<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $perPage = min($request->query('per_page', 20), 50);

        $query = Notification::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            });

        if ($request->has('unread_only') && $request->unread_only) {
            $query->where('read', false);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($notifications);
    }

    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        $count = Notification::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead($id): JsonResponse
    {
        $user = auth()->user();

        $notification = Notification::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();

        Notification::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    public function destroy($id): JsonResponse
    {
        $user = auth()->user();

        $notification = Notification::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['message' => 'Notification supprimée']);
    }

    public function recent(): JsonResponse
    {
        $user = auth()->user();

        $notifications = Notification::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $unreadCount = Notification::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            })
            ->where('read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }
}
