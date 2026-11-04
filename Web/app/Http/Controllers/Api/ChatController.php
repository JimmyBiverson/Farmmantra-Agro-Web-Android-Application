<?php

namespace App\Http\Controllers\Api;

use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Conversation::with(['latestMessage.sender', 'creator', 'franchise:id,name,code'])
            ->forUser($user)
            ->search($request->search);

        $conversations = $query->latest()->paginate(30)->through(function (Conversation $conversation) use ($user) {
            $conversation->unread = $conversation->messages()
                ->where('is_read', false)
                ->where('sender_id', '!=', $user->id)
                ->count();

            return $conversation;
        });

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
        ]);

        $user = $request->user();

        $conversation = Conversation::create([
            'franchise_id' => $user->franchise_id,
            'created_by' => $user->id,
            'subject' => $request->subject,
            'priority' => $request->priority ?? 'normal',
            'status' => 'open',
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'message' => $request->message,
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);

        $message->load('sender');

        event(new NewChatMessage($message, $conversation->id));

        $staffAndAdmin = User::staffAndAdmin()->pluck('id')->all();
        $senderName = $user->franchise?->name ? $user->name.' ('.$user->franchise->name.')' : $user->name;
        NotificationService::send(
            $staffAndAdmin,
            'New Message — '.$conversation->subject,
            $senderName.' sent a new message.',
            'chat',
            Conversation::class,
            $conversation->id,
            'admin/chat',
        );

        return response()->json(['message' => 'Conversation started.', 'data' => $conversation->load('messages.sender')], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (! $conversation->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $conversation->load(['messages.sender:id,name,avatar', 'creator:id,name,avatar', 'franchise:id,name,code']);

        return response()->json(['data' => $conversation]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['message' => 'required|string']);

        $user = $request->user();
        if (! $conversation->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'message' => $request->message,
            'is_delivered' => true,
            'delivered_at' => now(),
        ]);

        $message->load('sender');

        event(new NewChatMessage($message, $conversation->id));

        $recipients = [];
        if ($user->role?->name === 'Franchise Partner') {
            $recipients = User::staffAndAdmin()->pluck('id')->all();
        } else {
            $recipients = User::where('franchise_id', $conversation->franchise_id)
                ->orWhere('id', $conversation->created_by)
                ->pluck('id')
                ->all();
        }

        NotificationService::send(
            $recipients,
            'New Message — '.$conversation->subject,
            "{$user->name}: {$request->message}",
            'chat',
            Conversation::class,
            $conversation->id,
            'chat',
        );

        return response()->json(['message' => 'Message sent.', 'data' => $message], 201);
    }

    /**
     * Mark all messages from the other party as read.
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (! $conversation->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $updated = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Conversation marked as read.',
            'marked' => $updated,
        ]);
    }

    /**
     * Returns messages created after a given message id (lightweight polling).
     */
    public function messagesSince(Request $request, Conversation $conversation, ?int $afterId = null): JsonResponse
    {
        $user = $request->user();
        if (! $conversation->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = $conversation->messages()->with('sender:id,name,avatar');
        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->orderBy('id')->get();

        return response()->json(['data' => $messages]);
    }
}
