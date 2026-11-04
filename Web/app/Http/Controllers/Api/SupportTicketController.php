<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SupportTicket::with('user');

        if ($user->role?->name === 'Franchise Partner') {
            $query->where('user_id', $user->id);
        }

        $tickets = $query->latest()->get();

        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'message' => 'required|string',
        ]);

        $user = $request->user();

        $ticket = SupportTicket::create([
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'user_id' => $user->id,
            'franchise_id' => $user->franchise_id,
            'subject' => $request->subject,
            'category' => $request->category,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
            'message' => $request->message,
        ]);

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'data' => $ticket,
        ], 201);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $user = request()->user();

        if ($user->role?->name === 'Franchise Partner' && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $ticket]);
    }
}
