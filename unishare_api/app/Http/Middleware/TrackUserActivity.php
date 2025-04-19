<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        if (auth()->check()) {
            $user = auth()->user();
            
            // Cập nhật thời gian hoạt động cuối cùng
            $user->last_activity_at = Carbon::now();
            
            // Nếu đây là đăng nhập mới (không có last_login_at hoặc đã quá 24 giờ)
            if (!$user->last_login_at || Carbon::parse($user->last_login_at)->diffInHours(Carbon::now()) > 24) {
                $user->last_login_at = Carbon::now();
            }
            
            $user->save();
        }
        
        return $response;
    }
}
