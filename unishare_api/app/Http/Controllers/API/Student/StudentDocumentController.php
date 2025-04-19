<?php

namespace App\Http\Controllers\API\Student;

use App\Events\DocumentUploaded;
use App\Http\Controllers\API\PaginationController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentDocumentController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;
    
    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
        $this->middleware('role:student');
    }
    
    public function index(Request $request)
    {
        $query = Document::query();
        
        // Chỉ hiển thị tài liệu đã được phê duyệt
        $query->where('is_approved', true);
        
        // Áp dụng bộ lọc
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }
        
        if ($request->has('course_code')) {
            $query->where('course_code', $request->course_code);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Sắp xếp theo mới nhất hoặc lượt tải nhiều nhất
        if ($request->has('sort') && $request->sort === 'downloads') {
            $query->orderBy('download_count', 'desc');
        } else {
            $query->latest();
        }
        
        // Phân trang
        $documents = PaginationController::paginate($query, $request);
        
        return DocumentResource::collection($documents);
    }
    
    public function myDocuments(Request $request)
    {
        $query = Document::where('user_id', $request->user()->id);
        
        // Sắp xếp theo mới nhất
        $query->latest();
        
        // Phân trang
        $documents = PaginationController::paginate($query, $request);
        
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
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Upload file
        try {
            $fileUpload = $this->fileUploadService->uploadFile(
                $request->file('file'),
                $request->user()->id,
                'document'
            );
            
            // Tạo bản ghi tài liệu
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
                'is_official' => false, // Sinh viên không thể tạo tài liệu chính thức
                'is_approved' => false, // Cần được phê duyệt
            ]);
            
            // Phát sóng sự kiện tải lên tài liệu
            broadcast(new DocumentUploaded($document))->toOthers();
            
            // Gửi thông báo cho người kiểm duyệt
            $moderators = User::role('moderator')->get();
            foreach ($moderators as $moderator) {
                $this->notificationService->sendNotification(
                    $moderator,
                    'document_pending_approval',
                    "Tài liệu mới '{$document->title}' cần được phê duyệt",
                    ['document_id' => $document->id]
                );
            }
            
            return new DocumentResource($document);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
    public function show(Document $document)
    {
        // Kiểm tra xem tài liệu có được phê duyệt không
        if (!$document->is_approved && $document->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Tài liệu này chưa được phê duyệt'], 403);
        }
        
        // Tăng lượt xem
        $document->incrementViewCount();
        
        return new DocumentResource($document);
    }
    
    public function update(Request $request, Document $document)
    {
        // Kiểm tra xem người dùng có quyền cập nhật tài liệu này không
        if ($document->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật tài liệu này'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'course_code' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $document->update([
            'title' => $request->title,
            'description' => $request->description,
            'subject' => $request->subject,
            'course_code' => $request->course_code,
            'is_approved' => false, // Cần được phê duyệt lại sau khi cập nhật
        ]);
        
        // Gửi thông báo cho người kiểm duyệt
        $moderators = User::role('moderator')->get();
        foreach ($moderators as $moderator) {
            $this->notificationService->sendNotification(
                $moderator,
                'document_updated_approval',
                "Tài liệu '{$document->title}' đã được cập nhật và cần phê duyệt lại",
                ['document_id' => $document->id]
            );
        }
        
        return new DocumentResource($document);
    }
    
    public function destroy(Request $request, Document $document)
    {
        // Kiểm tra xem người dùng có quyền xóa tài liệu này không
        if ($document->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa tài liệu này'], 403);
        }
        
        // Xóa file từ bộ nhớ
        try {
            // Lấy bản ghi tải lên file
            $fileUpload = $document->fileUpload;
            
            if ($fileUpload) {
                $this->fileUploadService->deleteFileUpload($fileUpload);
            }
        } catch (\Exception $e) {
            // Ghi log lỗi nhưng vẫn tiếp tục xóa tài liệu
            \Log::error('Không thể xóa file: ' . $e->getMessage());
        }
        
        $document->delete();
        
        return response()->json(['message' => 'Tài liệu đã được xóa thành công']);
    }
    
    public function download(Request $request, Document $document)
    {
        // Kiểm tra xem tài liệu có được phê duyệt không
        if (!$document->is_approved && $document->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tài liệu này chưa được phê duyệt'], 403);
        }
        
        // Tăng lượt tải
        $document->incrementDownloadCount();
        
        // Lấy file từ bộ nhớ
        try {
            $fileUpload = $document->fileUpload;
            
            if (!$fileUpload) {
                return response()->json(['message' => 'Không tìm thấy file'], 404);
            }
            
            $filePath = storage_path('app/' . $fileUpload->file_path);
            
            if (!file_exists($filePath)) {
                return response()->json(['message' => 'Không tìm thấy file trên máy chủ'], 404);
            }
            
            return response()->download($filePath, $document->file_name, [
                'Content-Type' => $document->file_type,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Không thể tải file: ' . $e->getMessage()], 500);
        }
    }
    
    public function report(Request $request, Document $document)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Tạo báo cáo
        $report = $document->reports()->create([
            'reporter_id' => $request->user()->id,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);
        
        // Gửi thông báo cho người kiểm duyệt
        $moderators = User::role('moderator')->get();
        foreach ($moderators as $moderator) {
            $this->notificationService->sendNotification(
                $moderator,
                'document_reported',
                "Tài liệu '{$document->title}' đã bị báo cáo",
                ['document_id' => $document->id, 'report_id' => $report->id]
            );
        }
        
        return response()->json(['message' => 'Báo cáo đã được gửi thành công']);
    }
}
