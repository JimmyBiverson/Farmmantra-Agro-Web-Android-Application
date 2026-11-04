<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->boolean('unreadOnly')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit(50)->get()->map(function ($n) {
            $data = json_decode($n->data, true) ?? [];

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'type' => $data['type'] ?? 'general',
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'route' => $data['route'] ?? null,
            ];
        });

        return response()->json($notifications);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }
}
