<?php

use App\Models\Media;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Each media item gets its own private channel scoped to the owner.
| The callback returns true only when the authenticated user's id matches
| the user_id on the Media record identified by the UUID segment.
|
| Channel: private-media.{uuid}
|
*/

// Register the /broadcasting/auth route with auth middleware so the
// channel callbacks receive a properly authenticated user.
Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('media.{uuid}', function ($user, string $uuid): bool {
    return $user ? Media::where('uuid', $uuid)->where('user_id', $user->id)->exists() : false;
});
