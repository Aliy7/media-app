<?php

namespace Tests\Feature;

use App\Jobs\ProcessImageJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the three operational console commands:
 *   media:benchmark          — BenchmarkQueues
 *   mediaflow:trigger-failure — TriggerFailure
 *   mediaflow:verify-broadcast — VerifyBroadcast (integration, requires Soketi)
 */
class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // media:benchmark
    // -----------------------------------------------------------------------

    public function test_benchmark_returns_failure_when_no_users_exist(): void
    {
        Queue::fake();

        $this->artisan('media:benchmark')
            ->expectsOutputToContain('No users found.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_benchmark_dispatches_the_requested_number_of_jobs(): void
    {
        Queue::fake();

        User::factory()->create();

        $this->artisan('media:benchmark', ['count' => 3])
            ->assertSuccessful();

        Queue::assertPushed(ProcessImageJob::class, 3);
        $this->assertDatabaseCount('media', 3);
    }

    // -----------------------------------------------------------------------
    // mediaflow:trigger-failure
    // -----------------------------------------------------------------------

    public function test_trigger_failure_returns_failure_when_user_not_found(): void
    {
        Queue::fake();
        Storage::fake('media');

        $this->artisan('mediaflow:trigger-failure', ['--email' => 'nobody@example.com'])
            ->expectsOutputToContain('No user found with email: nobody@example.com')
            ->assertFailed();
    }

    public function test_trigger_failure_returns_failure_for_unknown_mode(): void
    {
        Queue::fake();
        Storage::fake('media');

        $user = User::factory()->create();

        $this->artisan('mediaflow:trigger-failure', [
            '--email' => $user->email,
            '--mode'  => 'invalid',
        ])->assertFailed();
    }

    public function test_trigger_failure_missing_mode_creates_record_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('media');

        $user = User::factory()->create();

        $this->artisan('mediaflow:trigger-failure', [
            '--email' => $user->email,
            '--mode'  => 'missing',
        ])->assertSuccessful();

        $this->assertDatabaseCount('media', 1);
        Queue::assertPushed(ProcessImageJob::class);
    }

    public function test_trigger_failure_corrupt_mode_copies_fixture_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('media');

        $user        = User::factory()->create();
        $fixturePath = base_path('tests/fixtures/corrupt-for-failure-test.jpg');

        $this->assertFileExists($fixturePath, 'Corrupt fixture must exist for this test');

        $this->artisan('mediaflow:trigger-failure', [
            '--email' => $user->email,
            '--mode'  => 'corrupt',
        ])->assertSuccessful();

        $this->assertDatabaseCount('media', 1);
        Queue::assertPushed(ProcessImageJob::class);
    }

    public function test_trigger_failure_corrupt_mode_fails_when_fixture_missing(): void
    {
        Queue::fake();
        Storage::fake('media');

        $user        = User::factory()->create();
        $fixturePath = base_path('tests/fixtures/corrupt-for-failure-test.jpg');
        $backup      = $fixturePath . '.bak';

        rename($fixturePath, $backup);

        try {
            $this->artisan('mediaflow:trigger-failure', [
                '--email' => $user->email,
                '--mode'  => 'corrupt',
            ])->assertFailed();
        } finally {
            rename($backup, $fixturePath);
        }
    }

    // -----------------------------------------------------------------------
    // mediaflow:verify-broadcast (integration — requires Soketi running)
    // -----------------------------------------------------------------------

    public function test_verify_broadcast_succeeds_when_soketi_is_reachable(): void
    {
        $this->artisan('mediaflow:verify-broadcast')
            ->assertSuccessful();
    }
}
