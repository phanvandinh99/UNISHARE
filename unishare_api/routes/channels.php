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

// User private channel
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Group presence channel
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    $group = \App\Models\Group::find($groupId);
    return $group && $group->isMember($user);
});

// Chat presence channel
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\Chat::find($chatId);
    return $chat && $chat->hasParticipant($user);
});

// Documents public channel
Broadcast::channel('documents', function ($user) {
    return true;
});

// Posts public channel
Broadcast::channel('posts', function ($user) {
    return true;
});
