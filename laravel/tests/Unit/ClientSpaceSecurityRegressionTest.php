<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClientSpaceSecurityRegressionTest extends TestCase
{
    private function projectFile(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . ltrim($path, '/'));
    }

    public function test_quote_page_does_not_expose_shop_identity(): void
    {
        $controller = $this->projectFile('app/Http/Controllers/DevisWebController.php');
        $view = $this->projectFile('resources/views/devis.blade.php');
        $routes = $this->projectFile('routes/web.php');

        $this->assertStringNotContainsString("with('shop')", $controller);
        $this->assertStringNotContainsString("'shop' =>", $controller);
        $this->assertStringNotContainsString('product.shop', $view);
        $this->assertStringNotContainsString('Toutes les boutiques', $view);
        $this->assertStringNotContainsString("Route::get('/shops/{shop}'", $routes);
    }

    public function test_receipt_views_are_read_only_and_share_financial_summary(): void
    {
        $htmlView = $this->projectFile('resources/views/receipt.blade.php');
        $pdfView = $this->projectFile('resources/views/pdf/receipt.blade.php');
        $controller = $this->projectFile('app/Http/Controllers/CheckoutController.php');

        $this->assertStringNotContainsString('firstOrCreate', $htmlView);
        $this->assertStringNotContainsString('items()->create', $htmlView);
        $this->assertStringNotContainsString('OrderReceptionForm', $htmlView);
        $this->assertStringContainsString("\$financialSummary['display_total']", $pdfView);
        $this->assertStringContainsString("Pdf::loadView('pdf.receipt', compact('order', 'financialSummary'))", $controller);
    }

    public function test_cancelled_order_payment_guard_exists_before_business_release(): void
    {
        $controller = $this->projectFile('app/Http/Controllers/PaymentController.php');

        $cancelGuard = strpos($controller, "if (\$order->status === 'cancelled')");
        $stockCommit = strrpos($controller, '$this->stockReservations->commit');
        $orderLock = strpos($controller, "->whereKey(\$payment->order_id)");
        $paymentLock = strpos($controller, "->whereKey(\$payment->id)");

        $this->assertNotFalse($cancelGuard);
        $this->assertNotFalse($stockCommit);
        $this->assertLessThan($stockCommit, $cancelGuard);
        $this->assertStringContainsString('received_after_order_cancellation', $controller);
        $this->assertNotFalse($orderLock);
        $this->assertNotFalse($paymentLock);
        $this->assertLessThan($paymentLock, $orderLock);
    }

    public function test_checkout_keeps_delivery_account_and_payment_phone_contexts_separate(): void
    {
        $controller = $this->projectFile('app/Http/Controllers/CheckoutController.php');

        $this->assertStringContainsString('$deliveryRecipientPhone', $controller);
        $this->assertStringContainsString("'delivery_recipient_phone' => \$deliveryRecipientPhone", $controller);
        $this->assertStringNotContainsString("\$request->merge(['phone' => \$contactPhone])", $controller);
        $this->assertStringNotContainsString("'whatsapp_phone' => \$request->whatsapp_phone", $controller);
    }

    public function test_timeline_never_falls_back_to_internal_delivery_messages(): void
    {
        $timeline = $this->projectFile('app/Services/ClientOrderTimelineService.php');

        $this->assertStringNotContainsString('$history->note', $timeline);
        $this->assertStringNotContainsString('publicDeliveryLabel($history->status, $history->label)', $timeline);
        $this->assertStringContainsString("'shipment:' . \$shipment->id", $timeline);
    }

    public function test_claim_and_refund_workflows_have_non_pickup_review_states(): void
    {
        $service = $this->projectFile('app/Services/ReturnRefundService.php');
        $model = $this->projectFile('app/Models/ReturnModel.php');

        $this->assertStringContainsString("'claim' => ReturnModel::LOGISTICS_CLAIM_REVIEW", $service);
        $this->assertStringContainsString("'refund' => ReturnModel::LOGISTICS_REFUND_REVIEW", $service);
        $this->assertStringContainsString("LOGISTICS_CLAIM_REVIEW = 'claim_review'", $model);
        $this->assertStringContainsString("LOGISTICS_REFUND_REVIEW = 'refund_review'", $model);
    }

    public function test_social_account_deletion_requires_one_time_code(): void
    {
        $controller = $this->projectFile('app/Http/Controllers/ClientAccountController.php');
        $routes = $this->projectFile('routes/web.php');

        $this->assertStringContainsString("\$rules['deletion_code'] = ['required', 'digits:6']", $controller);
        $this->assertStringContainsString('requestAccountDeletionCode', $controller);
        $this->assertStringContainsString("->middleware('throttle:3,10')", $routes);
    }
    public function test_cod_initial_fee_never_reduces_order_principal_balance(): void
    {
        $settlement = $this->projectFile('app/Services/OrderSettlementService.php');

        $customerTypesStart = strpos($settlement, 'private const CUSTOMER_SETTLEMENT_TYPES');
        $initialFeeStart = strpos($settlement, 'private const INITIAL_FEE_TYPES');
        $customerTypesBlock = substr($settlement, $customerTypesStart, $initialFeeStart - $customerTypesStart);

        $this->assertStringNotContainsString("'commission_payment'", $customerTypesBlock);
        $this->assertStringContainsString("'commission_payment'", $settlement);
        $this->assertStringContainsString('initialFeeAmount', $settlement);
    }

    public function test_client_tracking_requires_real_trackable_delivery(): void
    {
        $statusService = $this->projectFile('app/Services/ClientOrderStatusService.php');
        $controller = $this->projectFile('app/Http/Controllers/Api/ShipmentTrackingController.php');
        $accountController = $this->projectFile('app/Http/Controllers/ClientAccountController.php');

        $this->assertStringContainsString('isItemTrackable', $statusService);
        $this->assertStringContainsString("['pickup', 'retrait']", $statusService);
        $this->assertStringContainsString("abort_unless(\$statusService->canTrack(\$order), 404)", $accountController);
        $this->assertStringContainsString("['cancelled', 'failed', 'not_required']", $controller);
    }

    public function test_client_tracking_displays_only_the_authenticated_clients_pending_delivery_otp(): void
    {
        $controller = $this->projectFile('app/Http/Controllers/ClientAccountController.php');
        $view = $this->projectFile('resources/views/client/orders/tracking.blade.php');

        $this->assertStringContainsString("abort_unless((int) \$order->client_id === (int) \$request->user()->id, 403)", $controller);
        $this->assertStringContainsString('filled($item->delivery_otp_code)', $controller);
        $this->assertStringContainsString('$item->delivery_otp_verified_at === null', $controller);
        $this->assertStringContainsString('Votre code de remise', $view);
        $this->assertStringContainsString("{{ \$otpGroup['code'] }}", $view);
        $this->assertStringNotContainsString("asset('storage/", $view);
    }

    public function test_claim_quantity_is_separate_from_physical_return_quantity(): void
    {
        $service = $this->projectFile('app/Services/ReturnRefundService.php');
        $controller = $this->projectFile('app/Http/Controllers/ClientAccountController.php');

        $this->assertStringContainsString("? ['claim']", $service);
        $this->assertStringContainsString("['return', 'refund']", $service);
        $this->assertStringContainsString("availableQuantity(\$item, \$data['return_type'])", $controller);
    }

    public function test_saved_address_keeps_quartier_through_checkout(): void
    {
        $resolver = $this->projectFile('app/Services/CheckoutAddressResolver.php');
        $checkoutView = $this->projectFile('resources/views/checkout.blade.php');
        $addressModel = $this->projectFile('app/Models/Address.php');

        $this->assertStringContainsString("'delivery_quartier' => \$address->quartier", $resolver);
        $this->assertStringContainsString('savedAddress.delivery_quartier', $checkoutView);
        $this->assertStringContainsString("'quartier'", $addressModel);
    }

    public function test_account_deletion_and_checkout_share_user_row_lock(): void
    {
        $deletion = $this->projectFile('app/Services/AccountDeletionService.php');
        $checkout = $this->projectFile('app/Http/Controllers/CheckoutController.php');

        $this->assertStringContainsString("['deletion_in_progress' => true]", $deletion);
        $this->assertStringContainsString('->lockForUpdate()', $deletion);
        $this->assertStringContainsString('$checkoutUser = \App\Models\User::query()', $checkout);
        $this->assertStringContainsString('->lockForUpdate()', $checkout);
        $this->assertStringContainsString('$checkoutUser->deletion_in_progress', $checkout);
    }

}
