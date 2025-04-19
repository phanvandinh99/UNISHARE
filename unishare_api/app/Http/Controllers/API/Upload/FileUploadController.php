<?php

namespace App\Http\Controllers\API\Upload;

use App\Http\Controllers\Controller;
use App\Models\FileUpload;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    protected $fileUploadService;
    
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->middleware('auth:sanctum');
    }
    
    public function initializeUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string',
            'file_size' => 'required|integer',
            'uploadable_type' => 'required|string|in:document,post_attachment,group_cover,message_attachment,profile_picture',
            'uploadable_id' => 'nullable|integer',
            'chunks_total' => 'required|integer|min:1',
            'storage_type' => 'nullable|string|in:local,google_drive,minio',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $result = $this->fileUploadService->initializeUpload(
                new \Illuminate\Http\UploadedFile(
                    tempnam(sys_get_temp_dir(), 'upload'),
                    $request->file_name,
                    null,
                    null,
                    true
                ),
                $request->user()->id,
                $request->uploadable_type,
                $request->uploadable_id,
                $request->chunks_total,
                $request->storage_type
            );
            
            return response()->json([
                'upload_id' => $result['upload_session_id'],
                'is_duplicate' => $result['is_duplicate'],
                'file_upload' => $result['file_upload'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function uploadChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
            'chunks_total' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $fileUpload = $this->fileUploadService->processChunk(
                $request->file('chunk'),
                $request->upload_id,
                $request->chunk_index,
                $request->chunks_total
            );
            
            return response()->json([
                'upload_id' => $request->upload_id,
                'chunks_received' => $fileUpload->chunks_received,
                'status' => $fileUpload->status,
                'file_upload' => $fileUpload,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function checkUploadStatus(Request $request, $uploadId)
    {
        try {
            $fileUpload = $this->fileUploadService->getFileBySessionId($uploadId);
            
            if (!$fileUpload) {
                return response()->json(['message' => 'Upload session not found'], 404);
            }
            
            return response()->json([
                'upload_id' => $uploadId,
                'status' => $fileUpload->status,
                'chunks_received' => $fileUpload->chunks_received,
                'chunks_total' => $fileUpload->chunks_total,
                'file_upload' => $fileUpload,
                'file_url' => $fileUpload->status === 'completed' ? $this->fileUploadService->getFileUrl($fileUpload) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function handleInterruptedUpload(Request $request, $uploadId)
    {
        try {
            $result = $this->fileUploadService->handleInterruptedUpload($uploadId);
            
            if (!$result) {
                return response()->json(['message' => 'Upload session not found'], 404);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function cancelUpload(Request $request, $uploadId)
    {
        try {
            $fileUpload = $this->fileUploadService->getFileBySessionId($uploadId);
            
            if (!$fileUpload) {
                return response()->json(['message' => 'Upload session not found'], 404);
            }
            
            // Check if the user owns this upload
            if ($fileUpload->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'moderator'])) {
                return response()->json(['message' => 'You do not have permission to cancel this upload'], 403);
            }
            
            $this->fileUploadService->deleteFileUpload($fileUpload);
            
            return response()->json(['message' => 'Upload cancelled successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function getFileUrl(Request $request, $fileId)
    {
        try {
            $fileUpload = FileUpload::findOrFail($fileId);
            
            // Check if the user has permission to access this file
            if ($fileUpload->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'moderator'])) {
                // Kiểm tra xem file có thuộc về đối tượng công khai không
                $isPublic = false;
                
                if ($fileUpload->uploadable_type === 'document') {
                    $document = $fileUpload->uploadable;
                    if ($document && $document->is_approved) {
                        $isPublic = true;
                    }
                } elseif ($fileUpload->uploadable_type === 'post_attachment') {
                    $post = $fileUpload->uploadable->post;
                    if ($post && !$post->is_private) {
                        $isPublic = true;
                    }
                }
                
                if (!$isPublic) {
                    return response()->json(['message' => 'You do not have permission to access this file'], 403);
                }
            }
            
            $url = $this->fileUploadService->getFileUrl($fileUpload);
            
            if (!$url) {
                return response()->json(['message' => 'File URL not available'], 404);
            }
            
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
