import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ---------------------------------------------------------------------------
// Laravel Echo — real-time broadcast subscriptions via Soketi (Pusher protocol)
//
// VITE_PUSHER_HOST must be `localhost` (host-mapped port) in local dev, NOT
// `soketi` (Docker-internal). See .env VITE_PUSHER_HOST comment.
// ---------------------------------------------------------------------------
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    wsHost: import.meta.env.VITE_PUSHER_HOST ?? window.location.hostname,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,   // prevents pusher-js pinging Pusher cloud stats endpoint
});
