<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'created_by',
        'last_message_at',
        'is_group',
        'description',
        'avatar',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_group' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the user that created the chat.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the messages for the chat.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the participants for the chat.
     */
    public function participants()
    {
        return $this->hasMany(ChatParticipant::class);
    }

    /**
     * Get the active participants for the chat.
     */
    public function activeParticipants()
    {
        return $this->participants()->active();
    }

    /**
     * Get the users that are participants in the chat.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_participants')
            ->withPivot('is_admin', 'joined_at', 'left_at', 'last_read_at')
            ->withTimestamps();
    }

    /**
     * Get the active users that are participants in the chat.
     */
    public function activeUsers()
    {
        return $this->belongsToMany(User::class, 'chat_participants')
            ->wherePivotNull('left_at')
            ->withPivot('is_admin', 'joined_at', 'last_read_at')
            ->withTimestamps();
    }

    /**
     * Check if a user is a participant in the chat.
     *
     * @param  int  $userId
     * @return bool
     */
    public function hasParticipant($userId)
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user is an active participant in the chat.
     *
     * @param  int  $userId
     * @return bool
     */
    public function hasActiveParticipant($userId)
    {
        return $this->activeParticipants()->where('user_id', $userId)->exists();
    }

    /**
     * Add a user as a participant to the chat.
     *
     * @param  int  $userId
     * @param  bool  $isAdmin
     * @return \App\Models\ChatParticipant
     */
    public function addParticipant($userId, $isAdmin = false)
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        if ($participant) {
            // If the participant exists but left, update the left_at to null
            if ($participant->left_at) {
                $participant->update(['left_at' => null, 'is_admin' => $isAdmin]);
            }
            return $participant;
        }

        return $this->participants()->create([
            'user_id' => $userId,
            'is_admin' => $isAdmin,
            'joined_at' => now(),
        ]);
    }

    /**
     * Remove a user as a participant from the chat.
     *
     * @param  int  $userId
     * @return bool
     */
    public function removeParticipant($userId)
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        if (!$participant) {
            return false;
        }

        return $participant->leave();
    }

    /**
     * Get the last message for the chat.
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    /**
     * Get the unread messages count for a user.
     *
     * @param  int  $userId
     * @return int
     */
    public function unreadCount($userId)
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        if (!$participant) {
            return 0;
        }

        return $participant->unreadCount();
    }
}
