<?php

namespace Tests\Feature;

use App\Events\MediaProcessingCompleted;
use App\Events\MediaProcessingFailed;
use App\Events\MediaProcessingStarted;
use App\Events\MediaStepCompleted;
use App\Models\Media;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pusher\Pusher;
use Tests\TestCase;

/**
 * Task 4.5 — Soketi integration tests.
 *
 * These tests hit the REAL Pusher/Soketi connection, not a fake.
 * Auto-skip: if the app container cannot reach Soketi on TCP, all tests
 * are marked skipped — they will not fail CI runs without Docker.
 *
 * Run manually (requires Docker stack up):
 *   docker compose exec app php artisan test --filter SoketiBroadcastIntegrationTest
 */
class SoketiBroadcastIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Pusher $pusher;

    protected function setUp(): void
    {
        parent::setUp();

        // Re-register channels on the pusher driver.
        // channels.php registers callbacks on whichever driver is active at boot
        // (null, per phpunit.xml). Switching to pusher here requires copying the
        // channel callbacks across so the auth endpoint can find them.
        $manager  = $this->app->make(BroadcastManager::class);
        $channels = $manager->driver()->getChannels()->all();
        config(['broadcasting.default' => 'pusher']);
        foreach ($channels as $pattern => $callback) {
            $manager->driver()->channel($pattern, $callback);
        }

        // Skip the whole suite if Soketi is not reachable.
        $host   = config('broadcasting.connections.pusher.options.host', 'soketi');
        $port   = (int) config('broadcasting.connections.pusher.options.port', 6001);
        $socket = @fsockopen($host, $port, $errno, $errstr, timeout: 2);

        if ($socket === false) {
            $this->markTestSkipped(
                "Soketi not reachable at {$host}:{$port} — start the Docker stack to run integration tests."
            );
        }

        fclose($socket);

        // Shared Pusher client for assertion queries.
        $cfg          = config('broadcasting.connections.pusher');
        $this->pusher = new Pusher(
            $cfg['key'],
            $cfg['secret'],
            $cfg['app_id'],
            array_merge($cfg['options'], ['useTLS' => false]),
            null,
            $cfg['client_options'] ?? [],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Server-side connectivity
    // ─────────────────────────────────────────────────────────────────────────

    public function test_soketi_channels_api_responds(): void
    {
        $response = $this->pusher->getChannels();

        $this->assertIsObject($response);
        $this->assertObjectHasProperty('channels', $response);
        $this->assertIsArray($response->channels);
    }

    public function test_pusher_sdk_can_trigger_event(): void
    {
        $result = $this->pusher->trigger(
            'private-mediaflow-test',
            'test.event',
            ['ts' => now()->toIso8601String()],
        );

        $this->assertTrue($result->ok ?? false, 'Soketi rejected trigger — response: ' . json_encode($result));
    }

    public function test_laravel_broadcast_manager_uses_pusher_driver(): void
    {
        $driver = app(BroadcastManager::class)->driver('pusher');

        // broadcast() sends a signed HTTP POST to Soketi.
        // An exception here means the config chain is broken.
        $driver->broadcast(
            ['private-mediaflow-test'],
            'mediaflow.driver.verify',
            ['ts' => now()->toIso8601String()],
        );

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Domain events broadcast through the pusher driver
    //
    // The unit tests in MediaEventsTest already verify channel names, payload
    // keys, and broadcastAs() names. Here we verify those same events can be
    // physically dispatched through the real broadcaster to a live Soketi
    // without throwing — i.e. the full config chain is wired correctly.
    // ─────────────────────────────────────────────────────────────────────────

    public function test_media_processing_started_dispatches_to_soketi(): void
    {
        $media = Media::factory()->create();
        $event = new MediaProcessingStarted($media);

        $this->broadcastEventToSoketi($event);

        $this->assertTrue(true);
    }

    public function test_media_step_completed_dispatches_to_soketi(): void
    {
        $media = Media::factory()->create();
        $event = new MediaStepCompleted($media, 'resize', 33, 'media/test-resized.jpg');

        $this->broadcastEventToSoketi($event);

        $this->assertTrue(true);
    }

    public function test_media_processing_completed_dispatches_to_soketi(): void
    {
        $media = Media::factory()->create([
            'status'  => 'completed',
            'outputs' => ['thumbnail' => 'media/thumb.jpg', 'optimized' => 'media/opt.jpg'],
        ]);
        $event = new MediaProcessingCompleted($media);

        $this->broadcastEventToSoketi($event);

        $this->assertTrue(true);
    }

    public function test_media_processing_failed_dispatches_to_soketi(): void
    {
        $media = Media::factory()->create(['status' => 'failed', 'error_message' => 'Imagick error']);
        $event = new MediaProcessingFailed($media, 'resize', 'Imagick error');

        $this->broadcastEventToSoketi($event);

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Channel authentication endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function test_channel_auth_returns_signed_token_for_owner(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => "private-media.{$media->uuid}",
            'socket_id'    => '123.456',
        ]);

        $response->assertOk();

        $body = $response->json();
        $this->assertArrayHasKey('auth', $body);
        // Soketi expects "app-key:hmac-sha256-signature"
        $this->assertStringContainsString(':', $body['auth']);
        [$returnedKey] = explode(':', $body['auth']);
        $this->assertSame(config('broadcasting.connections.pusher.key'), $returnedKey);
    }

    public function test_channel_auth_denies_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)->postJson('/broadcasting/auth', [
            'channel_name' => "private-media.{$media->uuid}",
            'socket_id'    => '123.456',
        ])->assertForbidden();
    }

    public function test_channel_auth_denies_unauthenticated(): void
    {
        $media = Media::factory()->create();

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-media.{$media->uuid}",
            'socket_id'    => '123.456',
        ])->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatches a ShouldBroadcast event synchronously through the real
     * pusher driver. Bypasses the queue so the HTTP call to Soketi happens
     * immediately in the test process.
     */
    private function broadcastEventToSoketi(object $event): void
    {
        $channel = $event->broadcastOn();

        // PrivateChannel::__toString() returns 'private-{name}'.
        // PusherBroadcaster::broadcast() accepts channel name strings.
        $channelName = (string) $channel;

        app(BroadcastManager::class)->driver('pusher')->broadcast(
            [$channelName],
            $event->broadcastAs(),
            $event->broadcastWith(),
        );
    }
}
