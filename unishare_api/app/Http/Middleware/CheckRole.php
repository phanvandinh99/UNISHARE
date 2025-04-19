<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
            
            return redirect()->route('login');
        }
        
        $userRole = auth()->user()->role;
        
        // Nếu người dùng là admin, luôn cho phép truy cập
        if ($userRole === 'admin') {
            return $next($request);
        }
        
        // Kiểm tra xem vai trò của người dùng có trong danh sách vai trò được phép không
        if (in_array($userRole, $roles)) {
            return $next($request);
        }
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập tài nguyên này.',
            ], 403);
        }
        
        return redirect()->route('home')->with('error', 'Bạn không có quyền truy cập trang này.');
    }
}
