<?php

namespace App\Console\Commands;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Console\Command;
use Pusher\Pusher;
use Throwable;

/**
 * Task 4.5 — Soketi broadcast verification.
 *
 * Checks every layer of the server-side broadcast pipeline:
 *   1. TCP connectivity from the app container to Soketi
 *   2. Pusher SDK authentication against Soketi HTTP API
 *   3. Event publish and Soketi acknowledgement
 *   4. Full Laravel broadcaster pipeline (config → BroadcastManager → Pusher driver)
 *
 * Run:
 *   docker compose exec app php artisan mediaflow:verify-broadcast
 */
class VerifyBroadcast extends Command
{
    protected $signature   = 'mediaflow:verify-broadcast';
    protected $description = 'Verify Soketi connectivity and Laravel broadcast pipeline (task 4.5)';

    private int  $stepNum = 0;
    private bool $allPass = true;

    public function handle(): int
    {
        $this->line('');
        $this->line('<fg=cyan;options=bold>MediaFlow — Soketi Broadcast Verification</fg=cyan;options=bold>');
        $this->line('<fg=gray>════════════════════════════════════════════</fg=gray>');
        $this->line('');

        $pusher = null;

        $this->check('Soketi TCP reachable from app container', function () {
            $host = (string) config('broadcasting.connections.pusher.options.host', 'soketi');
            $port = (int)    config('broadcasting.connections.pusher.options.port', 6001);

            $socket = @fsockopen($host, $port, $errno, $errstr, 3);

            if ($socket === false) {
                throw new \RuntimeException("Cannot connect to {$host}:{$port} — {$errstr} (errno {$errno})");
            }

            fclose($socket);
        });

        $this->check('Pusher SDK authenticates with Soketi (channels API)', function () use (&$pusher) {
            $cfg    = config('broadcasting.connections.pusher');
            $pusher = new Pusher(
                $cfg['key'],
                $cfg['secret'],
                $cfg['app_id'],
                array_merge($cfg['options'], ['useTLS' => false]),
                null,
                $cfg['client_options'] ?? [],
            );

            $response = $pusher->getChannels();

            if (! isset($response->channels)) {
                throw new \RuntimeException('Channels API returned unexpected response: ' . json_encode($response));
            }
        });

        $this->check('Pusher SDK publishes test event → Soketi acknowledges', function () use (&$pusher) {
            if (! $pusher) {
                throw new \RuntimeException('Pusher client unavailable — step 2 failed.');
            }

            $result = $pusher->trigger(
                'private-mediaflow-verify',
                'mediaflow.verify',
                ['ts' => now()->toIso8601String(), 'source' => 'artisan:verify-broadcast'],
            );

            if (! ($result->ok ?? false)) {
                throw new \RuntimeException('Soketi rejected the event. Response: ' . json_encode($result));
            }
        });

        $this->check('Laravel BroadcastManager routes through pusher driver', function () {
            $driver = app(BroadcastManager::class)->driver('pusher');

            $driver->broadcast(
                ['private-mediaflow-verify'],
                'mediaflow.pipeline.verify',
                ['ts' => now()->toIso8601String()],
            );
        });

        $this->line('');
        $this->line('<fg=gray>────────────────────────────────────────────</fg=gray>');

        if ($this->allPass) {
            $this->line('');
            $this->line('<fg=green;options=bold> ✓  All server-side checks passed.</fg=green;options=bold>');
            $this->line('');
            $this->line('<options=bold>Next — verify browser-side reception manually:</>');
            $this->line('');
            $this->line('  1. Open http://localhost → log in');
            $this->line('  2. Browser devtools → Network → WS filter');
            $this->line('     Expect: ws://localhost:6001/app/mediaflow-app-key');
            $this->line('             101 Switching Protocols');
            $this->line('');
            $this->line('  3. Upload an image → watch browser console for:');
            $this->line('     Subscribed to channel: private-media.{uuid}');
            $this->line('     Echo event: .media.processing.started');
            $this->line('     Echo event: .media.step.completed  (x3)');
            $this->line('     Echo event: .media.processing.completed');
            $this->line('');
            $this->line('  4. Soketi live log (second terminal):');
            $this->line('     docker compose logs soketi --follow');
            $this->line('');
            $this->line('  5. Enable verbose Soketi logging:');
            $this->line('     Set SOKETI_DEBUG=1 in .env');
            $this->line('     docker compose up -d soketi');
            $this->line('     Re-run upload — logs show every connect/subscribe/publish');
            $this->line('');
            return self::SUCCESS;
        }

        $this->line('');
        $this->error(' ✗  One or more checks failed — review errors above.');
        $this->line('');
        $this->line('Common fixes:');
        $this->line('  docker compose ps                  confirm soketi is Up');
        $this->line('  .env: PUSHER_HOST=soketi  PUSHER_PORT=6001');
        $this->line('  docker compose restart soketi');
        $this->line('  docker compose logs soketi         check startup errors');
        $this->line('');
        return self::FAILURE;
    }

    private function check(string $label, \Closure $fn): void
    {
        $this->stepNum++;
        $this->output->write("  Step {$this->stepNum}  {$label} ... ");

        try {
            $fn();
            $this->line('<fg=green>PASS</fg=green>');
        } catch (Throwable $e) {
            $this->line('<fg=red>FAIL</fg=red>');
            $this->line("         <fg=red>→ {$e->getMessage()}</fg=red>");
            $this->allPass = false;
        }
    }
}
