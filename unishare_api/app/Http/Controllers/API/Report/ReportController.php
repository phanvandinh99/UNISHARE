<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Document;
use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use App\Events\ReportCreated;
use App\Events\ReportResolved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Tạo báo cáo mới
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reportable_type' => 'required|string|in:document,post,comment,user',
            'reportable_id' => 'required|integer',
            'reason' => 'required|string|max:500',
            'details' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Kiểm tra xem đối tượng báo cáo có tồn tại không
        $reportableType = $this->getReportableModel($request->reportable_type);
        $reportable = $reportableType::find($request->reportable_id);

        if (!$reportable) {
            return response()->json([
                'success' => false,
                'message' => 'Đối tượng báo cáo không tồn tại',
            ], 404);
        }

        // Kiểm tra xem người dùng đã báo cáo đối tượng này chưa
        $existingReport = Report::where('user_id', auth()->id())
            ->where('reportable_type', get_class($reportable))
            ->where('reportable_id', $reportable->id)
            ->where('status', 'pending')
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã báo cáo đối tượng này và báo cáo đang được xử lý',
            ], 422);
        }

        // Tạo báo cáo mới
        $report = new Report();
        $report->user_id = auth()->id();
        $report->reportable_type = get_class($reportable);
        $report->reportable_id = $reportable->id;
        $report->reason = $request->reason;
        $report->details = $request->details;
        $report->status = 'pending';
        $report->save();

        // Gửi sự kiện báo cáo mới
        broadcast(new ReportCreated($report))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo đã được gửi thành công',
            'data' => $report,
        ], 201);
    }

    /**
     * Lấy danh sách báo cáo của người dùng hiện tại
     */
    public function index(Request $request)
    {
        $query = Report::where('user_id', auth()->id());
        
        // Lọc theo trạng thái
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Lọc theo loại
        if ($request->has('type')) {
            $reportableType = $this->getReportableModel($request->type);
            $query->where('reportable_type', $reportableType);
        }
        
        // Sắp xếp
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Phân trang
        $perPage = $request->input('per_page', 15);
        $reports = $query->with('reportable')->paginate($perPage);
        
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
        $report = Report::where('id', $id)
            ->where('user_id', auth()->id())
            ->with('reportable')
            ->firstOrFail();
        
        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Cập nhật báo cáo
     */
    public function update(Request $request, $id)
    {
        $report = Report::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'details' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $report->reason = $request->reason;
        $report->details = $request->details;
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo đã được cập nhật thành công',
            'data' => $report,
        ]);
    }

    /**
     * Hủy báo cáo
     */
    public function cancel($id)
    {
        $report = Report::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();
        
        $report->status = 'cancelled';
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo đã được hủy thành công',
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
}
