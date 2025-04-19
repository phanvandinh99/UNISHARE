<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'avatar',
        'cover_image',
        'creator_id',
        'course_code',
        'university',
        'department',
        'type',
        'requires_approval',
        'member_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requires_approval' => 'boolean',
        'member_count' => 'integer',
    ];

    /**
     * Get the creator of the group.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the members of the group.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    /**
     * Get the posts in the group.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the chat for the group.
     */
    public function chat()
    {
        return $this->hasOne(Chat::class);
    }

    /**
     * Check if a user is a member of the group.
     */
    public function isMember(User $user)
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * Check if a user is an admin of the group.
     */
    public function isAdmin(User $user)
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }

    /**
     * Check if a user is a moderator of the group.
     */
    public function isModerator(User $user)
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['admin', 'moderator'])
            ->exists();
    }

    /**
     * Get the pending membership requests.
     */
    public function pendingRequests()
    {
        return $this->members()
            ->wherePivot('status', 'pending');
    }
}
