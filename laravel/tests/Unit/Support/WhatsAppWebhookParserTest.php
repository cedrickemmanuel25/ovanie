<?php

namespace Tests\Unit\Support;

use App\Services\WhatsApp\WhatsAppWebhookParser;
use PHPUnit\Framework\TestCase;

class WhatsAppWebhookParserTest extends TestCase
{
    public function test_it_extracts_messages_and_statuses(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'phone-id',
                            'display_phone_number' => '2250161781818',
                        ],
                        'contacts' => [[
                            'wa_id' => '2250500000000',
                            'profile' => ['name' => 'Client OVANIE'],
                        ]],
                        'messages' => [[
                            'id' => 'wamid.inbound',
                            'from' => '2250500000000',
                            'timestamp' => '1780000000',
                            'type' => 'text',
                            'text' => ['body' => 'Où est ma commande OV-2026-1000 ?'],
                        ]],
                        'statuses' => [[
                            'id' => 'wamid.outbound',
                            'recipient_id' => '2250500000000',
                            'status' => 'delivered',
                            'timestamp' => '1780000001',
                        ]],
                    ],
                ]],
            ]],
        ];

        $result = (new WhatsAppWebhookParser())->parse($payload);

        $this->assertSame('wamid.inbound', $result['messages'][0]['provider_message_id']);
        $this->assertSame('Client OVANIE', $result['messages'][0]['contact_name']);
        $this->assertSame('Où est ma commande OV-2026-1000 ?', $result['messages'][0]['body']);
        $this->assertSame('delivered', $result['statuses'][0]['status']);
    }
}
