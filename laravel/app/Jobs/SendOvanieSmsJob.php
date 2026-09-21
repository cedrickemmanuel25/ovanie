<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOvanieSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $phone,
        public string $message
    ) {}

    public function handle(): void
    {
        // Branche ici ton service SMS existant : Twilio, AllMySMS, Africa's Talking, etc.
        Log::info('OVANIE SMS queued', [
            'phone' => $this->phone,
            'message' => $this->message,
        ]);
    }
}
