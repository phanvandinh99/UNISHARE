<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'google_drive_id',
        'minio_key',
        'external_url',
        'storage_type',
        'status',
        'upload_session_id',
        'chunks_total',
        'chunks_received',
        'error_message',
        'uploadable_id',
        'uploadable_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'chunks_total' => 'integer',
        'chunks_received' => 'integer',
    ];

    /**
     * Get the user who uploaded the file.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent uploadable model.
     */
    public function uploadable()
    {
        return $this->morphTo();
    }

    /**
     * Check if the upload is complete.
     */
    public function isComplete()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the upload is in progress.
     */
    public function isInProgress()
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }

    /**
     * Check if the upload has failed.
     */
    public function hasFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Check if all chunks have been received.
     */
    public function allChunksReceived()
    {
        return $this->chunks_total === $this->chunks_received;
    }
}
