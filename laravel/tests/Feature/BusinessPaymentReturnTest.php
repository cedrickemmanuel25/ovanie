<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPaymentReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_url_alone_does_not_confirm_payment(): void
    {
        [$user, $payment] = $this->businessPayment(Payment::STATUS_PENDING);

        $this->actingAs($user)
            ->get(route('business.pay.success', ['token' => $payment->reference]))
            ->assertRedirect(route('catalog.business'))
            ->assertSessionMissing('success');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_pending_payment_displays_verification_in_progress(): void
    {
        [$user, $payment] = $this->businessPayment(Payment::STATUS_PENDING);

        $this->actingAs($user)
            ->get(route('business.pay.success', ['token' => $payment->reference]))
            ->assertSessionHas('warning', 'Paiement en cours de vérification');
    }

    public function test_really_confirmed_payment_displays_success(): void
    {
        [$user, $payment] = $this->businessPayment(Payment::STATUS_PAID);

        $this->actingAs($user)
            ->get(route('business.pay.success', ['token' => $payment->reference]))
            ->assertSessionHas('success', 'Paiement confirmé');
    }

    public function test_cancelled_or_failed_payment_never_displays_success(): void
    {
        foreach ([
            Payment::STATUS_CANCELLED => 'Paiement annulé',
            Payment::STATUS_FAILED => 'Paiement échoué',
        ] as $status => $message) {
            [$user, $payment] = $this->businessPayment($status);

            $this->actingAs($user)
                ->get(route('business.pay.success', ['token' => $payment->reference]))
                ->assertSessionHas('error', $message)
                ->assertSessionMissing('success');
        }
    }

    public function test_user_cannot_view_another_users_business_payment(): void
    {
        [, $payment] = $this->businessPayment(Payment::STATUS_PAID);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('business.pay.success', ['token' => $payment->reference]))
            ->assertRedirect(route('catalog.business'))
            ->assertSessionHas('error', 'Paiement Business introuvable')
            ->assertSessionMissing('success');
    }

    public function test_unknown_reference_does_not_cause_server_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('business.pay.success', ['token' => 'REFERENCE-INCONNUE']))
            ->assertRedirect(route('catalog.business'))
            ->assertSessionHas('error', 'Paiement Business introuvable');
    }

    private function businessPayment(string $status): array
    {
        $user = User::factory()->create();
        $payment = Payment::query()->create([
            'order_id' => null,
            'user_id' => $user->id,
            'method' => Payment::METHOD_PAYDUNYA,
            'type' => 'business_contact',
            'amount' => 5000,
            'status' => $status,
            'reference' => 'BUS-RETURN-' . uniqid(),
            'provider_payload' => [
                'business_request_type' => 'devis',
                'business_request_id' => 1,
                'user_id' => $user->id,
                'server_price' => 5000,
            ],
        ]);

        return [$user, $payment];
    }
}
