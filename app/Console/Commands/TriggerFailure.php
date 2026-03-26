<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Triggers the full failure state machine for a given user so the
 * MediaProcessingFailed broadcast and the "failed + retry" UI can be
 * demonstrated in the browser without a real broken image.
 *
 * Two modes:
 *
 *   --mode=missing  (default)
 *       Creates a Media record whose stored_filename does not exist on disk.
 *       ProcessImageJob → ResizeImageJob::handle() → Storage::get() fails →
 *       job retried 3 times (10 s + 30 s backoff) → failed() broadcasts
 *       MediaProcessingFailed. Total wall-clock ~41 s.
 *       Shows: pending → (5 s) → processing → (retries in Horizon) → failed.
 *
 *   --mode=corrupt
 *       Copies the crafted corrupt JPEG fixture onto the media disk, creates
 *       a Media record pointing to it, and dispatches the job. ResizeImageJob
 *       opens the file successfully but Imagick throws at resizeImage() time.
 *       Same retry timeline as missing mode, but exercises the Imagick path.
 *
 * Run:
 *   docker compose exec app php artisan mediaflow:trigger-failure \
 *       --email=user@example.com
 *
 *   docker compose exec app php artisan mediaflow:trigger-failure \
 *       --email=user@example.com --mode=corrupt
 */
class TriggerFailure extends Command
{
    protected $signature = 'mediaflow:trigger-failure
                            {--email= : Email of the user to create the record for}
                            {--mode=missing : missing | corrupt}';

    protected $description = 'Seed a guaranteed-to-fail media record to demonstrate the failure broadcast (task 4.5)';

    public function handle(): int
    {
        // ── Resolve user ──────────────────────────────────────────────────────
        $email = $this->option('email');
        if (! $email) {
            $email = $this->ask('User email');
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $mode = $this->option('mode');
        if (! in_array($mode, ['missing', 'corrupt'])) {
            $this->error("Unknown mode '{$mode}'. Use: missing | corrupt");
            return self::FAILURE;
        }

        // ── Set up the stored file ────────────────────────────────────────────
        $uuid            = Str::uuid()->toString();
        $storedFilename  = $uuid . '.jpg';

        if ($mode === 'corrupt') {
            $fixturePath = base_path('tests/fixtures/corrupt-for-failure-test.jpg');
            if (! file_exists($fixturePath)) {
                $this->error('Fixture file not found: tests/fixtures/corrupt-for-failure-test.jpg');
                $this->line('Run: php artisan mediaflow:verify-broadcast first to confirm setup.');
                return self::FAILURE;
            }
            Storage::disk('media')->put($storedFilename, file_get_contents($fixturePath));
            $this->line("  Fixture copied to media disk: <fg=yellow>{$storedFilename}</fg=yellow>");
        }
        // In 'missing' mode we intentionally do NOT write a file — the job
        // will fail when it tries to open a path that doesn't exist.

        // ── Create Media record ───────────────────────────────────────────────
        $media = new Media([
            'original_filename' => $mode === 'corrupt' ? 'corrupt-test-image.jpg' : 'missing-file-test.jpg',
            'stored_filename'   => $storedFilename,
            'mime_type'         => 'image/jpeg',
            'file_size'         => $mode === 'corrupt' ? Storage::disk('media')->size($storedFilename) : 3241,
        ]);
        $media->user_id = $user->id;
        $media->uuid    = $uuid;
        $media->status  = Media::STATUS_PENDING;
        $media->save();

        // ── Dispatch with standard 5 s delay ─────────────────────────────────
        ProcessImageJob::dispatch($media)->delay(now()->addSeconds(5));

        // ── Output ────────────────────────────────────────────────────────────
        $this->line('');
        $this->line('<fg=green>Record created and job dispatched.</>');
        $this->line('');
        $this->line("  User:  {$user->email}");
        $this->line("  UUID:  {$uuid}");
        $this->line("  Mode:  {$mode}");
        $this->line('');

        if ($mode === 'missing') {
            $this->line('<options=bold>What will happen:</>');
            $this->line('  • Card appears in dashboard immediately (pending)');
            $this->line('  • After 5 s: ResizeImageJob fires → file not found → exception');
            $this->line('  • Horizon retries: attempt 2 after 10 s, attempt 3 after 30 s');
            $this->line('  • After ~41 s total: failed() broadcasts MediaProcessingFailed');
            $this->line('  • Dashboard card → red "Failed" badge + Retry button');
        } else {
            $this->line('<options=bold>What will happen:</>');
            $this->line('  • Card appears in dashboard immediately (pending)');
            $this->line('  • After 5 s: ResizeImageJob fires → Imagick opens file');
            $this->line('              → resizeImage() throws ImagickException (corrupt data)');
            $this->line('  • Horizon retries: attempt 2 after 10 s, attempt 3 after 30 s');
            $this->line('  • After ~41 s total: failed() broadcasts MediaProcessingFailed');
            $this->line('  • Dashboard card → red "Failed" badge + Retry button');
        }

        $this->line('');
        $this->line('<options=bold>Watch it live:</>');
        $this->line('  Browser:  http://localhost/dashboard');
        $this->line('  Horizon:  http://localhost/horizon  (watch retries under Failed Jobs)');
        $this->line('  Soketi:   docker compose logs soketi --follow');
        $this->line('');
        $this->line('<options=bold>To test the Retry flow:</>');
        $this->line('  1. Wait for the "Failed" badge to appear');
        $this->line('  2. Click Retry on the card');
        $this->line('  3. Card goes back to Pending → repeats the failure cycle');
        $this->line('  4. (Demonstrates the full pending → failed → retry → pending arc)');
        $this->line('');

        return self::SUCCESS;
    }
}
