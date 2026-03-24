<?php

// =============================================================================
// Broadcasting Configuration
//
// MediaFlow uses the `pusher` driver pointed at a self-hosted Soketi server.
// Soketi speaks the Pusher wire protocol, so the driver works without
// modification — only the host, port, and scheme differ from hosted Pusher.
//
// Required .env variables:
//   BROADCAST_CONNECTION=pusher
//   PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET
//   PUSHER_HOST=soketi   (Docker service name — resolves inside the network)
//   PUSHER_PORT=6001
//   PUSHER_SCHEME=http   (no TLS in local dev)
//
// Browser-side (Laravel Echo) uses the VITE_PUSHER_* equivalents compiled
// into the JS bundle by Vite.
// =============================================================================

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    | The default broadcaster used when an event implements ShouldBroadcast.
    | Set to `pusher` so all broadcast events route through Soketi.
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [

        // ------------------------------------------------------------------
        // Soketi — self-hosted Pusher-protocol WebSocket server
        // The `pusher` driver is reused here; only the host/port differ.
        // ------------------------------------------------------------------
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                // PUSHER_HOST=soketi  — Docker internal service name.
                // Overrides the default Pusher cloud hostname entirely.
                'host'    => env('PUSHER_HOST', '127.0.0.1'),
                'port'    => env('PUSHER_PORT', 6001),
                'scheme'  => env('PUSHER_SCHEME', 'http'),
                'useTLS'  => env('PUSHER_SCHEME', 'http') === 'https',

                // cluster is required by the Pusher SDK but ignored by Soketi.
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),

                // Encrypted transport is false in local dev (no TLS on Soketi).
                'encrypted' => false,
            ],
            // Guzzle HTTP client options for server-side event publishing.
            // Timeout matches job execution ceiling (120 s) to avoid publish
            // failures during high-load processing.
            'client_options' => [
                'timeout' => 30,
            ],
        ],

        // ------------------------------------------------------------------
        // Log driver — useful for local debugging without Soketi running.
        // Switch BROADCAST_CONNECTION=log in .env to use this temporarily.
        // ------------------------------------------------------------------
        'log' => [
            'driver' => 'log',
        ],

        // ------------------------------------------------------------------
        // Null driver — silently discards all broadcast events.
        // Used in unit tests where Event::fake() is not sufficient.
        // ------------------------------------------------------------------
        'null' => [
            'driver' => 'null',
        ],

    ],

];
