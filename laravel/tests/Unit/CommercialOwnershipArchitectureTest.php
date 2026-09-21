<?php

namespace Tests\Unit;

use Tests\TestCase;

class CommercialOwnershipArchitectureTest extends TestCase
{
    public function test_commercial_lists_and_indicators_are_scoped_to_the_current_commercial(): void
    {
        $records = file_get_contents(app_path('Http/Controllers/Commercial/CommercialRecordController.php'));
        $dashboard = file_get_contents(app_path('Services/Staff/CommercialDashboardService.php'));

        $this->assertStringContainsString("where('created_by_commercial_id', \$request->user()->id)", $records);
        $this->assertStringContainsString("orWhere('managed_by_commercial_id', \$request->user()->id)", $records);
        $this->assertStringContainsString("where('created_by_commercial_id', \$user->id)", $dashboard);
        $this->assertStringContainsString("'clients_created'", $dashboard);
        $this->assertStringContainsString("'vendors_created'", $dashboard);
        $this->assertStringContainsString("'shops_created'", $dashboard);
    }

    public function test_commercial_creation_records_the_creator_and_manager(): void
    {
        $accounts = file_get_contents(app_path('Http/Controllers/Commercial/CommercialAccountController.php'));
        $products = file_get_contents(app_path('Http/Controllers/Commercial/CommercialProductController.php'));

        $this->assertStringContainsString("'created_by_commercial_id' => \$commercial->id", $accounts);
        $this->assertStringContainsString("'managed_by_commercial_id' => \$commercial->id", $accounts);
        $this->assertStringContainsString("'created_by_commercial_id' => \$request->user()->id", $products);
        $this->assertStringContainsString('Rule::in($shopIds->all())', $products);
    }

    public function test_admin_can_see_and_change_commercial_attribution(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminShopController.php'));
        $index = file_get_contents(resource_path('views/admin/shops/index.blade.php'));
        $clientIndex = file_get_contents(resource_path('views/admin/clients/index.blade.php'));

        $this->assertStringContainsString('updateCommercialManager', $controller);
        $this->assertStringContainsString('commercialCreator', $index);
        $this->assertStringContainsString('managed_by_commercial_id', $index);
        $this->assertStringContainsString('commercialCreator', $clientIndex);
    }

    public function test_commercial_account_form_matches_client_and_shop_onboarding_requirements(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Commercial/CommercialAccountController.php'));
        $form = file_get_contents(resource_path('views/commercial/accounts/create.blade.php'));

        foreach (['first_name', 'last_name', 'phone_country', 'password_confirmation', 'terms'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $form);
        }

        foreach (['sellerType', 'shopName', 'description', 'selfie', 'main_category', 'logistics_type', 'identityType', 'identityUploadMode', 'payment_mode', 'mmOperator'] as $field) {
            $this->assertStringContainsString($field, $form);
            $this->assertStringContainsString("'{$field}'", $controller);
        }

        $this->assertStringContainsString('class="category-menu hidden"', $form);
        $this->assertStringContainsString('@media(max-width:700px)', $form);
        $this->assertStringContainsString('Password::min(8)', $controller);
        $this->assertStringNotContainsString('mixedCase()', $controller);
        $this->assertStringNotContainsString('symbols()', $controller);
        $this->assertStringContainsString("'kyc/identity'", $controller);
    }
}
