<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chat_id',
        'user_id',
        'is_admin',
        'joined_at',
        'left_at',
        'last_read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    /**
     * Get the chat that owns the participant.
     */
    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the user that owns the participant.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active participants.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    /**
     * Check if the participant is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return is_null($this->left_at);
    }

    /**
     * Mark the participant as having left the chat.
     *
     * @return bool
     */
    public function leave()
    {
        return $this->update(['left_at' => now()]);
    }

    /**
     * Mark the participant as having read all messages up to now.
     *
     * @return bool
     */
    public function markAsRead()
    {
        return $this->update(['last_read_at' => now()]);
    }

    /**
     * Get the number of unread messages for the participant.
     *
     * @return int
     */
    public function unreadCount()
    {
        if (!$this->last_read_at) {
            return $this->chat->messages()->count();
        }

        return $this->chat->messages()
            ->where('created_at', '>', $this->last_read_at)
            ->where('user_id', '!=', $this->user_id)
            ->count();
    }
}
