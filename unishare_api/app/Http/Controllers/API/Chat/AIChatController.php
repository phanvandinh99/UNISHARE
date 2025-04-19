<?php

namespace App\Http\Controllers\API\Chat;

use App\Http\Controllers\Controller;
use App\Models\AIChat;
use App\Models\AIChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AIChatController extends Controller
{
    protected $openaiApiKey;
    
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->openaiApiKey = config('services.openai.api_key');
    }
    
    public function index(Request $request)
    {
        $chats = AIChat::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);
        
        return response()->json($chats);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $chat = AIChat::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'model' => 'gpt-4',
        ]);
        
        return response()->json($chat);
    }
    
    public function show(AIChat $aiChat)
    {
        // Check if user owns this chat
        if ($aiChat->user_id !== request()->user()->id) {
            return response()->json(['message' => 'You do not have permission to view this chat'], 403);
        }
        
        $messages = $aiChat->messages()->orderBy('created_at')->get();
        
        return response()->json([
            'chat' => $aiChat,
            'messages' => $messages,
        ]);
    }
    
    public function update(Request $request, AIChat $aiChat)
    {
        // Check if user owns this chat
        if ($aiChat->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to update this chat'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'model' => 'nullable|string|in:gpt-3.5-turbo,gpt-4',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $aiChat->update([
            'title' => $request->title,
            'model' => $request->model ?? $aiChat->model,
        ]);
        
        return response()->json($aiChat);
    }
    
    public function destroy(Request $request, AIChat $aiChat)
    {
        // Check if user owns this chat
        if ($aiChat->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to delete this chat'], 403);
        }
        
        // Delete all messages
        $aiChat->messages()->delete();
        
        // Delete the chat
        $aiChat->delete();
        
        return response()->json(['message' => 'Chat deleted successfully']);
    }
    
    public function sendMessage(Request $request, AIChat $aiChat)
    {
        // Check if user owns this chat
        if ($aiChat->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to send messages in this chat'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Save user message
        $userMessage = AIChatMessage::create([
            'ai_chat_id' => $aiChat->id,
            'role' => 'user',
            'content' => $request->content,
        ]);
        
        // Get previous messages for context
        $previousMessages = $aiChat->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->toArray();
        
        // Add system message if not present
        $hasSystemMessage = collect($previousMessages)->contains('role', 'system');
        
        if (!$hasSystemMessage) {
            array_unshift($previousMessages, [
                'role' => 'system',
                'content' => 'You are a helpful AI assistant for university students. You can help with academic questions, study tips, and general knowledge. Be concise, accurate, and helpful.',
            ]);
        }
        
        try {
            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $aiChat->model,
                'messages' => $previousMessages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);
            
            if ($response->successful()) {
                $aiResponse = $response->json()['choices'][0]['message']['content'];
                
                // Save AI response
                $aiMessage = AIChatMessage::create([
                    'ai_chat_id' => $aiChat->id,
                    'role' => 'assistant',
                    'content' => $aiResponse,
                ]);
                
                return response()->json([
                    'user_message' => $userMessage,
                    'ai_message' => $aiMessage,
                ]);
            } else {
                // Log the error
                \Log::error('OpenAI API Error: ' . $response->body());
                
                return response()->json(['message' => 'Failed to get response from AI service'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('OpenAI API Exception: ' . $e->getMessage());
            
            return response()->json(['message' => 'An error occurred while processing your request'], 500);
        }
    }
    
    public function clearHistory(Request $request, AIChat $aiChat)
    {
        // Check if user owns this chat
        if ($aiChat->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not have permission to clear this chat history'], 403);
        }
        
        // Delete all messages
        $aiChat->messages()->delete();
        
        return response()->json(['message' => 'Chat history cleared successfully']);
    }
}
