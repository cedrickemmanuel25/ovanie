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

class BusinessPaymentAmountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_modified_request_amount_does_not_change_billed_price(): void
    {
        config()->set('services.business.contact_access_price', 7500);
        $devis = $this->devis();
        $this->fakePaydunyaExpecting(7500);

        $this->actingAs(User::factory()->create())->postJson(route('business.pay'), [
            'request_id' => $devis->id,
            'request_type' => 'devis',
            'montant' => 100,
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['amount' => 7500, 'type' => 'business_contact']);
    }

    public function test_null_negative_and_very_low_amounts_are_ignored(): void
    {
        config()->set('services.business.contact_access_price', 6000);
        $user = User::factory()->create();

        foreach ([null, -500, 1] as $suppliedAmount) {
            $devis = $this->devis();
            $this->fakePaydunyaExpecting(6000);

            $this->actingAs($user)->postJson(route('business.pay'), [
                'request_id' => $devis->id,
                'request_type' => 'devis',
                'montant' => $suppliedAmount,
            ])->assertOk();
        }

        $this->assertSame([6000.0, 6000.0, 6000.0], Payment::query()->pluck('amount')->map(fn ($value) => (float) $value)->all());
    }

    public function test_unknown_business_request_is_rejected_without_server_error(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('business.pay'), [
            'request_id' => 999999,
            'request_type' => 'devis',
        ])->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_stored_payment_amount_matches_server_price(): void
    {
        config()->set('services.business.contact_access_price', 9000);
        $devis = $this->devis();
        $this->fakePaydunyaExpecting(9000);

        $this->actingAs(User::factory()->create())->postJson(route('business.pay'), [
            'request_id' => $devis->id,
            'request_type' => 'devis',
        ])->assertOk();

        $payment = Payment::query()->sole();
        $this->assertSame(9000.0, (float) $payment->amount);
        $this->assertSame(9000, $payment->provider_payload['expected_amount']);
    }

    public function test_amount_sent_to_paydunya_matches_server_price(): void
    {
        config()->set('services.business.contact_access_price', 12500);
        $devis = $this->devis();
        $this->fakePaydunyaExpecting(12500);

        $this->actingAs(User::factory()->create())->postJson(route('business.pay'), [
            'request_id' => $devis->id,
            'request_type' => 'devis',
            'montant' => 0,
        ])->assertOk();
    }

    private function fakePaydunyaExpecting(int $amount): void
    {
        $invoice = Mockery::mock(CheckoutInvoice::class);
        $invoice->token = 'BUS-' . uniqid();
        $invoice->shouldReceive('create')->once()->andReturnTrue();
        $invoice->shouldReceive('getInvoiceUrl')->once()->andReturn('https://pay.example.test/invoice');

        $this->mock(PayDunyaService::class, function ($mock) use ($amount, $invoice): void {
            $mock->shouldReceive('createOrderInvoice')
                ->once()
                ->with(Mockery::on(fn (array $data) => $data['amount'] === $amount))
                ->andReturn($invoice);
        });
    }

    private function devis(): Devis
    {
        return Devis::query()->create([
            'secteur' => 'BTP',
            'activites' => [],
            'prenom' => 'Client',
            'nom' => 'Business',
            'email' => uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 1000000,
            'projet' => 'Projet Business',
            'message' => 'Demande active',
        ]);
    }
}
