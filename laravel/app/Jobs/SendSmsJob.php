<?php

namespace App\Jobs;

use App\Services\Sms\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public string $phone,
        public string $message
    ) {}

    public function handle()
    {
        try {
            SmsManager::send($this->phone, $this->message);

            \Log::info('SMS envoyé', [
                'phone' => $this->phone,
                'message' => $this->message
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur SMS', [
                'phone' => $this->phone,
                'error' => $e->getMessage()
            ]);
        }
    }
}
