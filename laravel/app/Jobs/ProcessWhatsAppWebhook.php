<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppSupportMessageProcessor;
use App\Services\WhatsApp\WhatsAppWebhookParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 240;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue((string) config('support_ai.queue', 'default'));
    }

    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(
        WhatsAppWebhookParser $parser,
        WhatsAppSupportMessageProcessor $processor,
    ): void {
        $event = WhatsAppWebhookEvent::find($this->eventId);
        if (! $event || $event->status === 'processed') {
            return;
        }

        $event->forceFill([
            'status' => 'processing',
            'attempts' => $event->attempts + 1,
            'error' => null,
        ])->save();

        try {
            $parsed = $parser->parse($event->payload ?? []);

            foreach ($parsed['messages'] as $message) {
                $processor->processInbound($message);
            }

            foreach ($parsed['statuses'] as $status) {
                $processor->processStatus($status);
            }

            $event->forceFill([
                'status' => ($parsed['messages'] === [] && $parsed['statuses'] === []) ? 'ignored' : 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }
}
