<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\PasswordResetController;
use App\Http\Controllers\API\Admin\AdminDocumentController;
use App\Http\Controllers\API\Admin\UserManagementController;
use App\Http\Controllers\API\Chat\AIChatController;
use App\Http\Controllers\API\Chat\GroupChatController;
use App\Http\Controllers\API\Document\DocumentController;
use App\Http\Controllers\API\Group\GroupController;
use App\Http\Controllers\API\Message\ChatController;
use App\Http\Controllers\API\Message\MessageController;
use App\Http\Controllers\API\Moderator\ModeratorDocumentController;
use App\Http\Controllers\API\Notification\NotificationController;
use App\Http\Controllers\API\Post\PostCommentController;
use App\Http\Controllers\API\Post\PostController;
use App\Http\Controllers\API\Student\StudentDocumentController;
use App\Http\Controllers\API\Teacher\TeacherDocumentController;
use App\Http\Controllers\API\Upload\FileUploadController;
use App\Http\Controllers\API\WebSocket\WebSocketController;
use App\Http\Controllers\API\WebSocket\WebSocketStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Xác thực
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword']);

    // Các route yêu cầu xác thực
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::post('refresh-token', [AuthController::class, 'refreshToken']);
    });
});

// WebSocket
Route::middleware('auth:sanctum')->group(function () {
    Route::post('broadcasting/auth', [WebSocketController::class, 'auth']);
    Route::get('channels', [WebSocketController::class, 'getChannels']);
    Route::get('websocket/status', [WebSocketStatusController::class, 'status']);
    Route::post('websocket/test', [WebSocketStatusController::class, 'test']);
});

// Các route yêu cầu xác thực và tài khoản đang hoạt động
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Tài liệu
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::get('/{document}', [DocumentController::class, 'show']);
        Route::get('/{document}/download', [DocumentController::class, 'download']);
        Route::post('/{document}/report', [DocumentController::class, 'report']);
    });

    // Bài đăng
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'index']);
        Route::post('/', [PostController::class, 'store']);
        Route::get('/{post}', [PostController::class, 'show']);
        Route::put('/{post}', [PostController::class, 'update']);
        Route::delete('/{post}', [PostController::class, 'destroy']);
        Route::post('/{post}/like', [PostController::class, 'like']);
        Route::delete('/{post}/like', [PostController::class, 'unlike']);

        // Bình luận bài đăng
        Route::get('/{post}/comments', [PostCommentController::class, 'index']);
        Route::post('/{post}/comments', [PostCommentController::class, 'store']);
        Route::get('/{post}/comments/{comment}', [PostCommentController::class, 'show']);
        Route::put('/{post}/comments/{comment}', [PostCommentController::class, 'update']);
        Route::delete('/{post}/comments/{comment}', [PostCommentController::class, 'destroy']);
        Route::post('/{post}/comments/{comment}/like', [PostCommentController::class, 'like']);
        Route::delete('/{post}/comments/{comment}/like', [PostCommentController::class, 'unlike']);
        Route::get('/{post}/comments/{comment}/replies', [PostCommentController::class, 'replies']);
    });

    // Nhóm
    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::post('/', [GroupController::class, 'store']);
        Route::get('/{group}', [GroupController::class, 'show']);
        Route::put('/{group}', [GroupController::class, 'update']);
        Route::delete('/{group}', [GroupController::class, 'destroy']);
        Route::post('/{group}/join', [GroupController::class, 'join']);
        Route::post('/{group}/leave', [GroupController::class, 'leave']);
        Route::get('/{group}/members', [GroupController::class, 'members']);
        Route::put('/{group}/members/{userId}', [GroupController::class, 'updateMember']);
        Route::delete('/{group}/members/{userId}', [GroupController::class, 'removeMember']);
        Route::get('/{group}/join-requests', [GroupController::class, 'joinRequests']);
        Route::post('/{group}/join-requests/{userId}/approve', [GroupController::class, 'approveJoinRequest']);
        Route::post('/{group}/join-requests/{userId}/reject', [GroupController::class, 'rejectJoinRequest']);
        Route::post('/{group}/chat', [GroupChatController::class, 'createFromGroup']);
    });

    // Chat
    Route::prefix('chats')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::get('/{chat}', [ChatController::class, 'show']);
        Route::delete('/{chat}', [ChatController::class, 'destroy']);

        // Tin nhắn
        Route::get('/{chat}/messages', [MessageController::class, 'index']);
        Route::post('/{chat}/messages', [MessageController::class, 'store']);
        Route::post('/{chat}/read', [MessageController::class, 'markAsRead']);
        Route::delete('/{chat}/messages/{message}', [MessageController::class, 'destroy']);
    });

    // Chat nhóm
    Route::prefix('group-chats')->group(function () {
        Route::get('/', [GroupChatController::class, 'index']);
        Route::post('/', [GroupChatController::class, 'store']);
        Route::get('/{chat}', [GroupChatController::class, 'show']);
        Route::put('/{chat}', [GroupChatController::class, 'update']);
        Route::delete('/{chat}', [GroupChatController::class, 'destroy']);
        Route::post('/{chat}/participants', [GroupChatController::class, 'addParticipants']);
        Route::delete('/{chat}/participants/{userId}', [GroupChatController::class, 'removeParticipant']);
        Route::post('/{chat}/leave', [GroupChatController::class, 'leave']);
    });

    // Chat AI
    Route::prefix('ai-chats')->group(function () {
        Route::get('/', [AIChatController::class, 'index']);
        Route::post('/', [AIChatController::class, 'store']);
        Route::get('/{aiChat}', [AIChatController::class, 'show']);
        Route::put('/{aiChat}', [AIChatController::class, 'update']);
        Route::delete('/{aiChat}', [AIChatController::class, 'destroy']);
        Route::post('/{aiChat}/messages', [AIChatController::class, 'sendMessage']);
        Route::delete('/{aiChat}/messages', [AIChatController::class, 'clearHistory']);
    });

    // Thông báo
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Tải lên file
    Route::prefix('uploads')->group(function () {
        Route::post('/initialize', [FileUploadController::class, 'initializeUpload']);
        Route::post('/chunk', [FileUploadController::class, 'uploadChunk']);
        Route::get('/{uploadId}/status', [FileUploadController::class, 'checkUploadStatus']);
        Route::post('/{uploadId}/resume', [FileUploadController::class, 'handleInterruptedUpload']);
        Route::delete('/{uploadId}', [FileUploadController::class, 'cancelUpload']);
    });

    // API cho sinh viên
    Route::prefix('student')->group(function () {
        Route::get('/documents', [StudentDocumentController::class, 'index']);
        Route::get('/my-documents', [StudentDocumentController::class, 'myDocuments']);
        Route::post('/documents', [StudentDocumentController::class, 'store']);
        Route::get('/documents/{document}', [StudentDocumentController::class, 'show']);
        Route::put('/documents/{document}', [StudentDocumentController::class, 'update']);
        Route::delete('/documents/{document}', [StudentDocumentController::class, 'destroy']);
        Route::get('/documents/{document}/download', [StudentDocumentController::class, 'download']);
        Route::post('/documents/{document}/report', [StudentDocumentController::class, 'report']);
    });

    // API cho giảng viên
    Route::prefix('teacher')->middleware('role:lecturer')->group(function () {
        Route::get('/documents', [TeacherDocumentController::class, 'index']);
        Route::get('/my-documents', [TeacherDocumentController::class, 'myDocuments']);
        Route::post('/documents', [TeacherDocumentController::class, 'store']);
        Route::get('/documents/{document}', [TeacherDocumentController::class, 'show']);
        Route::put('/documents/{document}', [TeacherDocumentController::class, 'update']);
        Route::delete('/documents/{document}', [TeacherDocumentController::class, 'destroy']);
        Route::get('/documents/{document}/download', [TeacherDocumentController::class, 'download']);
        Route::post('/documents/{document}/official', [TeacherDocumentController::class, 'markAsOfficial']);
        Route::post('/documents/{document}/report', [TeacherDocumentController::class, 'report']);
    });

    // API cho người kiểm duyệt
    Route::prefix('moderator')->middleware('role:moderator')->group(function () {
        Route::get('/documents', [ModeratorDocumentController::class, 'index']);
        Route::get('/documents/pending', [ModeratorDocumentController::class, 'pendingApproval']);
        Route::post('/documents/{document}/approve', [ModeratorDocumentController::class, 'approve']);
        Route::post('/documents/{document}/reject', [ModeratorDocumentController::class, 'reject']);
        Route::delete('/documents/{document}', [ModeratorDocumentController::class, 'delete']);
        Route::get('/reports', [ModeratorDocumentController::class, 'reports']);
        Route::post('/reports/{report}/resolve', [ModeratorDocumentController::class, 'resolveReport']);
    });

    // API cho quản trị viên
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        // Quản lý tài liệu
        Route::get('/documents', [AdminDocumentController::class, 'index']);
        Route::get('/documents/{document}', [AdminDocumentController::class, 'show']);
        Route::put('/documents/{document}', [AdminDocumentController::class, 'update']);
        Route::delete('/documents/{document}', [AdminDocumentController::class, 'destroy']);
        Route::get('/document-reports', [AdminDocumentController::class, 'reports']);
        Route::post('/document-reports/{report}/resolve', [AdminDocumentController::class, 'resolveReport']);
        Route::get('/document-statistics', [AdminDocumentController::class, 'statistics']);

        // Quản lý người dùng
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::get('/users/{user}', [UserManagementController::class, 'show']);
        Route::put('/users/{user}', [UserManagementController::class, 'update']);
        Route::put('/users/{user}/password', [UserManagementController::class, 'updatePassword']);
        Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole']);
        Route::post('/users/{user}/ban', [UserManagementController::class, 'ban']);
        Route::post('/users/{user}/unban', [UserManagementController::class, 'unban']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
        Route::get('/roles', [UserManagementController::class, 'roles']);
        Route::get('/user-statistics', [UserManagementController::class, 'statistics']);
    });
});

// Thêm route test để kiểm tra API
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});
