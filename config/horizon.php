<?php

use Illuminate\Support\Str;

// =============================================================================
// Laravel Horizon Configuration
//
// Supervises queue workers for the three named queues defined in
// PROJECT_SPEC.md § 7:
//
//   media-critical  — thumbnail jobs   (highest priority, 2 workers)
//   media-standard  — resize jobs      (normal priority,  2 workers)
//   media-low       — optimise jobs    (lowest priority,  1 worker)
//
// Worker counts and queue assignments are defined in code, not environment
// variables, per the spec constraint (PROJECT_SPEC.md § 7).
//
// Dashboard access is controlled by the gate in HorizonServiceProvider.
// In local dev any authenticated user may access /horizon.
// =============================================================================

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    */
    'name' => env('HORIZON_NAME', 'MediaFlow'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    | null → dashboard served at /horizon on the primary domain.
    */
    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */
    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    | Must use the same Redis connection as QUEUE_CONNECTION=redis.
    */
    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    | Namespaces all Horizon keys to avoid collisions with other applications
    | sharing the same Redis instance.
    */
    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    | `web` provides session and CSRF; auth is enforced by the gate in
    | HorizonServiceProvider.
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds (seconds)
    |--------------------------------------------------------------------------
    | Horizon fires LongWaitDetected when a job waits longer than these values.
    | Aligned with NFR: < 1 second pickup latency in normal operation.
    */
    'waits' => [
        'redis:media-critical' => 5,
        'redis:media-standard' => 10,
        'redis:media-low'      => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming (minutes)
    |--------------------------------------------------------------------------
    */
    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080, // 7 days
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    | Leave empty — all MediaFlow jobs must be visible in the dashboard for
    | the Phase 3 human review checkpoint.
    */
    'silenced' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    | Imagick can be memory-intensive on large files. 256 MB matches the
    | memory_limit set in docker/php/php.ini.
    */
    'memory_limit' => 256,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Three supervisor pools — one per named queue.
    |
    | Priority ordering (left = highest): each supervisor's queue list is
    | ordered so workers always drain higher-priority queues first.
    |
    |   media-critical-supervisor → checks: critical → standard → low
    |   media-standard-supervisor → checks: standard → low
    |   media-low-supervisor      → checks: low only
    |
    | This guarantees thumbnail jobs (media-critical) are never starved by
    | slower resize or optimise jobs.
    */
    'environments' => [

        'local' => [

            // High-priority pool — thumbnail generation (fastest user feedback)
            'media-critical-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['media-critical', 'media-standard', 'media-low'],
                'balance'      => 'simple',
                'processes'    => 2,
                'tries'        => 3,
                'timeout'      => 120,
                'sleep'        => 3,
                'maxProcesses' => 2,
                'minProcesses' => 1,
            ],

            // Standard pool — resize operations
            'media-standard-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['media-standard', 'media-low'],
                'balance'      => 'simple',
                'processes'    => 2,
                'tries'        => 3,
                'timeout'      => 120,
                'sleep'        => 3,
                'maxProcesses' => 2,
                'minProcesses' => 1,
            ],

            // Low-priority pool — optimise and cleanup
            'media-low-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['media-low'],
                'balance'      => 'simple',
                'processes'    => 1,
                'tries'        => 3,
                'timeout'      => 120,
                'sleep'        => 5,
                'maxProcesses' => 1,
                'minProcesses' => 1,
            ],
        ],

        'production' => [
            'media-critical-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['media-critical', 'media-standard', 'media-low'],
                'balance'      => 'auto',
                'processes'    => 2,
                'tries'        => 3,
                'timeout'      => 120,
                'minProcesses' => 1,
                'maxProcesses' => 4,
            ],
            'media-standard-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['media-standard', 'media-low'],
                'balance'      => 'auto',
                'processes'    => 2,
                'tries'        => 3,
                'timeout'      => 120,
                'minProcesses' => 1,
                'maxProcesses' => 4,
            ],
            'media-low-supervisor' => [
                'connection'   => 'redis',
                'queue'        => ['media-low'],
                'balance'      => 'auto',
                'processes'    => 1,
                'tries'        => 3,
                'timeout'      => 120,
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
        ],

    ],

];
