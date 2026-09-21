<?php

namespace Tests\Unit;

use App\Services\PayDunyaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayDunyaSoftPayPhoneTest extends TestCase
{
    public function test_wave_receives_current_ten_digit_ivorian_number_without_country_code(): void
    {
        config()->set('paydunya.mode', 'live');
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'Paiement lancé.']),
        ]);

        app(PayDunyaService::class)->startSoftPay([
            'operator' => 'wave',
            'payment_token' => 'token-test',
            'full_name' => 'Client Test',
            'email' => 'client@example.test',
            'phone' => '+225 07 04 74 97 85',
        ]);

        Http::assertSent(fn ($request): bool =>
            $request->url() === 'https://app.paydunya.com/api/v1/softpay/wave-ci'
            && $request['wave_ci_phone'] === '0704749785'
        );
    }
}
