<?php

namespace App\Http\Controllers\API\Message;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    public function index(Request $request)
    {
        $chats = Chat::where(function ($query) use ($request) {
            $query->where('user1_id', $request->user()->id)
                  ->orWhere('user2_id', $request->user()->id);
        })->with(['user1', 'user2', 'lastMessage'])->latest('updated_at')->paginate(15);
        
        return ChatResource::collection($chats);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if chat already exists
        $chat = Chat::where(function ($query) use ($request) {
            $query->where('user1_id', $request->user()->id)
                  ->where('user2_id', $request->user_id);
        })->orWhere(function ($query) use ($request) {
            $query->where('user1_id', $request->user_id)
                  ->where('user2_id', $request->user()->id);
        })->first();
        
        if ($chat) {
            return new ChatResource($chat);
        }
        
        // Create a new chat
        $chat = Chat::create([
            'user1_id' => $request->user()->id,
            'user2_id' => $request->user_id,
        ]);
        
        return new ChatResource($chat);
    }
    
    public function show(Chat $chat)
    {
        // Check if user is part of this chat
        if ($chat->user1_id !== request()->user()->id && $chat->user2_id !== request()->user()->id) {
            return response()->json(['message' => 'You do not have permission to view this chat'], 403);
        }
        
        return new ChatResource($chat->load(['user1', 'user2']));
    }
    
    public function destroy(Request $request, Chat $chat)
    {
        // Check if user is part of this chat
        if ($chat->user1_id !== $request->user()->id && $chat->user2_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to delete this chat'], 403);
        }
        
        // Delete all messages in the chat
        $chat->messages()->delete();
        
        // Delete the chat
        $chat->delete();
        
        return response()->json(['message' => 'Chat deleted successfully']);
    }
}
