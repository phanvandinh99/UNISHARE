<?php

namespace App\Http\Controllers\API\WebSocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebSocketStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    public function status()
    {
        $pusherKey = config('broadcasting.connections.pusher.key');
        $pusherCluster = config('broadcasting.connections.pusher.options.cluster');
        
        try {
            // Check Pusher API status
            $response = Http::get("https://pusher.com/api/status");
            
            if ($response->successful()) {
                $pusherStatus = $response->json();
            } else {
                $pusherStatus = ['status' => 'unknown'];
            }
            
            return response()->json([
                'websocket_enabled' => !empty($pusherKey),
                'pusher_key' => $pusherKey ? substr($pusherKey, 0, 8) . '...' : null,
                'pusher_cluster' => $pusherCluster,
                'pusher_status' => $pusherStatus,
                'channels' => [
                    'user_channel' => 'private-user.' . auth()->id(),
                    'public_channels' => [
                        'documents' => 'documents',
                        'posts' => 'posts'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'websocket_enabled' => !empty($pusherKey),
                'pusher_key' => $pusherKey ? substr($pusherKey, 0, 8) . '...' : null,
                'pusher_cluster' => $pusherCluster,
                'error' => 'Could not check Pusher status: ' . $e->getMessage()
            ]);
        }
    }
    
    public function test(Request $request)
    {
        // Send a test event to the user's private channel
        try {
            broadcast(new \App\Events\TestWebSocket(auth()->user()))->toOthers();
            
            return response()->json([
                'success' => true,
                'message' => 'Test event broadcasted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast test event: ' . $e->getMessage()
            ], 500);
        }
    }
}
