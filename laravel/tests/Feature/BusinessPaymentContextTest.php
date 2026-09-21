<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\Payment;
use App\Models\User;
use App\Services\PayDunyaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Paydunya\Checkout\CheckoutInvoice;
use Tests\TestCase;

class BusinessPaymentContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_payment_keeps_request_type_and_identifier(): void
    {
        [$user, $devis] = $this->initiatePayment();
        $context = Payment::query()->sole()->provider_payload;

        $this->assertSame('devis', $context['business_request_type']);
        $this->assertSame($devis->id, $context['business_request_id']);
        $this->assertSame($user->id, $context['user_id']);
        $this->assertSame(5000, $context['server_price']);
    }

    public function test_context_is_read_back_as_an_array(): void
    {
        $this->initiatePayment();

        $this->assertIsArray(Payment::query()->sole()->provider_payload);
    }

    public function test_unknown_request_creates_no_payment(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('business.pay'), [
            'request_type' => 'devis',
            'request_id' => 999999,
        ])->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_no_unnecessary_sensitive_data_is_stored_in_context(): void
    {
        [$user] = $this->initiatePayment();
        $encoded = json_encode(Payment::query()->sole()->provider_payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($user->email, $encoded);
        $this->assertStringNotContainsString('0708091011', $encoded);
        $this->assertArrayNotHasKey('email', Payment::query()->sole()->provider_payload);
        $this->assertArrayNotHasKey('phone', Payment::query()->sole()->provider_payload);
    }

    public function test_classic_order_payment_model_behavior_is_unchanged(): void
    {
        $payment = new Payment([
            'order_id' => 42,
            'user_id' => 7,
            'method' => Payment::METHOD_PAYDUNYA,
            'type' => 'order_payment',
            'amount' => 15000,
            'status' => Payment::STATUS_PENDING,
            'provider_payload' => ['order_item_ids' => [10, 11]],
        ]);

        $this->assertSame(42, $payment->order_id);
        $this->assertSame('order_payment', $payment->type);
        $this->assertSame(['order_item_ids' => [10, 11]], $payment->provider_payload);
    }

    private function initiatePayment(): array
    {
        config()->set('services.business.contact_access_price', 5000);
        $user = User::factory()->create([
            'email' => uniqid() . '@private.example.test',
            'phone' => '0708091011',
        ]);
        $devis = Devis::query()->create([
            'secteur' => 'BTP',
            'activites' => [],
            'prenom' => 'Client',
            'nom' => 'Business',
            'email' => 'contact-demande@example.test',
            'telephone' => '0102030405',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 1000000,
            'projet' => 'Projet Business',
            'message' => 'Demande active',
        ]);

        $invoice = Mockery::mock(CheckoutInvoice::class);
        $invoice->token = 'BUS-' . uniqid();
        $invoice->shouldReceive('create')->once()->andReturnTrue();
        $invoice->shouldReceive('getInvoiceUrl')->once()->andReturn('https://pay.example.test/invoice');

        $this->mock(PayDunyaService::class, function ($mock) use ($invoice): void {
            $mock->shouldReceive('createOrderInvoice')->once()->andReturn($invoice);
        });

        $this->actingAs($user)->postJson(route('business.pay'), [
            'request_type' => 'devis',
            'request_id' => $devis->id,
        ])->assertOk();

        return [$user, $devis];
    }
}
