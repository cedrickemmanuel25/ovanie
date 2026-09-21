<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOvanieNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public array $payload = []
    ) {}

    public function handle(): void
    {
        // À connecter à la table notifications ou à Firebase/OneSignal plus tard.
        Log::info('OVANIE notification queued', [
            'user_id' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
            'payload' => $this->payload,
        ]);
    }
}
