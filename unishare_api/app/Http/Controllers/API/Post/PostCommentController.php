<?php

namespace App\Http\Controllers\API\Post;

use App\Events\PostCommented;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostCommentResource;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostCommentController extends Controller
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }
    
    public function index(Post $post)
    {
        $comments = $post->comments()
            ->with('user')
            ->whereNull('parent_id')
            ->latest()
            ->paginate(15);
        
        return PostCommentResource::collection($comments);
    }
    
    public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:post_comments,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'parent_id' => $request->parent_id,
        ]);
        
        // Increment comment count
        $post->increment('comment_count');
        
        // Broadcast the comment event
        broadcast(new PostCommented($post, $comment, $request->user()))->toOthers();
        
        // Notify the post owner if it's not the same user
        if ($post->user_id !== $request->user()->id) {
            $this->notificationService->sendNotification(
                $post->user_id,
                'post_commented',
                "{$request->user()->name} commented on your post '{$post->title}'",
                ['post_id' => $post->id, 'comment_id' => $comment->id]
            );
        }
        
        // If this is a reply, notify the parent comment owner
        if ($request->parent_id) {
            $parentComment = PostComment::find($request->parent_id);
            
            if ($parentComment && $parentComment->user_id !== $request->user()->id) {
                $this->notificationService->sendNotification(
                    $parentComment->user_id,
                    'comment_replied',
                    "{$request->user()->name} replied to your comment",
                    ['post_id' => $post->id, 'comment_id' => $comment->id]
                );
            }
        }
        
        return new PostCommentResource($comment->load('user'));
    }
    
    public function show(Post $post, PostComment $comment)
    {
        // Check if the comment belongs to the post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found for this post'], 404);
        }
        
        // Load replies
        $comment->load(['user', 'replies.user']);
        
        return new PostCommentResource($comment);
    }
    
    public function update(Request $request, Post $post, PostComment $comment)
    {
        // Check if the comment belongs to the post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found for this post'], 404);
        }
        
        // Check if user has permission to update this comment
        if ($comment->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'moderator'])) {
            return response()->json(['message' => 'You do not have permission to update this comment'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $comment->update([
            'content' => $request->content,
        ]);
        
        return new PostCommentResource($comment->load('user'));
    }
    
    public function destroy(Request $request, Post $post, PostComment $comment)
    {
        // Check if the comment belongs to the post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found for this post'], 404);
        }
        
        // Check if user has permission to delete this comment
        if ($comment->user_id !== $request->user()->id && !$request->user()->can('delete any post')) {
            return response()->json(['message' => 'You do not have permission to delete this comment'], 403);
        }
        
        // Delete the comment and its replies
        $this->deleteCommentAndReplies($comment, $post);
        
        return response()->json(['message' => 'Comment deleted successfully']);
    }
    
    private function deleteCommentAndReplies(PostComment $comment, Post $post)
    {
        // Get all replies
        $replies = $comment->replies;
        
        // Delete each reply recursively
        foreach ($replies as $reply) {
            $this->deleteCommentAndReplies($reply, $post);
        }
        
        // Delete the comment
        $comment->delete();
        
        // Decrement comment count
        $post->decrement('comment_count');
    }
    
    public function like(Request $request, Post $post, PostComment $comment)
    {
        // Check if the comment belongs to the post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found for this post'], 404);
        }
        
        // Check if user already liked this comment
        $existingLike = $comment->likes()->where('user_id', $request->user()->id)->first();
        
        if ($existingLike) {
            return response()->json(['message' => 'You already liked this comment'], 400);
        }
        
        // Create the like
        $like = $comment->likes()->create([
            'user_id' => $request->user()->id,
        ]);
        
        // Increment like count
        $comment->increment('like_count');
        
        // Notify the comment owner if it's not the same user
        if ($comment->user_id !== $request->user()->id) {
            $this->notificationService->sendNotification(
                $comment->user_id,
                'comment_liked',
                "{$request->user()->name} liked your comment",
                ['post_id' => $post->id, 'comment_id' => $comment->id]
            );
        }
        
        return response()->json(['message' => 'Comment liked successfully']);
    }
    
    public function unlike(Request $request, Post $post, PostComment $comment)
    {
        // Check if the comment belongs to the post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found for this post'], 404);
        }
        
        // Find and delete the like
        $deleted = $comment->likes()->where('user_id', $request->user()->id)->delete();
        
        if (!$deleted) {
            return response()->json(['message' => 'You have not liked this comment'], 400);
        }
        
        // Decrement like count
        $comment->decrement('like_count');
        
        return response()->json(['message' => 'Comment unliked successfully']);
    }
    
    public function replies(Post $post, PostComment $comment)
    {
        // Check if the comment belongs to the post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found for this post'], 404);
        }
        
        $replies = $comment->replies()->with('user')->latest()->paginate(15);
        
        return PostCommentResource::collection($replies);
    }
}
