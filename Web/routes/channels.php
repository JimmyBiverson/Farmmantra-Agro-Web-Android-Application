<?php

use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Real-time channels for notifications, chat and order updates. Only
| authorized subscribers may listen on a channel.
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function (User $user, int $id) {
    $conversation = Conversation::find($id);

    if (! $conversation) {
        return false;
    }

    if ($user->role?->name === 'Franchise Partner') {
        return $conversation->franchise_id === $user->franchise_id || $conversation->created_by === $user->id;
    }

    return true;
});

Broadcast::channel('order.{id}', function (User $user, int $id) {
    $order = Order::find($id);

    if (! $order) {
        return false;
    }

    if ($user->role?->name === 'Franchise Partner') {
        return $order->franchise_id === $user->franchise_id;
    }

    return true;
});

Broadcast::channel('franchise.{id}', function (User $user, int $franchiseId) {
    if ($user->role?->name === 'Franchise Partner') {
        return $user->franchise_id === $franchiseId;
    }

    return true;
});
