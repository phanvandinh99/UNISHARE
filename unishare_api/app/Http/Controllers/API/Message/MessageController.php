<?php

namespace App\Http\Controllers\API\Message;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Chat;
use App\Models\Message;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;
    
    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }
    
    public function index(Request $request, Chat $chat)
    {
        // Check if user is part of this chat
        if ($chat->user1_id !== $request->user()->id && $chat->user2_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to view messages in this chat'], 403);
        }
        
        $messages = $chat->messages()->latest()->paginate(20);
        
        // Mark messages as read
        $chat->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        return MessageResource::collection($messages);
    }
    
    public function store(Request $request, Chat $chat)
    {
        // Check if user is part of this chat
        if ($chat->user1_id !== $request->user()->id && $chat->user2_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to send messages in this chat'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'attachment' => 'nullable|file|max:10240', // 10MB max
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle attachment if provided
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            try {
                $fileUpload = $this->fileUploadService->uploadFile(
                    $request->file('attachment'),
                    $request->user()->id,
                    'message_attachment'
                );
                $attachmentPath = $fileUpload->file_path;
            } catch (\Exception $e) {
                // Log the error but continue with message creation
                \Log::error('Failed to upload attachment: ' . $e->getMessage());
            }
        }
        
        // Create the message
        $message = $chat->messages()->create([
            'sender_id' => $request->user()->id,
            'content' => $request->content,
            'attachment' => $attachmentPath,
            'is_read' => false,
        ]);
        
        // Update the chat's updated_at timestamp
        $chat->touch();
        
        // Broadcast the message
        broadcast(new MessageSent($message))->toOthers();
        
        // Determine the recipient
        $recipientId = $chat->user1_id === $request->user()->id ? $chat->user2_id : $chat->user1_id;
        
        // Send notification to the recipient
        $this->notificationService->sendNotification(
            $recipientId,
            'new_message',
            "New message from {$request->user()->name}",
            ['chat_id' => $chat->id, 'message_id' => $message->id]
        );
        
        return new MessageResource($message);
    }
    
    public function markAsRead(Request $request, Chat $chat)
    {
        // Check if user is part of this chat
        if ($chat->user1_id !== $request->user()->id && $chat->user2_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to access this chat'], 403);
        }
        
        // Mark all messages from the other user as read
        $chat->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        return response()->json(['message' => 'Messages marked as read']);
    }
    
    public function destroy(Request $request, Chat $chat, Message $message)
    {
        // Check if user is the sender of this message
        if ($message->sender_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to delete this message'], 403);
        }
        
        // Delete attachment if it exists
        if ($message->attachment) {
            try {
                // Find the file upload record
                $fileUpload = FileUpload::where('file_path', $message->attachment)->first();
                
                if ($fileUpload) {
                    $this->fileUploadService->deleteFileUpload($fileUpload);
                }
            } catch (\Exception $e) {
                // Log the error but continue with message deletion
                \Log::error('Failed to delete message attachment: ' . $e->getMessage());
            }
        }
        
        $message->delete();
        
        return response()->json(['message' => 'Message deleted successfully']);
    }
}
