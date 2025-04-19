<?php

namespace App\Http\Controllers\API\Post;

use App\Events\PostCreated;
use App\Events\PostLiked;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;
    
    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
        $this->middleware('permission:delete any post', ['only' => ['approve', 'reject']]);
    }
    
    public function index(Request $request)
    {
        $query = Post::query();
        
        // Apply filters
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Only show approved posts to regular users
        if (!$request->user()->hasRole(['admin', 'moderator'])) {
            $query->where('is_approved', true);
        }
        
        // Sort by latest
        $query->latest();
        
        $posts = $query->paginate(15);
        
        return PostResource::collection($posts);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'group_id' => 'nullable|exists:groups,id',
            'attachments.*' => 'nullable|file|max:20480', // 20MB max per attachment
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if user has permission to create posts
        if (!$request->user()->can('create post')) {
            return response()->json(['message' => 'You do not have permission to create posts'], 403);
        }
        
        // Create the post record
        $post = Post::create([
            'user_id' => $request->user()->id,
            'group_id' => $request->group_id,
            'title' => $request->title,
            'content' => $request->content,
            'is_approved' => $request->user()->hasRole(['admin', 'moderator']) ? true : true, // Auto-approve for now
        ]);
        
        // Handle attachments if any
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                try {
                    $fileUpload = $this->fileUploadService->uploadFile(
                        $attachment,
                        $request->user()->id,
                        'post_attachment',
                        $post->id
                    );
                    
                    // Create the attachment record
                    $post->attachments()->create([
                        'file_path' => $fileUpload->file_path,
                        'file_name' => $fileUpload->original_filename,
                        'file_type' => $fileUpload->file_type,
                        'file_size' => $fileUpload->file_size,
                    ]);
                } catch (\Exception $e) {
                    // Log the error but continue with post creation
                    \Log::error('Failed to upload attachment: ' . $e->getMessage());
                }
            }
        }
        
        // Broadcast the post creation event
        broadcast(new PostCreated($post))->toOthers();
        
        // Send notification to group members if post is in a group
        if ($post->group_id) {
            $group = $post->group;
            $members = $group->members()->where('user_id', '!=', $request->user()->id)->get();
            
            foreach ($members as $member) {
                $this->notificationService->sendNotification(
                    $member->user_id,
                    'new_post_in_group',
                    "New post '{$post->title}' in group '{$group->name}'",
                    ['post_id' => $post->id, 'group_id' => $group->id]
                );
            }
        }
        
        return new PostResource($post);
    }
    
    public function show(Post $post)
    {
        // Increment view count
        $post->increment('view_count');
        
        return new PostResource($post);
    }
    
    public function update(Request $request, Post $post)
    {
        // Check if user has permission to update this post
        if ($post->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'moderator'])) {
            return response()->json(['message' => 'You do not have permission to update this post'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $post->update([
            'title' => $request->title,
            'content' => $request->content,
        ]);
        
        return new PostResource($post);
    }
    
    public function destroy(Request $request, Post $post)
    {
        // Check if user has permission to delete this post
        if ($post->user_id !== $request->user()->id && !$request->user()->can('delete any post')) {
            return response()->json(['message' => 'You do not have permission to delete this post'], 403);
        }
        
        // Delete attachments if any
        foreach ($post->attachments as $attachment) {
            try {
                // Get the file upload record
                $fileUpload = $attachment->fileUpload;
                
                if ($fileUpload) {
                    $this->fileUploadService->deleteFileUpload($fileUpload);
                }
                
                $attachment->delete();
            } catch (\Exception $e) {
                // Log the error but continue with post deletion
                \Log::error('Failed to delete attachment: ' . $e->getMessage());
            }
        }
        
        $post->delete();
        
        return response()->json(['message' => 'Post deleted successfully']);
    }
    
    public function approve(Request $request, Post $post)
    {
        if ($post->is_approved) {
            return response()->json(['message' => 'Post is already approved'], 400);
        }
        
        $post->update(['is_approved' => true]);
        
        // Notify the post owner
        $this->notificationService->sendNotification(
            $post->user_id,
            'post_approved',
            "Your post '{$post->title}' has been approved",
            ['post_id' => $post->id]
        );
        
        return new PostResource($post);
    }
    
    public function reject(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        if ($post->is_approved) {
            $post->update(['is_approved' => false]);
        }
        
        // Notify the post owner
        $this->notificationService->sendNotification(
            $post->user_id,
            'post_rejected',
            "Your post '{$post->title}' has been rejected",
            [
                'post_id' => $post->id,
                'reason' => $request->reason
            ]
        );
        
        return new PostResource($post);
    }
    
    public function like(Request $request, Post $post)
    {
        // Check if user already liked this post
        $existingLike = $post->likes()->where('user_id', $request->user()->id)->first();
        
        if ($existingLike) {
            return response()->json(['message' => 'You already liked this post'], 400);
        }
        
        // Create the like
        $like = $post->likes()->create([
            'user_id' => $request->user()->id,
        ]);
        
        // Increment like count
        $post->incrementLikeCount();
        
        // Broadcast the like event
        broadcast(new PostLiked($post, $request->user()))->toOthers();
        
        // Notify the post owner if it's not the same user
        if ($post->user_id !== $request->user()->id) {
            $this->notificationService->sendNotification(
                $post->user_id,
                'post_liked',
                "{$request->user()->name} liked your post '{$post->title}'",
                ['post_id' => $post->id, 'user_id' => $request->user()->id]
            );
        }
        
        return response()->json(['message' => 'Post liked successfully']);
    }
    
    public function unlike(Request $request, Post $post)
    {
        // Find and delete the like
        $deleted = $post->likes()->where('user_id', $request->user()->id)->delete();
        
        if (!$deleted) {
            return response()->json(['message' => 'You have not liked this post'], 400);
        }
        
        return response()->json(['message' => 'Post unliked successfully']);
    }
}
