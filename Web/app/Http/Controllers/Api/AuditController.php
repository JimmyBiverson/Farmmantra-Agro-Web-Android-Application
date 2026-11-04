<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function activityLogs(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'action' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = ActivityLog::with('user:id,name,email');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->action) {
            $query->where('action', 'like', $request->action.'%');
        }
        if ($request->date_from) {
            $query->where('created_at', '>=', now()->parse($request->date_from)->startOfDay());
        }
        if ($request->date_to) {
            $query->where('created_at', '<=', now()->parse($request->date_to)->endOfDay());
        }

        $logs = $query->latest()->paginate(50);

        return response()->json($logs);
    }

    public function activitySummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ? now()->parse($request->date_from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = $request->date_to ? now()->parse($request->date_to)->endOfDay() : now()->endOfDay();

        $byAction = ActivityLog::whereBetween('activity_logs.created_at', [$dateFrom, $dateTo])
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $byUser = ActivityLog::join('users', 'users.id', '=', 'activity_logs.user_id')
            ->whereRaw('activity_logs.created_at between ? and ?', [$dateFrom, $dateTo])
            ->select('activity_logs.user_id', 'users.name', DB::raw('COUNT(*) as count'))
            ->groupBy('activity_logs.user_id', 'users.name')
            ->orderByDesc('count')
            ->get();

        $byDay = ActivityLog::whereRaw('activity_logs.created_at between ? and ?', [$dateFrom, $dateTo])
            ->select(DB::raw('DATE(activity_logs.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(activity_logs.created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'by_action' => $byAction,
                'by_user' => $byUser,
                'by_day' => $byDay,
                'total_logs' => ActivityLog::whereBetween('activity_logs.created_at', [$dateFrom, $dateTo])->count(),
            ],
        ]);
    }

    public function userActivity(User $user): JsonResponse
    {
        $logs = ActivityLog::where('user_id', $user->id)
            ->latest()
            ->paginate(30);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'logs' => $logs,
        ]);
    }
}
