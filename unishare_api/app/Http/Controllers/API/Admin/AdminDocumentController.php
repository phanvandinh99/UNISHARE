<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\PaginationController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Report;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminDocumentController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;
    
    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }
    
    public function index(Request $request)
    {
        $query = Document::query();
        
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
        
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }
        
        if ($request->has('is_official')) {
            $query->where('is_official', $request->is_official);
        }
        
        // Sắp xếp theo mới nhất
        $query->latest();
        
        // Phân trang
        $documents = PaginationController::paginate($query, $request);
        
        return DocumentResource::collection($documents);
    }
    
    public function show(Document $document)
    {
        return new DocumentResource($document);
    }
    
    public function update(Request $request, Document $document)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'course_code' => 'nullable|string|max:255',
            'is_official' => 'nullable|boolean',
            'is_approved' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $document->update([
            'title' => $request->title,
            'description' => $request->description,
            'subject' => $request->subject,
            'course_code' => $request->course_code,
            'is_official' => $request->has('is_official') ? $request->is_official : $document->is_official,
            'is_approved' => $request->has('is_approved') ? $request->is_approved : $document->is_approved,
        ]);
        
        // Thông báo cho người tải lên nếu trạng thái phê duyệt thay đổi
        if ($request->has('is_approved') && $request->is_approved != $document->getOriginal('is_approved')) {
            $this->notificationService->sendNotification(
                $document->user,
                $request->is_approved ? 'document_approved' : 'document_rejected',
                $request->is_approved 
                    ? "Tài liệu '{$document->title}' của bạn đã được phê duyệt" 
                    : "Tài liệu '{$document->title}' của bạn đã bị từ chối",
                ['document_id' => $document->id]
            );
        }
        
        return new DocumentResource($document);
    }
    
    public function destroy(Request $request, Document $document)
    {
        // Xóa tài liệu và file liên quan
        try {
            // Lấy bản ghi tải lên file
            $fileUpload = $document->fileUpload;
            
            if ($fileUpload) {
                $this->fileUploadService->deleteFileUpload($fileUpload);
            }
            
            // Thông báo cho người tải lên
            $this->notificationService->sendNotification(
                $document->user,
                'document_deleted',
                "Tài liệu '{$document->title}' của bạn đã bị xóa bởi quản trị viên",
                ['reason' => $request->reason ?? 'Vi phạm quy định của hệ thống']
            );
            
            $document->delete();
            
            return response()->json(['message' => 'Tài liệu đã được xóa thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Không thể xóa tài liệu: ' . $e->getMessage()], 500);
        }
    }
    
    public function reports(Request $request)
    {
        $query = Report::where('reportable_type', Document::class)
            ->with(['reportable', 'reporter', 'resolver']);
        
        // Áp dụng bộ lọc
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Sắp xếp theo mới nhất
        $query->latest();
        
        // Phân trang
        $reports = PaginationController::paginate($query, $request);
        
        return response()->json($reports);
    }
    
    public function resolveReport(Request $request, Report $report)
    {
        $validator = Validator::make($request->all(), [
            'resolution_note' => 'nullable|string',
            'action' => 'required|in:resolve,reject',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        if ($report->status !== 'pending') {
            return response()->json(['message' => 'Báo cáo này đã được xử lý'], 400);
        }
        
        if ($request->action === 'resolve') {
            $report->resolve($request->user(), $request->resolution_note);
            
            // Nếu báo cáo liên quan đến tài liệu, có thể thực hiện hành động bổ sung
            if ($report->reportable_type === Document::class) {
                $document = Document::find($report->reportable_id);
                
                if ($document) {
                    // Ví dụ: Đánh dấu tài liệu là không được phê duyệt
                    $document->update(['is_approved' => false]);
                    
                    // Thông báo cho người tải lên
                    $this->notificationService->sendNotification(
                        $document->user,
                        'document_reported_action',
                        "Tài liệu '{$document->title}' của bạn đã bị đánh dấu là không được phê duyệt do vi phạm",
                        ['document_id' => $document->id]
                    );
                }
            }
        } else {
            $report->reject($request->user(), $request->resolution_note);
        }
        
        // Thông báo cho người báo cáo
        $this->notificationService->sendNotification(
            $report->reporter,
            'report_processed',
            "Báo cáo của bạn đã được xử lý",
            ['report_id' => $report->id]
        );
        
        return response()->json(['message' => 'Báo cáo đã được xử lý thành công']);
    }
    
    public function statistics(Request $request)
    {
        $stats = [
            'total_documents' => Document::count(),
            'approved_documents' => Document::where('is_approved', true)->count(),
            'pending_documents' => Document::where('is_approved', false)->count(),
            'official_documents' => Document::where('is_official', true)->count(),
            'total_downloads' => Document::sum('download_count'),
            'total_views' => Document::sum('view_count'),
            'documents_by_subject' => Document::selectRaw('subject, count(*) as count')
                ->groupBy('subject')
                ->get(),
            'documents_by_course' => Document::selectRaw('course_code, count(*) as count')
                ->groupBy('course_code')
                ->get(),
        ];
        
        return response()->json($stats);
    }
}
