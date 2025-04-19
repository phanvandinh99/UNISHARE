<?php

namespace App\Http\Controllers\API\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }
    
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        
        return NotificationResource::collection($notifications);
    }
    
    public function unread(Request $request)
    {
        $notifications = $request->user()->unreadNotifications()->latest()->paginate(20);
        
        return NotificationResource::collection($notifications);
    }
    
    public function markAsRead(Request $request, $id)
    {
        $result = $this->notificationService->markAsRead($request->user(), $id);
        
        if (!$result) {
            return response()->json(['message' => 'Notification not found'], 404);
        }
        
        return response()->json(['message' => 'Notification marked as read']);
    }
    
    public function markAllAsRead(Request $request)
    {
        $this->notificationService->markAllAsRead($request->user());
        
        return response()->json(['message' => 'All notifications marked as read']);
    }
    
    public function destroy(Request $request, $id)
    {
        $result = $this->notificationService->deleteNotification($request->user(), $id);
        
        if (!$result) {
            return response()->json(['message' => 'Notification not found'], 404);
        }
        
        return response()->json(['message' => 'Notification deleted successfully']);
    }
}
