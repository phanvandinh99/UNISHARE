<?php

namespace App\Http\Controllers\API\WebSocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pusher\Pusher;

class WebSocketController extends Controller
{
    protected $pusher;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );
    }

    public function auth(Request $request)
    {
        $user = Auth::user();
        $socketId = $request->socket_id;
        $channelName = $request->channel_name;

        // For private channels
        if (strpos($channelName, 'private-') === 0) {
            $channelData = ['user_id' => $user->id];
            $auth = $this->pusher->authorizeChannel($channelName, $socketId, $channelData);
            return response()->json($auth);
        }

        // For presence channels
        if (strpos($channelName, 'presence-') === 0) {
            $presenceData = [
                'user_id' => $user->id,
                'user_info' => [
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                ]
            ];
            $auth = $this->pusher->authorizePresenceChannel($channelName, $socketId, $user->id, $presenceData);
            return response()->json($auth);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function getChannels()
    {
        $user = Auth::user();

        // Get user's private channel
        $userChannel = 'private-user.' . $user->id;

        // Get user's group channels
        $groupChannels = $user->groups()->pluck('id')->map(function ($groupId) {
            return 'presence-group.' . $groupId;
        })->toArray();

        // Get user's chat channels
        $chatChannels = $user->chats()->pluck('id')->map(function ($chatId) {
            return 'presence-chat.' . $chatId;
        })->toArray();

        return response()->json([
            'user_channel' => $userChannel,
            'group_channels' => $groupChannels,
            'chat_channels' => $chatChannels,
        ]);
    }
}
