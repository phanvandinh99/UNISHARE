<?php

namespace App\Http\Controllers\API\Moderator;

use App\Http\Controllers\API\PaginationController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Report;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModeratorDocumentController extends Controller
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
        $this->middleware('role:moderator');
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
    
    public function pendingApproval(Request $request)
    {
        $query = Document::where('is_approved', false);
        
        // Sắp xếp theo mới nhất
        $query->latest();
        
        // Phân trang
        $documents = PaginationController::paginate($query, $request);
        
        return DocumentResource::collection($documents);
    }
    
    public function approve(Request $request, Document $document)
    {
        if ($document->is_approved) {
            return response()->json(['message' => 'Tài liệu này đã được phê duyệt'], 400);
        }
        
        $document->update(['is_approved' => true]);
        
        // Thông báo cho người tải lên
        $this->notificationService->sendNotification(
            $document->user,
            'document_approved',
            "Tài liệu '{$document->title}' của bạn đã được phê duyệt",
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
        
        // Thông báo cho người tải lên
        $this->notificationService->sendNotification(
            $document->user,
            'document_rejected',
            "Tài liệu '{$document->title}' của bạn đã bị từ chối",
            [
                'document_id' => $document->id,
                'reason' => $request->reason
            ]
        );
        
        return new DocumentResource($document);
    }
    
    public function reports(Request $request)
    {
        $query = Report::where('reportable_type', Document::class)
            ->with(['reportable', 'reporter'])
            ->where('status', 'pending');
        
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
    
    public function delete(Request $request, Document $document)
    {
        // Xóa tài liệu và file liên quan
        try {
            // Lấy bản ghi tải lên file
            $fileUpload = $document->fileUpload;
            
            if ($fileUpload) {
                // Sử dụng service để xóa file
                app(FileUploadService::class)->deleteFileUpload($fileUpload);
            }
            
            // Thông báo cho người tải lên
            $this->notificationService->sendNotification(
                $document->user,
                'document_deleted',
                "Tài liệu '{$document->title}' của bạn đã bị xóa bởi người kiểm duyệt",
                ['reason' => $request->reason ?? 'Vi phạm quy định của hệ thống']
            );
            
            $document->delete();
            
            return response()->json(['message' => 'Tài liệu đã được xóa thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Không thể xóa tài liệu: ' . $e->getMessage()], 500);
        }
    }
}
