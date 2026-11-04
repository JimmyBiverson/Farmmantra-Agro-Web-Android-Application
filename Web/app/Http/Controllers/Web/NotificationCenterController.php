<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->notifications()->latest();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return view('notifications.index', ['notifications' => $query->paginate(20)->withQueryString()]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $request->user()->notifications()->latest()->limit(10)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
