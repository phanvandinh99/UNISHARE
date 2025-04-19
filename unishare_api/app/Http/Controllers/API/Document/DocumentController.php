<?php

namespace App\Http\Controllers\API\Document;

use App\Events\DocumentUploaded;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;
    
    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
        $this->middleware('permission:delete any document', ['only' => ['approve', 'reject']]);
    }
    
    public function index(Request $request)
    {
        $query = Document::query();
        
        // Apply filters
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }
        
        if ($request->has('course_code')) {
            $query->where('course_code', $request->course_code);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Only show approved documents to regular users
        if (!$request->user()->hasRole(['admin', 'moderator'])) {
            $query->where('is_approved', true);
        }
        
        // Sort by latest or most downloaded
        if ($request->has('sort') && $request->sort === 'downloads') {
            $query->orderBy('download_count', 'desc');
        } else {
            $query->latest();
        }
        
        $documents = $query->paginate(15);
        
        return DocumentResource::collection($documents);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'course_code' => 'nullable|string|max:255',
            'file' => 'required|file|max:102400', // 100MB max
            'storage_type' => 'nullable|string|in:local,google_drive,minio',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if user has permission to upload documents
        if (!$request->user()->can('upload document')) {
            return response()->json(['message' => 'You do not have permission to upload documents'], 403);
        }
        
        // Upload the file
        try {
            $fileUpload = $this->fileUploadService->uploadFile(
                $request->file('file'),
                $request->user()->id,
                'document',
                null,
                $request->storage_type
            );
            
            // Create the document record
            $document = Document::create([
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $fileUpload->file_path,
                'file_name' => $fileUpload->original_filename,
                'file_type' => $fileUpload->file_type,
                'file_size' => $fileUpload->file_size,
                'file_hash' => $fileUpload->file_hash,
                'subject' => $request->subject,
                'course_code' => $request->course_code,
                'is_official' => $request->user()->hasRole('lecturer') && $request->has('is_official') ? $request->is_official : false,
                'is_approved' => $request->user()->hasRole(['admin', 'moderator', 'lecturer']) ? true : false,
                'storage_type' => $fileUpload->storage_type,
            ]);
            
            // Broadcast the document upload event
            broadcast(new DocumentUploaded($document))->toOthers();
            
            // Send notification to moderators if document needs approval
            if (!$document->is_approved) {
                $moderators = User::role('moderator')->get();
                foreach ($moderators as $moderator) {
                    $this->notificationService->sendNotification(
                        $moderator->id,
                        'document_pending_approval',
                        "New document '{$document->title}' needs approval",
                        ['document_id' => $document->id]
                    );
                }
            }
            
            return new DocumentResource($document);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function initiateChunkedUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string',
            'file_size' => 'required|integer',
            'total_chunks' => 'required|integer',
            'storage_type' => 'nullable|string|in:local,google_drive,minio',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if user has permission to upload documents
        if (!$request->user()->can('upload document')) {
            return response()->json(['message' => 'You do not have permission to upload documents'], 403);
        }
        
        try {
            $fileUpload = $this->fileUploadService->initializeUpload(
                null, // No file yet, just initializing
                $request->user()->id,
                'document',
                null,
                $request->total_chunks,
                $request->storage_type
            );
            
            return response()->json([
                'upload_id' => $fileUpload['upload_session_id'],
                'status' => 'initiated'
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
            'chunk_number' => 'required|integer',
            'total_chunks' => 'required|integer',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $fileUpload = $this->fileUploadService->processChunk(
                $request->file('chunk'),
                $request->upload_id,
                $request->chunk_number,
                $request->total_chunks
            );
            
            return response()->json([
                'upload_id' => $request->upload_id,
                'chunks_received' => $fileUpload->chunks_received,
                'status' => $fileUpload->status
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function finalizeChunkedUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'course_code' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $fileUpload = $this->fileUploadService->getFileBySessionId($request->upload_id);
            
            if (!$fileUpload || $fileUpload->status !== 'completed') {
                return response()->json(['message' => 'Upload not completed or not found'], 400);
            }
            
            // Create the document record
            $document = Document::create([
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $fileUpload->file_path,
                'file_name' => $fileUpload->original_filename,
                'file_type' => $fileUpload->file_type,
                'file_size' => $fileUpload->file_size,
                'file_hash' => $fileUpload->file_hash,
                'subject' => $request->subject,
                'course_code' => $request->course_code,
                'is_official' => $request->user()->hasRole('lecturer') && $request->has('is_official') ? $request->is_official : false,
                'is_approved' => $request->user()->hasRole(['admin', 'moderator', 'lecturer']) ? true : false,
                'storage_type' => $fileUpload->storage_type,
            ]);
            
            // Broadcast the document upload event
            broadcast(new DocumentUploaded($document))->toOthers();
            
            // Send notification to moderators if document needs approval
            if (!$document->is_approved) {
                $moderators = User::role('moderator')->get();
                foreach ($moderators as $moderator) {
                    $this->notificationService->sendNotification(
                        $moderator->id,
                        'document_pending_approval',
                        "New document '{$document->title}' needs approval",
                        ['document_id' => $document->id]
                    );
                }
            }
            
            return new DocumentResource($document);
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
            
            $this->fileUploadService->deleteFileUpload($fileUpload);
            
            return response()->json([
                'message' => 'Upload cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function show(Document $document)
    {
        // Increment view count
        $document->incrementViewCount();
        
        return new DocumentResource($document);
    }
    
    public function update(Request $request, Document $document)
    {
        // Check if user has permission to update this document
        if ($document->user_id !== $request->user()->id && !$request->user()->hasRole(['admin', 'moderator'])) {
            return response()->json(['message' => 'You do not have permission to update this document'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'course_code' => 'nullable|string|max:255',
            'is_official' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Only lecturers, admins, and moderators can mark documents as official
        if ($request->has('is_official') && !$request->user()->hasRole(['lecturer', 'admin', 'moderator'])) {
            return response()->json(['message' => 'You do not have permission to mark documents as official'], 403);
        }
        
        $document->update([
            'title' => $request->title,
            'description' => $request->description,
            'subject' => $request->subject,
            'course_code' => $request->course_code,
            'is_official' => $request->has('is_official') ? $request->is_official : $document->is_official,
        ]);
        
        return new DocumentResource($document);
    }
    
    public function destroy(Request $request, Document $document)
    {
        // Check if user has permission to delete this document
        if ($document->user_id !== $request->user()->id && !$request->user()->can('delete any document')) {
            return response()->json(['message' => 'You do not have permission to delete this document'], 403);
        }
        
        // Delete the file from storage
        try {
            // Get the file upload record
            $fileUpload = $document->fileUpload;
            
            if ($fileUpload) {
                $this->fileUploadService->deleteFileUpload($fileUpload);
            }
        } catch (\Exception $e) {
            // Log the error but continue with document deletion
            \Log::error('Failed to delete file: ' . $e->getMessage());
        }
        
        $document->delete();
        
        return response()->json(['message' => 'Document deleted successfully']);
    }
    
    public function download(Request $request, Document $document)
    {
        // Increment download count
        $document->incrementDownloadCount();
        
        // Get the file from storage
        try {
            $fileUpload = $document->fileUpload;
            
            if (!$fileUpload) {
                return response()->json(['message' => 'File not found'], 404);
            }
            
            // Xử lý tải xuống dựa trên loại storage
            switch ($fileUpload->storage_type) {
                case 'google_drive':
                    // Google Drive không hỗ trợ tải xuống trực tiếp, trả về lỗi
                    return response()->json(['message' => 'Direct download from Google Drive is not supported. Please use the Google Drive interface.'], 400);
                    
                case 'minio':
                    // Đối với MinIO, trả về URL có thời hạn
                    $url = $this->fileUploadService->getFileUrl($fileUpload);
                    return response()->json([
                        'download_url' => $url,
                        'filename' => $document->file_name,
                    ]);
                    
                default:
                    // Đối với local storage, tải xuống trực tiếp
                    $filePath = storage_path('app/' . $fileUpload->file_path);
                    
                    if (!file_exists($filePath)) {
                        return response()->json(['message' => 'File not found on server'], 404);
                    }
                    
                    return response()->download($filePath, $document->file_name, [
                        'Content-Type' => $document->file_type,
                    ]);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to download file: ' . $e->getMessage()], 500);
        }
    }
    
    public function approve(Request $request, Document $document)
    {
        if ($document->is_approved) {
            return response()->json(['message' => 'Document is already approved'], 400);
        }
        
        $document->update(['is_approved' => true]);
        
        // Notify the document owner
        $this->notificationService->sendNotification(
            $document->user_id,
            'document_approved',
            "Your document '{$document->title}' has been approved",
            ['document_id' => $document->id]
        );
        
        return new DocumentResource($document);
    }
    
    public function reject(Request $request, Document $document)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        if ($document->is_approved) {
            $document->update(['is_approved' => false]);
        }
        
        // Notify the document owner
        $this->notificationService->sendNotification(
            $document->user_id,
            'document_rejected',
            "Your document '{$document->title}' has been rejected",
            [
                'document_id' => $document->id,
                'reason' => $request->reason
            ]
        );
        
        return new DocumentResource($document);
    }
}
