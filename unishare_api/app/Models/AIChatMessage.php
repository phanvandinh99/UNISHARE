<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIChatMessage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_chat_id',
        'role',
        'content',
    ];

    /**
     * Get the chat that the message belongs to.
     */
    public function chat()
    {
        return $this->belongsTo(AIChat::class, 'ai_chat_id');
    }
}
