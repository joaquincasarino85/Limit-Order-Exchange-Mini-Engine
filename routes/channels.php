<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for user-specific events
// Format: private-user.{id}
// Example: private-user.1, private-user.2, etc.
Broadcast::channel('user.{userId}', function ($user, $userId) {
    // Only allow users to listen to their own private channel
    return (int) $user->id === (int) $userId;
});

