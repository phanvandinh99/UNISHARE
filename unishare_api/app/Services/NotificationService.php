<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a user.
     */
    public function sendNotification(User $user, $notification)
    {
        try {
            NotificationFacade::send($user, $notification);
            
            // Broadcast the notification to WebSocket
            $this->broadcastNotification($user, $notification);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a notification to multiple users.
     */
    public function sendNotificationToMultipleUsers($users, $notification)
    {
        try {
            NotificationFacade::send($users, $notification);
            
            // Broadcast the notification to WebSocket for each user
            foreach ($users as $user) {
                $this->broadcastNotification($user, $notification);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification to multiple users: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Broadcast a notification to WebSocket.
     */
    protected function broadcastNotification(User $user, $notification)
    {
        try {
            // Get the notification data
            $notificationData = $notification->toArray($user);
            
            // Broadcast to the user's private channel
            broadcast(new \App\Events\NotificationSent($user, $notificationData))->toOthers();
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to broadcast notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(User $user, $notificationId)
    {
        try {
            $notification = DatabaseNotification::where('id', $notificationId)
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->first();
            
            if ($notification) {
                $notification->markAsRead();
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user)
    {
        try {
            $user->unreadNotifications->markAsRead();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(User $user, $notificationId)
    {
        try {
            $notification = DatabaseNotification::where('id', $notificationId)
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->first();
            
            if ($notification) {
                $notification->delete();
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete notification: ' . $e->getMessage());
            return false;
        }
    }
}
