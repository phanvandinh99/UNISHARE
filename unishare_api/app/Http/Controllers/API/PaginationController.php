<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaginationController extends Controller
{
    /**
     * Cấu hình phân trang mặc định cho toàn bộ ứng dụng.
     */
    public static function getDefaultPerPage()
    {
        return 15; // Số lượng mục mặc định trên mỗi trang
    }
    
    /**
     * Lấy số lượng mục trên mỗi trang từ request.
     */
    public static function getPerPage(Request $request)
    {
        $perPage = $request->input('per_page', self::getDefaultPerPage());
        
        // Giới hạn số lượng mục tối đa trên mỗi trang để tránh quá tải
        $maxPerPage = 100;
        
        return min((int) $perPage, $maxPerPage);
    }
    
    /**
     * Áp dụng phân trang cho một query builder.
     */
    public static function paginate($query, Request $request)
    {
        $perPage = self::getPerPage($request);
        
        return $query->paginate($perPage);
    }
}
