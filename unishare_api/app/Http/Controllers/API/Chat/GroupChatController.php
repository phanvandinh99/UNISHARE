<?php

namespace App\Http\Controllers\API\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    public function index(Request $request)
    {
        // Get all group chats where the user is a participant
        $chats = Chat::where('type', 'group')
            ->whereHas('participants', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['participants.user'])
            ->latest()
            ->paginate(15);
        
        return ChatResource::collection($chats);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'participants' => 'required|array|min:2',
            'participants.*' => 'exists:users,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Make sure the current user is included in participants
        $participants = collect($request->participants);
        if (!$participants->contains($request->user()->id)) {
            $participants->push($request->user()->id);
        }
        
        // Create the group chat
        $chat = Chat::create([
            'name' => $request->name,
            'type' => 'group',
        ]);
        
        // Add participants
        foreach ($participants as $userId) {
            ChatParticipant::create([
                'chat_id' => $chat->id,
                'user_id' => $userId,
            ]);
        }
        
        return new ChatResource($chat->load('participants.user'));
    }
    
    public function show(Chat $chat)
    {
        // Check if the chat is a group chat
        if ($chat->type !== 'group') {
            return response()->json(['message' => 'This is not a group chat'], 400);
        }
        
        // Check if user is a participant
        $isParticipant = $chat->participants()->where('user_id', request()->user()->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant in this chat'], 403);
        }
        
        return new ChatResource($chat->load('participants.user'));
    }
    
    public function update(Request $request, Chat $chat)
    {
        // Check if the chat is a group chat
        if ($chat->type !== 'group') {
            return response()->json(['message' => 'This is not a group chat'], 400);
        }
        
        // Check if user is a participant
        $isParticipant = $chat->participants()->where('user_id', $request->user()->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant in this chat'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $chat->update([
            'name' => $request->name,
        ]);
        
        return new ChatResource($chat->load('participants.user'));
    }
    
    public function addParticipants(Request $request, Chat $chat)
    {
        // Check if the chat is a group chat
        if ($chat->type !== 'group') {
            return response()->json(['message' => 'This is not a group chat'], 400);
        }
        
        // Check if user is a participant
        $isParticipant = $chat->participants()->where('user_id', $request->user()->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant in this chat'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Add new participants
        foreach ($request->participants as $userId) {
            // Check if user is already a participant
            $exists = $chat->participants()->where('user_id', $userId)->exists();
            
            if (!$exists) {
                ChatParticipant::create([
                    'chat_id' => $chat->id,
                    'user_id' => $userId,
                ]);
            }
        }
        
        return new ChatResource($chat->load('participants.user'));
    }
    
    public function removeParticipant(Request $request, Chat $chat, $userId)
    {
        // Check if the chat is a group chat
        if ($chat->type !== 'group') {
            return response()->json(['message' => 'This is not a group chat'], 400);
        }
        
        // Check if user is a participant
        $isParticipant = $chat->participants()->where('user_id', $request->user()->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant in this chat'], 403);
        }
        
        // Cannot remove yourself if you're the only participant
        if ($userId == $request->user()->id && $chat->participants()->count() === 1) {
            return response()->json(['message' => 'You cannot remove yourself as you are the only participant. Delete the chat instead.'], 400);
        }
        
        // Remove the participant
        $chat->participants()->where('user_id', $userId)->delete();
        
        return new ChatResource($chat->load('participants.user'));
    }
    
    public function leave(Request $request, Chat $chat)
    {
        // Check if the chat is a group chat
        if ($chat->type !== 'group') {
            return response()->json(['message' => 'This is not a group chat'], 400);
        }
        
        // Check if user is a participant
        $isParticipant = $chat->participants()->where('user_id', $request->user()->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant in this chat'], 403);
        }
        
        // Remove the user from the chat
        $chat->participants()->where('user_id', $request->user()->id)->delete();
        
        // If no participants left, delete the chat
        if ($chat->participants()->count() === 0) {
            $chat->messages()->delete();
            $chat->delete();
            
            return response()->json(['message' => 'You left the chat and it was deleted as there are no participants left']);
        }
        
        return response()->json(['message' => 'You left the chat successfully']);
    }
    
    public function destroy(Request $request, Chat $chat)
    {
        // Check if the chat is a group chat
        if ($chat->type !== 'group') {
            return response()->json(['message' => 'This is not a group chat'], 400);
        }
        
        // Check if user is a participant
        $isParticipant = $chat->participants()->where('user_id', $request->user()->id)->exists();
        
        if (!$isParticipant) {
            return response()->json(['message' => 'You are not a participant in this chat'], 403);
        }
        
        // Delete all messages in the chat
        $chat->messages()->delete();
        
        // Delete all participants
        $chat->participants()->delete();
        
        // Delete the chat
        $chat->delete();
        
        return response()->json(['message' => 'Chat deleted successfully']);
    }
    
    public function createFromGroup(Request $request, Group $group)
    {
        // Check if user is a member of the group
        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        
        if (!$isMember) {
            return response()->json(['message' => 'You are not a member of this group'], 403);
        }
        
        // Check if a chat already exists for this group
        $existingChat = Chat::where('group_id', $group->id)->first();
        
        if ($existingChat) {
            return new ChatResource($existingChat->load('participants.user'));
        }
        
        // Create a new chat for the group
        $chat = Chat::create([
            'name' => $group->name . ' Chat',
            'type' => 'group',
            'group_id' => $group->id,
        ]);
        
        // Add all group members as participants
        $members = $group->members()->get();
        
        foreach ($members as $member) {
            ChatParticipant::create([
                'chat_id' => $chat->id,
                'user_id' => $member->user_id,
            ]);
        }
        
        return new ChatResource($chat->load('participants.user'));
    }
}
