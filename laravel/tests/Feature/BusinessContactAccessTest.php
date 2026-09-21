<?php

namespace Tests\Feature;

use App\Models\BusinessContactAccess;
use App\Models\Devis;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessContactAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('paydunya.master_key', 'master-key-access-test');
        config()->set('paydunya.allow_unsigned_webhooks_in_testing', false);
    }

    public function test_confirmed_business_payment_creates_one_access(): void
    {
        [, $payment, $devis] = $this->businessPayment();

        $this->postJson(route('paydunya.webhook'), $this->payload($payment))->assertOk();

        $this->assertDatabaseHas('business_contact_accesses', [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'business_request_type' => 'devis',
            'business_request_id' => $devis->id,
            'access_count' => 0,
        ]);
        $this->assertDatabaseCount('business_contact_accesses', 1);
    }

    public function test_pending_failed_or_cancelled_payment_creates_no_access(): void
    {
        foreach ([Payment::STATUS_PENDING, Payment::STATUS_FAILED, Payment::STATUS_CANCELLED] as $status) {
            [$user, $payment] = $this->businessPayment($status);

            $this->actingAs($user)
                ->get(route('business.pay.success', ['token' => $payment->reference]))
                ->assertRedirect(route('catalog.business'));
        }

        $this->assertDatabaseCount('business_contact_accesses', 0);
    }

    public function test_duplicate_webhook_does_not_duplicate_access(): void
    {
        [, $payment] = $this->businessPayment();

        $this->postJson(route('paydunya.webhook'), $this->payload($payment))->assertOk();
        $this->postJson(route('paydunya.webhook'), $this->payload($payment))->assertOk();

        $this->assertDatabaseCount('business_contact_accesses', 1);
    }

    public function test_user_cannot_consume_another_users_access(): void
    {
        [$owner, $payment] = $this->businessPayment();
        $this->postJson(route('paydunya.webhook'), $this->payload($payment))->assertOk();
        $access = BusinessContactAccess::query()->sole();

        $this->assertFalse($access->consumeFor(User::factory()->create()));
        $this->assertSame(0, $access->fresh()->access_count);

        $this->assertTrue($access->consumeFor($owner));
        $this->assertSame(1, $access->fresh()->access_count);
        $this->assertNotNull($access->fresh()->last_accessed_at);
    }

    public function test_expired_or_revoked_access_is_refused(): void
    {
        [$user, $payment] = $this->businessPayment();
        $this->postJson(route('paydunya.webhook'), $this->payload($payment))->assertOk();
        $access = BusinessContactAccess::query()->sole();

        $access->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($access->consumeFor($user));

        $access->update(['expires_at' => null, 'revoked_at' => now()]);
        $this->assertFalse($access->consumeFor($user));
        $this->assertSame(0, $access->fresh()->access_count);
    }

    public function test_browser_return_alone_never_creates_access(): void
    {
        [$user, $payment] = $this->businessPayment(Payment::STATUS_PENDING);

        $this->actingAs($user)
            ->get(route('business.pay.success', ['token' => $payment->reference]))
            ->assertSessionHas('warning', 'Paiement en cours de vérification');

        $this->assertDatabaseCount('business_contact_accesses', 0);
    }

    private function businessPayment(string $status = Payment::STATUS_PENDING): array
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
        $payment = Payment::query()->create([
            'order_id' => null,
            'user_id' => $user->id,
            'method' => Payment::METHOD_PAYDUNYA,
            'type' => 'business_contact',
            'amount' => 5000,
            'status' => $status,
            'reference' => 'BUS-ACCESS-' . uniqid(),
            'provider_payload' => [
                'business_request_type' => 'devis',
                'business_request_id' => $devis->id,
                'user_id' => $user->id,
                'server_price' => 5000,
            ],
        ]);

        return [$user, $payment, $devis];
    }

    private function payload(Payment $payment): array
    {
        return [
            'data' => [
                'hash' => hash('sha512', 'master-key-access-test'),
                'status' => 'completed',
                'transaction_id' => 'txn-' . $payment->id,
                'invoice' => [
                    'token' => $payment->reference,
                    'total_amount' => 5000,
                    'currency' => 'XOF',
                ],
            ],
        ];
    }
}
