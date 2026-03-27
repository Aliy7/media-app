<?php

namespace Tests\Feature;

use App\Livewire\MediaLibrary;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers MediaLibrary::handleMediaUpdate(), toggleSort(), and all
 * dateBucket() branches (Yesterday, This week, This month, older).
 */
class MediaLibraryComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // -----------------------------------------------------------------------
    // handleMediaUpdate() (line 37)
    // -----------------------------------------------------------------------

    public function test_handle_media_update_stores_live_update_keyed_by_uuid(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->call('handleMediaUpdate', 'test-uuid', 'processing', 50, 'resize', null);

        $liveUpdates = $component->get('liveUpdates');
        $this->assertArrayHasKey('test-uuid', $liveUpdates);
        $this->assertEquals('processing', $liveUpdates['test-uuid']['status']);
        $this->assertEquals(50, $liveUpdates['test-uuid']['progress']);
        $this->assertEquals('resize', $liveUpdates['test-uuid']['step']);
        $this->assertNull($liveUpdates['test-uuid']['error']);
    }

    // -----------------------------------------------------------------------
    // toggleSort() (line 71)
    // -----------------------------------------------------------------------

    public function test_toggle_sort_switches_from_desc_to_asc(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->assertSet('sortOrder', 'desc')
            ->call('toggleSort')
            ->assertSet('sortOrder', 'asc');
    }

    public function test_toggle_sort_switches_back_from_asc_to_desc(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('sortOrder', 'asc')
            ->call('toggleSort')
            ->assertSet('sortOrder', 'desc');
    }

    // -----------------------------------------------------------------------
    // dateBucket() branches — exercised via render() grouping
    // -----------------------------------------------------------------------

    public function test_media_created_yesterday_is_bucketed_as_yesterday(): void
    {
        $user = User::factory()->create();
        Media::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->assertSee('Yesterday');
    }

    public function test_media_created_three_days_ago_is_bucketed_as_this_week(): void
    {
        $user = User::factory()->create();
        Media::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(3)]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->assertSee('This week');
    }

    public function test_media_created_fourteen_days_ago_is_bucketed_as_this_month(): void
    {
        $user = User::factory()->create();
        Media::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(14)]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->assertSee('This month');
    }

    public function test_media_created_sixty_days_ago_is_bucketed_as_month_year(): void
    {
        $user = User::factory()->create();
        $date = now()->subDays(60);
        Media::factory()->create(['user_id' => $user->id, 'created_at' => $date]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->assertSee($date->format('F Y'));
    }
}
