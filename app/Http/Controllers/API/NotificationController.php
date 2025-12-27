<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        $notifications = DB::table('notifications')
            ->where('notifiable_type', 'App\Models\User\User')
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => json_decode($notification->data, true),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            });

        $unreadCount = DB::table('notifications')
            ->where('notifiable_type', 'App\Models\User\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = auth()->user();

        $updated = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', 'App\Models\User\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOTIFICATION_NOT_FOUND',
                    'message' => 'Notificación no encontrada o ya marcada como leída',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();

        $updated = DB::table('notifications')
            ->where('notifiable_type', 'App\Models\User\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => sprintf('%d notificaciones marcadas como leídas', $updated),
        ]);
    }
}
