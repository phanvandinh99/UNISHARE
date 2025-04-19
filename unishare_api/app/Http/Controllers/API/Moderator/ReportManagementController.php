<?php

namespace App\Http\Controllers\API\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Document;
use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use App\Events\ReportResolved;
use App\Notifications\ReportResolved as ReportResolvedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:moderator,admin');
    }

    /**
     * Lấy danh sách báo cáo
     */
    public function index(Request $request)
    {
        $query = Report::query();
        
        // Lọc theo trạng thái
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Lọc theo loại
        if ($request->has('type')) {
            $reportableType = $this->getReportableModel($request->type);
            if ($reportableType) {
                $query->where('reportable_type', $reportableType);
            }
        }
        
        // Tìm kiếm
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('details', 'like', "%{$search}%");
            });
        }
        
        // Sắp xếp
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Phân trang
        $perPage = $request->input('per_page', 15);
        $reports = $query->with(['user', 'reportable'])->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Lấy chi tiết báo cáo
     */
    public function show($id)
    {
        $report = Report::with(['user', 'reportable'])->findOrFail($id);
        
        // Lấy thông tin bổ sung về đối tượng báo cáo
        $reportableInfo = $this->getReportableInfo($report->reportable);
        
        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'reportable_info' => $reportableInfo,
            ],
        ]);
    }

    /**
     * Xử lý báo cáo
     */
    public function resolve(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        
        // Kiểm tra xem báo cáo đã được xử lý chưa
        if ($report->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Báo cáo này đã được xử lý',
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:resolve,reject,delete,ban',
            'resolution_note' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Xử lý báo cáo dựa trên hành động
        switch ($request->action) {
            case 'resolve':
                // Đánh dấu báo cáo là đã xử lý
                $report->status = 'resolved';
                $report->resolved_by = auth()->id();
                $report->resolution_note = $request->resolution_note;
                $report->resolved_at = now();
                $report->save();
                break;
                
            case 'reject':
                // Từ chối báo cáo
                $report->status = 'rejected';
                $report->resolved_by = auth()->id();
                $report->resolution_note = $request->resolution_note;
                $report->resolved_at = now();
                $report->save();
                break;
                
            case 'delete':
                // Xóa đối tượng báo cáo
                $reportable = $report->reportable;
                
                if ($reportable) {
                    // Xóa đối tượng báo cáo
                    $reportable->delete();
                    
                    // Đánh dấu tất cả các báo cáo liên quan là đã xử lý
                    Report::where('reportable_type', get_class($reportable))
                        ->where('reportable_id', $reportable->id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'resolved',
                            'resolved_by' => auth()->id(),
                            'resolution_note' => $request->resolution_note,
                            'resolved_at' => now(),
                        ]);
                } else {
                    // Đối tượng báo cáo không tồn tại
                    $report->status = 'resolved';
                    $report->resolved_by = auth()->id();
                    $report->resolution_note = $request->resolution_note . ' (Đối tượng không tồn tại)';
                    $report->resolved_at = now();
                    $report->save();
                }
                break;
                
            case 'ban':
                // Cấm người dùng (chỉ áp dụng cho báo cáo người dùng)
                if ($report->reportable_type === User::class) {
                    $user = $report->reportable;
                    
                    if ($user) {
                        // Cấm người dùng
                        $user->is_active = false;
                        $user->ban_reason = $request->resolution_note;
                        $user->banned_at = now();
                        $user->save();
                        
                        // Thu hồi tất cả token
                        $user->tokens()->delete();
                        
                        // Đánh dấu tất cả các báo cáo liên quan là đã xử lý
                        Report::where('reportable_type', User::class)
                            ->where('reportable_id', $user->id)
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'resolved',
                                'resolved_by' => auth()->id(),
                                'resolution_note' => $request->resolution_note,
                                'resolved_at' => now(),
                            ]);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hành động cấm chỉ áp dụng cho báo cáo người dùng',
                    ], 422);
                }
                break;
        }
        
        // Gửi thông báo cho người báo cáo
        $reporter = $report->user;
        if ($reporter) {
            $reporter->notify(new ReportResolvedNotification($report));
        }
        
        // Gửi sự kiện báo cáo đã được xử lý
        broadcast(new ReportResolved($report))->toOthers();
        
        return response()->json([
            'success' => true,
            'message' => 'Báo cáo đã được xử lý thành công',
            'data' => $report,
        ]);
    }

    /**
     * Lấy thống kê báo cáo
     */
    public function statistics()
    {
        // Tổng số báo cáo
        $totalReports = Report::count();
        
        // Báo cáo theo trạng thái
        $reportsByStatus = Report::select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
        
        // Báo cáo theo loại
        $reportsByType = Report::select('reportable_type', \DB::raw('count(*) as count'))
            ->groupBy('reportable_type')
            ->get()
            ->map(function ($item) {
                $type = class_basename($item->reportable_type);
                return [
                    'type' => $type,
                    'count' => $item->count,
                ];
            });
        
        // Báo cáo mới trong 7 ngày qua
        $newReports = Report::where('created_at', '>=', \Carbon\Carbon::now()->subDays(7))->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalReports,
                'by_status' => $reportsByStatus,
                'by_type' => $reportsByType,
                'new_reports' => $newReports,
            ],
        ]);
    }

    /**
     * Lấy model tương ứng với loại báo cáo
     */
    private function getReportableModel($type)
    {
        switch ($type) {
            case 'document':
                return Document::class;
            case 'post':
                return Post::class;
            case 'comment':
                return Comment::class;
            case 'user':
                return User::class;
            default:
                return null;
        }
    }

    /**
     * Lấy thông tin bổ sung về đối tượng báo cáo
     */
    private function getReportableInfo($reportable)
    {
        if (!$reportable) {
            return [
                'type' => 'unknown',
                'title' => 'Đối tượng không tồn tại',
                'details' => 'Đối tượng báo cáo đã bị xóa hoặc không tồn tại',
            ];
        }
        
        $type = class_basename(get_class($reportable));
        
        switch ($type) {
            case 'Document':
                return [
                    'type' => 'document',
                    'title' => $reportable->title,
                    'details' => $reportable->description,
                    'user' => $reportable->user ? $reportable->user->name : 'Người dùng đã xóa',
                    'created_at' => $reportable->created_at->format('Y-m-d H:i:s'),
                    'url' => "/documents/{$reportable->id}",
                ];
                
            case 'Post':
                return [
                    'type' => 'post',
                    'title' => $reportable->title,
                    'details' => $reportable->content,
                    'user' => $reportable->user ? $reportable->user->name : 'Người dùng đã xóa',
                    'created_at' => $reportable->created_at->format('Y-m-d H:i:s'),
                    'url' => "/posts/{$reportable->id}",
                ];
                
            case 'Comment':
                return [
                    'type' => 'comment',
                    'title' => 'Bình luận',
                    'details' => $reportable->content,
                    'user' => $reportable->user ? $reportable->user->name : 'Người dùng đã xóa',
                    'created_at' => $reportable->created_at->format('Y-m-d H:i:s'),
                    'url' => "/posts/{$reportable->post_id}#comment-{$reportable->id}",
                ];
                
            case 'User':
                return [
                    'type' => 'user',
                    'title' => $reportable->name,
                    'details' => "Email: {$reportable->email}, Vai trò: {$reportable->role}",
                    'created_at' => $reportable->created_at->format('Y-m-d H:i:s'),
                    'url' => "/users/{$reportable->id}",
                ];
                
            default:
                return [
                    'type' => 'unknown',
                    'title' => 'Loại không xác định',
                    'details' => 'Không thể hiển thị thông tin chi tiết',
                ];
        }
    }
}
