<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupportTicketUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Mise à jour de votre demande Support',
            'message' => $this->message,
            'category' => 'support',
            'ticket_id' => (int) $this->ticket->id,
            'ticket_reference' => (string) $this->ticket->reference,
            'ticket_status' => (string) $this->ticket->status,
            'source' => 'support_center',
        ];
    }
}
