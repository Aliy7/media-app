<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;

class BenchmarkQueues extends Command
{
    protected $signature   = 'media:benchmark {count=5 : Number of jobs to dispatch}';
    protected $description = 'Dispatch ProcessImageJob(s) to observe Horizon queue behaviour';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $user  = User::first();

        if (! $user) {
            $this->error('No users found.');
            return self::FAILURE;
        }

        $this->info("Dispatching {$count} ProcessImageJob(s)...");

        for ($i = 1; $i <= $count; $i++) {
            $media = Media::factory()->create([
                'user_id'           => $user->id,
                'original_filename' => "bench_{$i}.jpg",
                'stored_filename'   => "fixture.jpg",
                'status'            => 'pending',
            ]);
            ProcessImageJob::dispatch($media);
            $this->line("  [{$i}/{$count}] Dispatched media #{$media->id}");
        }

        $this->newLine();
        $this->info('Jobs dispatched. Watch: http://localhost/horizon');
        $this->table(
            ['Queue', 'Purpose', 'Workers', 'Wait threshold'],
            [
                ['media-standard', 'ProcessImageJob (orchestrator)', '2', '10s'],
                ['media-critical', 'GenerateThumbnailJob',           '2', '5s'],
                ['media-standard', 'ResizeImageJob',                 '2', '10s'],
                ['media-low',      'OptimizeImageJob',               '1', '30s'],
            ]
        );

        return self::SUCCESS;
    }
}
