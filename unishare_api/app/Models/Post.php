<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'group_id',
        'title',
        'content',
        'is_approved',
        'like_count',
        'comment_count',
        'share_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_approved' => 'boolean',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'share_count' => 'integer',
    ];

    /**
     * Get the user who created the post.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group that the post belongs to.
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the comments for the post.
     */
    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * Get the attachments for the post.
     */
    public function attachments()
    {
        return $this->hasMany(PostAttachment::class);
    }

    /**
     * Get the likes for the post.
     */
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Get the reports for this post.
     */
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Check if the post is liked by a specific user.
     */
    public function isLikedBy(User $user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Increment the like count.
     */
    public function incrementLikeCount()
    {
        $this->increment('like_count');
    }

    /**
     * Decrement the like count.
     */
    public function decrementLikeCount()
    {
        $this->decrement('like_count');
    }

    /**
     * Increment the comment count.
     */
    public function incrementCommentCount()
    {
        $this->increment('comment_count');
    }

    /**
     * Decrement the comment count.
     */
    public function decrementCommentCount()
    {
        $this->decrement('comment_count');
    }

    /**
     * Increment the share count.
     */
    public function incrementShareCount()
    {
        $this->increment('share_count');
    }
}
