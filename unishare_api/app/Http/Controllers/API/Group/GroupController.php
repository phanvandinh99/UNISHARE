<?php

namespace App\Http\Controllers\API\Group;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;
    
    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }
    
    public function index(Request $request)
    {
        $query = Group::query();
        
        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('course_code')) {
            $query->where('course_code', $request->course_code);
        }
        
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }
        
        // Show only public groups or groups the user is a member of
        $query->where(function ($q) use ($request) {
            $q->where('is_private', false)
              ->orWhereHas('members', function ($q) use ($request) {
                  $q->where('user_id', $request->user()->id);
              });
        });
        
        // Sort by latest
        $query->latest();
        
        $groups = $query->paginate(15);
        
        return GroupResource::collection($groups);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:course,university,interest',
            'course_code' => 'nullable|string|max:255',
            'is_private' => 'nullable|boolean',
            'cover_image' => 'nullable|image|max:5120', // 5MB max
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if user has permission to create groups
        if (!$request->user()->can('create group')) {
            return response()->json(['message' => 'You do not have permission to create groups'], 403);
        }
        
        // Handle cover image upload if provided
        $coverImagePath = null;
        if ($request->hasFile('cover_image')) {
            try {
                $fileUpload = $this->fileUploadService->uploadFile(
                    $request->file('cover_image'),
                    $request->user()->id,
                    'group_cover'
                );
                $coverImagePath = $fileUpload->file_path;
            } catch (\Exception $e) {
                // Log the error but continue with group creation
                \Log::error('Failed to upload cover image: ' . $e->getMessage());
            }
        }
        
        // Create the group
        $group = Group::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'course_code' => $request->course_code,
            'is_private' => $request->is_private ?? false,
            'cover_image' => $coverImagePath,
            'created_by' => $request->user()->id,
        ]);
        
        // Add the creator as a member and admin
        $group->members()->create([
            'user_id' => $request->user()->id,
            'role' => 'admin',
        ]);
        
        return new GroupResource($group);
    }
    
    public function show(Group $group)
    {
        // Check if user can view this group
        if ($group->is_private) {
            $isMember = $group->members()->where('user_id', request()->user()->id)->exists();
            
            if (!$isMember && !request()->user()->hasRole(['admin', 'moderator'])) {
                return response()->json(['message' => 'You do not have permission to view this group'], 403);
            }
        }
        
        return new GroupResource($group);
    }
    
    public function update(Request $request, Group $group)
    {
        // Check if user has permission to update this group
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to update this group'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:course,university,interest',
            'course_code' => 'nullable|string|max:255',
            'is_private' => 'nullable|boolean',
            'cover_image' => 'nullable|image|max:5120', // 5MB max
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle cover image upload if provided
        if ($request->hasFile('cover_image')) {
            try {
                $fileUpload = $this->fileUploadService->uploadFile(
                    $request->file('cover_image'),
                    $request->user()->id,
                    'group_cover'
                );
                $group->cover_image = $fileUpload->file_path;
            } catch (\Exception $e) {
                // Log the error but continue with group update
                \Log::error('Failed to upload cover image: ' . $e->getMessage());
            }
        }
        
        // Update the group
        $group->update([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'course_code' => $request->course_code,
            'is_private' => $request->is_private ?? $group->is_private,
        ]);
        
        return new GroupResource($group);
    }
    
    public function destroy(Request $request, Group $group)
    {
        // Check if user has permission to delete this group
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to delete this group'], 403);
        }
        
        // Delete all group members
        $group->members()->delete();
        
        // Delete all posts in the group
        foreach ($group->posts as $post) {
            // Delete post attachments
            foreach ($post->attachments as $attachment) {
                try {
                    $fileUpload = $attachment->fileUpload;
                    
                    if ($fileUpload) {
                        $this->fileUploadService->deleteFileUpload($fileUpload);
                    }
                    
                    $attachment->delete();
                } catch (\Exception $e) {
                    \Log::error('Failed to delete post attachment: ' . $e->getMessage());
                }
            }
            
            $post->delete();
        }
        
        // Delete the group cover image if it exists
        if ($group->cover_image) {
            try {
                // Find the file upload record
                $fileUpload = FileUpload::where('file_path', $group->cover_image)->first();
                
                if ($fileUpload) {
                    $this->fileUploadService->deleteFileUpload($fileUpload);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to delete group cover image: ' . $e->getMessage());
            }
        }
        
        $group->delete();
        
        return response()->json(['message' => 'Group deleted successfully']);
    }
    
    public function join(Request $request, Group $group)
    {
        // Check if user is already a member
        $isMember = $group->members()->where('user_id', $request->user()->id)->exists();
        
        if ($isMember) {
            return response()->json(['message' => 'You are already a member of this group'], 400);
        }
        
        // Check if the group is private
        if ($group->is_private) {
            // Create a join request
            $joinRequest = $group->joinRequests()->create([
                'user_id' => $request->user()->id,
                'status' => 'pending',
            ]);
            
            // Notify group admins
            $admins = $group->members()->where('role', 'admin')->get();
            
            foreach ($admins as $admin) {
                $this->notificationService->sendNotification(
                    $admin->user_id,
                    'group_join_request',
                    "{$request->user()->name} has requested to join '{$group->name}'",
                    ['group_id' => $group->id, 'user_id' => $request->user()->id]
                );
            }
            
            return response()->json(['message' => 'Join request sent successfully']);
        } else {
            // Add user as a member
            $group->members()->create([
                'user_id' => $request->user()->id,
                'role' => 'member',
            ]);
            
            return response()->json(['message' => 'Joined group successfully']);
        }
    }
    
    public function leave(Request $request, Group $group)
    {
        // Check if user is a member
        $member = $group->members()->where('user_id', $request->user()->id)->first();
        
        if (!$member) {
            return response()->json(['message' => 'You are not a member of this group'], 400);
        }
        
        // Check if user is the only admin
        $isAdmin = $member->role === 'admin';
        $adminCount = $group->members()->where('role', 'admin')->count();
        
        if ($isAdmin && $adminCount === 1) {
            return response()->json(['message' => 'You cannot leave the group as you are the only admin. Please assign another admin first.'], 400);
        }
        
        // Remove user from the group
        $member->delete();
        
        return response()->json(['message' => 'Left group successfully']);
    }
    
    public function members(Group $group)
    {
        // Check if user can view this group
        if ($group->is_private) {
            $isMember = $group->members()->where('user_id', request()->user()->id)->exists();
            
            if (!$isMember && !request()->user()->hasRole(['admin', 'moderator'])) {
                return response()->json(['message' => 'You do not have permission to view this group'], 403);
            }
        }
        
        $members = $group->members()->with('user')->paginate(20);
        
        return response()->json($members);
    }
    
    public function updateMember(Request $request, Group $group, $userId)
    {
        // Check if user has permission to update members
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to update members'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:admin,moderator,member',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Find the member
        $member = $group->members()->where('user_id', $userId)->first();
        
        if (!$member) {
            return response()->json(['message' => 'User is not a member of this group'], 404);
        }
        
        // Update the member's role
        $member->update(['role' => $request->role]);
        
        // Notify the user
        $this->notificationService->sendNotification(
            $userId,
            'group_role_updated',
            "Your role in '{$group->name}' has been updated to {$request->role}",
            ['group_id' => $group->id]
        );
        
        return response()->json(['message' => 'Member role updated successfully']);
    }
    
    public function removeMember(Request $request, Group $group, $userId)
    {
        // Check if user has permission to remove members
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to remove members'], 403);
        }
        
        // Find the member
        $member = $group->members()->where('user_id', $userId)->first();
        
        if (!$member) {
            return response()->json(['message' => 'User is not a member of this group'], 404);
        }
        
        // Cannot remove yourself if you're the only admin
        if ($userId == $request->user()->id) {
            $adminCount = $group->members()->where('role', 'admin')->count();
            
            if ($member->role === 'admin' && $adminCount === 1) {
                return response()->json(['message' => 'You cannot remove yourself as you are the only admin. Please assign another admin first.'], 400);
            }
        }
        
        // Remove the member
        $member->delete();
        
        // Notify the user
        $this->notificationService->sendNotification(
            $userId,
            'removed_from_group',
            "You have been removed from '{$group->name}'",
            ['group_id' => $group->id]
        );
        
        return response()->json(['message' => 'Member removed successfully']);
    }
    
    public function joinRequests(Request $request, Group $group)
    {
        // Check if user has permission to view join requests
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to view join requests'], 403);
        }
        
        $joinRequests = $group->joinRequests()->with('user')->where('status', 'pending')->paginate(20);
        
        return response()->json($joinRequests);
    }
    
    public function approveJoinRequest(Request $request, Group $group, $userId)
    {
        // Check if user has permission to approve join requests
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to approve join requests'], 403);
        }
        
        // Find the join request
        $joinRequest = $group->joinRequests()->where('user_id', $userId)->where('status', 'pending')->first();
        
        if (!$joinRequest) {
            return response()->json(['message' => 'Join request not found'], 404);
        }
        
        // Update the join request status
        $joinRequest->update(['status' => 'approved']);
        
        // Add the user as a member
        $group->members()->create([
            'user_id' => $userId,
            'role' => 'member',
        ]);
        
        // Notify the user
        $this->notificationService->sendNotification(
            $userId,
            'join_request_approved',
            "Your request to join '{$group->name}' has been approved",
            ['group_id' => $group->id]
        );
        
        return response()->json(['message' => 'Join request approved successfully']);
    }
    
    public function rejectJoinRequest(Request $request, Group $group, $userId)
    {
        // Check if user has permission to reject join requests
        $isAdmin = $group->members()->where('user_id', $request->user()->id)->where('role', 'admin')->exists();
        
        if (!$isAdmin && !$request->user()->can('manage any group')) {
            return response()->json(['message' => 'You do not have permission to reject join requests'], 403);
        }
        
        // Find the join request
        $joinRequest = $group->joinRequests()->where('user_id', $userId)->where('status', 'pending')->first();
        
        if (!$joinRequest) {
            return response()->json(['message' => 'Join request not found'], 404);
        }
        
        // Update the join request status
        $joinRequest->update(['status' => 'rejected']);
        
        // Notify the user
        $this->notificationService->sendNotification(
            $userId,
            'join_request_rejected',
            "Your request to join '{$group->name}' has been rejected",
            ['group_id' => $group->id]
        );
        
        return response()->json(['message' => 'Join request rejected successfully']);
    }
}
