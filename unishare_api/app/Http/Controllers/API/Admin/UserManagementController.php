<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Lấy danh sách người dùng
     */
    public function index(Request $request)
    {
        $query = User::query();
        
        // Tìm kiếm
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%");
            });
        }
        
        // Lọc theo vai trò
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        // Lọc theo trạng thái
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }
        
        // Lọc theo trường đại học
        if ($request->has('university')) {
            $query->where('university', 'like', "%{$request->university}%");
        }
        
        // Lọc theo khoa
        if ($request->has('department')) {
            $query->where('department', 'like', "%{$request->department}%");
        }
        
        // Sắp xếp
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Phân trang
        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Tạo người dùng mới
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:student,lecturer,moderator,admin',
            'university' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role = $request->role;
        $user->university = $request->university;
        $user->department = $request->department;
        $user->student_id = $request->student_id;
        $user->is_active = $request->has('is_active') ? $request->is_active : true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Người dùng đã được tạo thành công',
            'data' => $user,
        ], 201);
    }

    /**
     * Hiển thị thông tin chi tiết người dùng
     */
    public function show($id)
    {
        $user = User::with(['documents', 'posts', 'groups'])->findOrFail($id);
        
        // Thống kê hoạt động
        $activityStats = [
            'documents_count' => $user->documents()->count(),
            'posts_count' => $user->posts()->count(),
            'comments_count' => $user->comments()->count(),
            'groups_count' => $user->groups()->count(),
            'messages_count' => $user->messages()->count(),
            'storage_used' => $user->fileUploads()->sum('size'),
            'storage_formatted' => $this->formatBytes($user->fileUploads()->sum('size')),
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'activity' => $activityStats,
            ],
        ]);
    }

    /**
     * Cập nhật thông tin người dùng
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'university' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->university = $request->university;
        $user->department = $request->department;
        $user->student_id = $request->student_id;
        
        if ($request->has('is_active')) {
            $user->is_active = $request->is_active;
        }
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Thông tin người dùng đã được cập nhật thành công',
            'data' => $user,
        ]);
    }

    /**
     * Cập nhật mật khẩu người dùng
     */
    public function updatePassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Mật khẩu đã được cập nhật thành công',
        ]);
    }

    /**
     * Cập nhật vai trò người dùng
     */
    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:student,lecturer,moderator,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Vai trò người dùng đã được cập nhật thành công',
            'data' => $user,
        ]);
    }

    /**
     * Cấm người dùng
     */
    public function ban(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->is_active = false;
        $user->ban_reason = $request->reason;
        $user->banned_at = now();
        $user->save();

        // Đăng xuất người dùng bằng cách thu hồi tất cả token
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Người dùng đã bị cấm thành công',
        ]);
    }

    /**
     * Bỏ cấm người dùng
     */
    public function unban($id)
    {
        $user = User::findOrFail($id);
        
        $user->is_active = true;
        $user->ban_reason = null;
        $user->banned_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã bỏ cấm người dùng thành công',
        ]);
    }

    /**
     * Xóa người dùng
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Kiểm tra xem người dùng có phải là admin duy nhất không
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa admin duy nhất trong hệ thống',
                ], 422);
            }
        }
        
        // Thu hồi tất cả token
        $user->tokens()->delete();
        
        // Xóa người dùng (soft delete)
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Người dùng đã được xóa thành công',
        ]);
    }

    /**
     * Lấy danh sách vai trò
     */
    public function roles()
    {
        $roles = [
            ['value' => 'student', 'label' => 'Sinh viên'],
            ['value' => 'lecturer', 'label' => 'Giảng viên'],
            ['value' => 'moderator', 'label' => 'Người kiểm duyệt'],
            ['value' => 'admin', 'label' => 'Quản trị viên'],
        ];
        
        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Lấy thống kê người dùng
     */
    public function statistics()
    {
        // Tổng số người dùng
        $totalUsers = User::count();
        
        // Người dùng theo vai trò
        $usersByRole = User::select('role', \DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->pluck('count', 'role')
            ->toArray();
        
        // Người dùng hoạt động/không hoạt động
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        
        // Người dùng mới trong 7 ngày qua
        $newUsers = User::where('created_at', '>=', \Carbon\Carbon::now()->subDays(7))->count();
        
        // Người dùng đăng nhập gần đây
        $recentlyActiveUsers = User::where('last_login_at', '>=', \Carbon\Carbon::now()->subDays(7))->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalUsers,
                'by_role' => $usersByRole,
                'active' => $activeUsers,
                'inactive' => $inactiveUsers,
                'new_users' => $newUsers,
                'recently_active' => $recentlyActiveUsers,
            ],
        ]);
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
