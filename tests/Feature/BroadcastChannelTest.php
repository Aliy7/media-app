<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

protected function setUp(): void
{
    parent::setUp();

    $manager = $this->app->make(\Illuminate\Broadcasting\BroadcastManager::class);

    // channels.php registers callbacks on the null broadcaster (phpunit.xml sets
    // BROADCAST_CONNECTION=null). Copy them to the pusher broadcaster so that
    // verifyUserCanAccessChannel can actually run the auth callback.
    $channels = $manager->driver()->getChannels()->all();

    config(['broadcasting.default' => 'pusher']);

    foreach ($channels as $pattern => $callback) {
        $manager->driver()->channel($pattern, $callback);
    }
}

    public function test_owner_is_authorised_on_their_media_channel(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-media.' . $media->uuid,
            'socket_id'    => '123.456',
        ]);

        $response->assertOk();
    }

    public function test_other_user_is_denied_on_another_users_media_channel(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-media.' . $media->uuid,
            'socket_id'    => '123.456',
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_denied(): void
    {
        $media = Media::factory()->create();

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-media.' . $media->uuid,
            'socket_id'    => '123.456',
        ]);

        $response->assertUnauthorized();
    }

    public function test_non_existent_uuid_is_denied(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-media.00000000-0000-0000-0000-000000000000',
            'socket_id'    => '123.456',
        ]);

        $response->assertForbidden();
    }
}
