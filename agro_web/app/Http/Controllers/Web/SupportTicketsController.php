<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Support tickets on the web. Every user can raise tickets (sales staff
 * monitor the system and raise tickets for issues they see); the admin
 * answers and closes them.
 */
class SupportTicketsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = SupportTicket::with('user:id,name,role_id', 'user.role:id,name')->latest();
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('support.tickets', ['tickets' => $query->paginate(20)->withQueryString(), 'isAdmin' => $user->isAdmin()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'priority' => 'required|in:low,medium,high,urgent',
            'message' => 'required|string|max:5000',
        ]);
        $user = $request->user();
        $ticket = SupportTicket::create($data + [
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'user_id' => $user->id,
            'franchise_id' => $user->franchise_id,
            'status' => 'open',
        ]);

        NotificationService::send(
            User::whereHas('role', fn ($q) => $q->where('name', 'System Administrator'))->pluck('id')->all(),
            ($ticket->priority === 'urgent' ? 'URGENT ' : '').'Support ticket '.$ticket->ticket_number,
            "{$user->name}: {$ticket->subject}",
            'support',
            SupportTicket::class,
            $ticket->id,
            'support/tickets',
        );

        return back()->with('success', "Ticket {$ticket->ticket_number} submitted.");
    }

    public function respond(Request $request, SupportTicket $ticket)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate([
            'admin_response' => 'nullable|string|max:5000',
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);
        $ticket->update([
            'admin_response' => $request->admin_response ?? $ticket->admin_response,
            'status' => $request->status,
            'resolved_at' => in_array($request->status, ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null,
        ]);
        ActivityLogger::log('support.updated', "Ticket {$ticket->ticket_number} set to {$ticket->status}", $ticket, $request->user()->id);

        NotificationService::send(
            [$ticket->user_id],
            'Ticket '.$ticket->ticket_number.' updated',
            'Status: '.str_replace('_', ' ', $ticket->status).($request->admin_response ? ' — '.$request->admin_response : ''),
            'support',
            SupportTicket::class,
            $ticket->id,
            'support/tickets',
        );

        return back()->with('success', 'Ticket updated.');
    }
}
