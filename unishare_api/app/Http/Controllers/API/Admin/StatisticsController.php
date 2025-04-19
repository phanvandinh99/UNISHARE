<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Report;
use App\Models\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Lấy thống kê tổng quan
     */
    public function overview()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalDocuments = Document::count();
        $totalPosts = Post::count();
        $totalGroups = Group::count();
        $totalMessages = Message::count();
        $pendingReports = Report::where('status', 'pending')->count();
        $totalStorage = FileUpload::sum('size');
        
        // Người dùng mới trong 7 ngày qua
        $newUsers = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        
        // Tài liệu mới trong 7 ngày qua
        $newDocuments = Document::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        
        // Bài đăng mới trong 7 ngày qua
        $newPosts = Post::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'new' => $newUsers,
                    'inactive' => $totalUsers - $activeUsers,
                ],
                'content' => [
                    'documents' => [
                        'total' => $totalDocuments,
                        'new' => $newDocuments,
                    ],
                    'posts' => [
                        'total' => $totalPosts,
                        'new' => $newPosts,
                    ],
                    'groups' => $totalGroups,
                    'messages' => $totalMessages,
                ],
                'reports' => [
                    'pending' => $pendingReports,
                ],
                'storage' => [
                    'total' => $totalStorage,
                    'formatted' => $this->formatBytes($totalStorage),
                ],
            ],
        ]);
    }

    /**
     * Lấy thống kê người dùng
     */
    public function users(Request $request)
    {
        $period = $request->input('period', 'month');
        $startDate = $this->getStartDate($period);
        
        // Thống kê người dùng theo vai trò
        $usersByRole = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->pluck('count', 'role')
            ->toArray();
        
        // Thống kê người dùng mới theo thời gian
        $newUsers = $this->getTimeSeriesData(
            User::where('created_at', '>=', $startDate),
            $period,
            'created_at'
        );
        
        // Thống kê người dùng hoạt động
        $activeUsers = User::where('last_login_at', '>=', Carbon::now()->subDays(30))->count();
        $inactiveUsers = User::where('last_login_at', '<', Carbon::now()->subDays(30))
            ->orWhereNull('last_login_at')
            ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => User::count(),
                'by_role' => $usersByRole,
                'new_users' => $newUsers,
                'active_status' => [
                    'active' => $activeUsers,
                    'inactive' => $inactiveUsers,
                ],
            ],
        ]);
    }

    /**
     * Lấy thống kê tài liệu
     */
    public function documents(Request $request)
    {
        $period = $request->input('period', 'month');
        $startDate = $this->getStartDate($period);
        
        // Thống kê tài liệu theo danh mục
        $documentsByCategory = Document::select('category_id', DB::raw('count(*) as count'))
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category ? $item->category->name : 'Không có danh mục',
                    'count' => $item->count,
                ];
            });
        
        // Thống kê tài liệu mới theo thời gian
        $newDocuments = $this->getTimeSeriesData(
            Document::where('created_at', '>=', $startDate),
            $period,
            'created_at'
        );
        
        // Thống kê tài liệu theo trạng thái
        $documentsByStatus = Document::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
        
        // Thống kê tài liệu theo người tạo
        $topContributors = Document::select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => $item->user ? $item->user->name : 'Người dùng đã xóa',
                    'count' => $item->count,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Document::count(),
                'by_category' => $documentsByCategory,
                'by_status' => $documentsByStatus,
                'new_documents' => $newDocuments,
                'top_contributors' => $topContributors,
            ],
        ]);
    }

    /**
     * Lấy thống kê bài đăng
     */
    public function posts(Request $request)
    {
        $period = $request->input('period', 'month');
        $startDate = $this->getStartDate($period);
        
        // Thống kê bài đăng theo danh mục
        $postsByCategory = Post::select('category_id', DB::raw('count(*) as count'))
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category ? $item->category->name : 'Không có danh mục',
                    'count' => $item->count,
                ];
            });
        
        // Thống kê bài đăng mới theo thời gian
        $newPosts = $this->getTimeSeriesData(
            Post::where('created_at', '>=', $startDate),
            $period,
            'created_at'
        );
        
        // Thống kê bài đăng theo tương tác
        $postsWithMostLikes = Post::withCount('likes')
            ->orderBy('likes_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'likes' => $post->likes_count,
                ];
            });
        
        $postsWithMostComments = Post::withCount('comments')
            ->orderBy('comments_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'comments' => $post->comments_count,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Post::count(),
                'by_category' => $postsByCategory,
                'new_posts' => $newPosts,
                'most_liked' => $postsWithMostLikes,
                'most_commented' => $postsWithMostComments,
            ],
        ]);
    }

    /**
     * Lấy thống kê nhóm
     */
    public function groups()
    {
        // Thống kê nhóm theo danh mục
        $groupsByCategory = Group::select('category_id', DB::raw('count(*) as count'))
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category ? $item->category->name : 'Không có danh mục',
                    'count' => $item->count,
                ];
            });
        
        // Thống kê nhóm theo số lượng thành viên
        $groupsBySize = [
            'small' => Group::has('members', '<', 10)->count(),
            'medium' => Group::has('members', '>=', 10)->has('members', '<', 50)->count(),
            'large' => Group::has('members', '>=', 50)->count(),
        ];
        
        // Nhóm lớn nhất
        $largestGroups = Group::withCount('members')
            ->orderBy('members_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members' => $group->members_count,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Group::count(),
                'by_category' => $groupsByCategory,
                'by_size' => $groupsBySize,
                'largest_groups' => $largestGroups,
            ],
        ]);
    }

    /**
     * Lấy thống kê tin nhắn
     */
    public function messages(Request $request)
    {
        $period = $request->input('period', 'month');
        $startDate = $this->getStartDate($period);
        
        // Thống kê tin nhắn theo thời gian
        $messagesByTime = $this->getTimeSeriesData(
            Message::where('created_at', '>=', $startDate),
            $period,
            'created_at'
        );
        
        // Thống kê tin nhắn theo loại chat
        $messagesByType = [
            'direct' => Message::whereHas('chat', function ($query) {
                $query->where('type', 'direct');
            })->count(),
            'group' => Message::whereHas('chat', function ($query) {
                $query->where('type', 'group');
            })->count(),
            'ai' => Message::whereHas('chat', function ($query) {
                $query->where('type', 'ai');
            })->count(),
        ];
        
        // Người dùng gửi nhiều tin nhắn nhất
        $topMessageSenders = Message::select('user_id', DB::raw('count(*) as count'))
            ->where('user_id', '!=', null)
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => $item->user ? $item->user->name : 'Người dùng đã xóa',
                    'count' => $item->count,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Message::count(),
                'by_time' => $messagesByTime,
                'by_type' => $messagesByType,
                'top_senders' => $topMessageSenders,
            ],
        ]);
    }

    /**
     * Lấy thống kê báo cáo
     */
    public function reports()
    {
        // Thống kê báo cáo theo loại
        $reportsByType = Report::select('reportable_type', DB::raw('count(*) as count'))
            ->groupBy('reportable_type')
            ->get()
            ->map(function ($item) {
                $type = class_basename($item->reportable_type);
                return [
                    'type' => $type,
                    'count' => $item->count,
                ];
            });
        
        // Thống kê báo cáo theo trạng thái
        $reportsByStatus = Report::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
        
        // Báo cáo mới nhất
        $latestReports = Report::with(['user:id,name', 'reportable'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $reportableType = class_basename($report->reportable_type);
                $reportableName = '';
                
                if ($report->reportable) {
                    if ($reportableType === 'Document') {
                        $reportableName = $report->reportable->title;
                    } elseif ($reportableType === 'Post') {
                        $reportableName = $report->reportable->title;
                    } elseif ($reportableType === 'Comment') {
                        $reportableName = substr($report->reportable->content, 0, 50) . '...';
                    } elseif ($reportableType === 'User') {
                        $reportableName = $report->reportable->name;
                    }
                }
                
                return [
                    'id' => $report->id,
                    'type' => $reportableType,
                    'item' => $reportableName,
                    'reason' => $report->reason,
                    'status' => $report->status,
                    'reporter' => $report->user ? $report->user->name : 'Người dùng đã xóa',
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Report::count(),
                'by_type' => $reportsByType,
                'by_status' => $reportsByStatus,
                'latest' => $latestReports,
            ],
        ]);
    }

    /**
     * Lấy thống kê lưu trữ
     */
    public function storage()
    {
        // Tổng dung lượng lưu trữ
        $totalStorage = FileUpload::sum('size');
        
        // Thống kê theo loại file
        $storageByType = FileUpload::select('mime_type', DB::raw('sum(size) as total_size'))
            ->groupBy('mime_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->mime_type,
                    'size' => $item->total_size,
                    'formatted_size' => $this->formatBytes($item->total_size),
                ];
            });
        
        // Thống kê theo người dùng
        $storageByUser = FileUpload::select('user_id', DB::raw('sum(size) as total_size'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderBy('total_size', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => $item->user ? $item->user->name : 'Người dùng đã xóa',
                    'size' => $item->total_size,
                    'formatted_size' => $this->formatBytes($item->total_size),
                ];
            });
        
        // Thống kê theo thời gian
        $storageGrowth = $this->getTimeSeriesData(
            FileUpload::select(DB::raw('DATE(created_at) as date'), DB::raw('sum(size) as total_size'))
                ->groupBy('date'),
            'month',
            'date',
            'total_size'
        );
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalStorage,
                'formatted_total' => $this->formatBytes($totalStorage),
                'by_type' => $storageByType,
                'by_user' => $storageByUser,
                'growth' => $storageGrowth,
            ],
        ]);
    }

    /**
     * Lấy ngày bắt đầu dựa trên khoảng thời gian
     */
    private function getStartDate($period)
    {
        switch ($period) {
            case 'week':
                return Carbon::now()->subWeek();
            case 'month':
                return Carbon::now()->subMonth();
            case 'quarter':
                return Carbon::now()->subMonths(3);
            case 'year':
                return Carbon::now()->subYear();
            default:
                return Carbon::now()->subMonth();
        }
    }

    /**
     * Lấy dữ liệu theo chuỗi thời gian
     */
    private function getTimeSeriesData($query, $period, $dateField, $valueField = null)
    {
        $format = '%Y-%m-%d';
        $groupBy = 'day';
        
        switch ($period) {
            case 'week':
                $format = '%Y-%m-%d';
                $groupBy = 'day';
                break;
            case 'month':
                $format = '%Y-%m-%d';
                $groupBy = 'day';
                break;
            case 'quarter':
                $format = '%Y-%m-%W';
                $groupBy = 'week';
                break;
            case 'year':
                $format = '%Y-%m';
                $groupBy = 'month';
                break;
        }
        
        if ($valueField) {
            $data = $query->get()
                ->groupBy(function ($item) use ($dateField, $groupBy) {
                    $date = $item->{$dateField} instanceof Carbon 
                        ? $item->{$dateField} 
                        : Carbon::parse($item->{$dateField});
                    
                    switch ($groupBy) {
                        case 'day':
                            return $date->format('Y-m-d');
                        case 'week':
                            return $date->format('Y-W');
                        case 'month':
                            return $date->format('Y-m');
                    }
                })
                ->map(function ($items) use ($valueField) {
                    return $items->sum($valueField);
                })
                ->toArray();
        } else {
            $data = $query->get()
                ->groupBy(function ($item) use ($dateField, $groupBy) {
                    $date = $item->{$dateField} instanceof Carbon 
                        ? $item->{$dateField} 
                        : Carbon::parse($item->{$dateField});
                    
                    switch ($groupBy) {
                        case 'day':
                            return $date->format('Y-m-d');
                        case 'week':
                            return $date->format('Y-W');
                        case 'month':
                            return $date->format('Y-m');
                    }
                })
                ->map(function ($items) {
                    return count($items);
                })
                ->toArray();
        }
        
        return $data;
    }

    /**
     * Định dạng bytes thành đơn vị đọc được
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
