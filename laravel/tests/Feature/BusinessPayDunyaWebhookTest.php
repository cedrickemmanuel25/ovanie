<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPayDunyaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('paydunya.master_key', 'master-key-test');
        config()->set('paydunya.allow_unsigned_webhooks_in_testing', false);
    }

    public function test_successful_business_contact_payment_is_confirmed_without_order(): void
    {
        $payment = $this->businessPayment();

        $this->postJson(route('paydunya.webhook'), $this->payload($payment))
            ->assertOk()
            ->assertJsonPath('success', true);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNull($payment->order_id);
        $this->assertSame('txn-business-001', $payment->transaction_id);
    }

    public function test_order_payment_dispatch_continues_to_work(): void
    {
        $payment = Payment::query()->create([
            'order_id' => null,
            'user_id' => User::factory()->create()->id,
            'method' => Payment::METHOD_PAYDUNYA,
            'type' => 'order_payment',
            'amount' => 5000,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'ORDER-TOKEN',
        ]);
        $payload = $this->payload($payment);
        $payload['data']['status'] = 'pending';

        $this->postJson(route('paydunya.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_incorrect_amount_is_rejected(): void
    {
        $payment = $this->businessPayment();
        $payload = $this->payload($payment);
        $payload['data']['invoice']['total_amount'] = 100;

        $this->postJson(route('paydunya.webhook'), $payload)->assertUnprocessable();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_invalid_webhook_changes_nothing(): void
    {
        $payment = $this->businessPayment();
        $payload = $this->payload($payment);
        $payload['data']['hash'] = 'signature-invalide';

        $this->postJson(route('paydunya.webhook'), $payload)->assertUnauthorized();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->transaction_id);
    }

    public function test_duplicate_webhook_is_processed_only_once(): void
    {
        $payment = $this->businessPayment();
        $updates = 0;
        Payment::updated(function () use (&$updates): void {
            $updates++;
        });

        $this->postJson(route('paydunya.webhook'), $this->payload($payment))->assertOk();
        $this->postJson(route('paydunya.webhook'), $this->payload($payment))
            ->assertOk()
            ->assertJsonPath('message', 'Déjà traité');

        $this->assertSame(1, $updates);
    }

    public function test_unknown_payment_type_returns_controlled_error(): void
    {
        $payment = Payment::query()->create([
            'order_id' => null,
            'user_id' => User::factory()->create()->id,
            'method' => Payment::METHOD_PAYDUNYA,
            'type' => 'unknown_payment_type',
            'amount' => 5000,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'UNKNOWN-TOKEN',
        ]);

        $this->postJson(route('paydunya.webhook'), $this->payload($payment))
            ->assertUnprocessable();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    private function businessPayment(): Payment
    {
        $user = User::factory()->create();
        $devis = Devis::query()->create([
            'secteur' => 'BTP',
            'activites' => [],
            'prenom' => 'Client',
            'nom' => 'Business',
            'email' => uniqid() . '@example.test',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 1000000,
            'projet' => 'Projet Business',
            'message' => 'Demande active',
        ]);

        return Payment::query()->create([
            'order_id' => null,
            'user_id' => $user->id,
            'method' => Payment::METHOD_PAYDUNYA,
            'type' => 'business_contact',
            'amount' => 5000,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'BUSINESS-' . uniqid(),
            'provider_payload' => [
                'business_request_type' => 'devis',
                'business_request_id' => $devis->id,
                'user_id' => $user->id,
                'server_price' => 5000,
            ],
        ]);
    }

    private function payload(Payment $payment): array
    {
        return [
            'data' => [
                'hash' => hash('sha512', 'master-key-test'),
                'status' => 'completed',
                'transaction_id' => 'txn-business-001',
                'invoice' => [
                    'token' => $payment->reference,
                    'total_amount' => 5000,
                    'currency' => 'XOF',
                ],
            ],
        ];
    }
}
