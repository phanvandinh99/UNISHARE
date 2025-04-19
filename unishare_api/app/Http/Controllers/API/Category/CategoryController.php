<?php

namespace App\Http\Controllers\API\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    /**
     * Lấy danh sách tất cả danh mục
     */
    public function index(Request $request)
    {
        $query = Category::query();
        
        // Lọc theo loại danh mục
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        // Lọc theo danh mục cha
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            // Mặc định chỉ lấy danh mục gốc
            $query->whereNull('parent_id');
        }
        
        // Sắp xếp
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Phân trang
        $perPage = $request->input('per_page', 15);
        $categories = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Tạo danh mục mới
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:document,post,group',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = new Category();
        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        $category->type = $request->type;
        $category->parent_id = $request->parent_id;
        $category->description = $request->description;
        $category->icon = $request->icon;
        $category->color = $request->color;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Danh mục đã được tạo thành công',
            'data' => $category,
        ], 201);
    }

    /**
     * Hiển thị thông tin chi tiết danh mục
     */
    public function show($id)
    {
        $category = Category::with('children')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * Cập nhật thông tin danh mục
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:document,post,group',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Kiểm tra xem parent_id có tạo thành vòng lặp không
        if ($request->parent_id && $request->parent_id == $id) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không thể là danh mục cha của chính nó',
            ], 422);
        }

        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        $category->type = $request->type;
        $category->parent_id = $request->parent_id;
        $category->description = $request->description;
        $category->icon = $request->icon;
        $category->color = $request->color;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Danh mục đã được cập nhật thành công',
            'data' => $category,
        ]);
    }

    /**
     * Xóa danh mục
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        // Kiểm tra xem danh mục có danh mục con không
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa danh mục có danh mục con. Vui lòng xóa danh mục con trước.',
            ], 422);
        }
        
        // Kiểm tra xem danh mục có được sử dụng không
        $usageCount = 0;
        
        switch ($category->type) {
            case 'document':
                $usageCount = $category->documents()->count();
                break;
            case 'post':
                $usageCount = $category->posts()->count();
                break;
            case 'group':
                $usageCount = $category->groups()->count();
                break;
        }
        
        if ($usageCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Không thể xóa danh mục đang được sử dụng bởi {$usageCount} mục.",
            ], 422);
        }
        
        $category->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Danh mục đã được xóa thành công',
        ]);
    }
    
    /**
     * Lấy cây danh mục
     */
    public function tree(Request $request)
    {
        $type = $request->input('type', 'document');
        
        $categories = Category::where('type', $type)
            ->whereNull('parent_id')
            ->with('children')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
