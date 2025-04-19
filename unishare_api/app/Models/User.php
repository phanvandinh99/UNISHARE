<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'university',
        'department',
        'student_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    /**
     * Get the documents uploaded by the user.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the posts created by the user.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the groups created by the user.
     */
    public function createdGroups()
    {
        return $this->hasMany(Group::class, 'creator_id');
    }

    /**
     * Get the groups the user is a member of.
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    /**
     * Get the messages sent by the user.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the chats the user is participating in.
     */
    public function chats()
    {
        return $this->belongsToMany(Chat::class, 'chat_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Get the file uploads by the user.
     */
    public function fileUploads()
    {
        return $this->hasMany(FileUpload::class);
    }

    /**
     * Get the document ratings by the user.
     */
    public function documentRatings()
    {
        return $this->hasMany(DocumentRating::class);
    }

    /**
     * Get the document comments by the user.
     */
    public function documentComments()
    {
        return $this->hasMany(DocumentComment::class);
    }

    /**
     * Get the post comments by the user.
     */
    public function postComments()
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * Get the likes by the user.
     */
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Check if the user is a student.
     */
    public function isStudent()
    {
        return $this->hasRole('student');
    }

    /**
     * Check if the user is a lecturer.
     */
    public function isLecturer()
    {
        return $this->hasRole('lecturer');
    }

    /**
     * Check if the user is a moderator.
     */
    public function isModerator()
    {
        return $this->hasRole('moderator');
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }
}