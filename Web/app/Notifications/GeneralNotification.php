<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $type = 'general',
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $route = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'route' => $this->route,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
