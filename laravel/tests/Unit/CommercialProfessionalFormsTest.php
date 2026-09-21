<?php

namespace Tests\Unit;

use Tests\TestCase;

class CommercialProfessionalFormsTest extends TestCase
{
    public function test_client_form_contains_only_client_fields(): void
    {
        $view = file_get_contents(resource_path('views/commercial/clients/create.blade.php'));

        foreach (['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $view);
        }

        foreach (['shopName', 'sellerType', 'logistics_type', 'identityNumber', 'mmNumber'] as $vendorField) {
            $this->assertStringNotContainsString('name="'.$vendorField.'"', $view);
        }

        $this->assertStringNotContainsString('conditions vendeur', $view);
        $this->assertStringNotContainsString('politique de confidentialité', $view);
    }

    public function test_vendor_form_is_a_four_step_wizard(): void
    {
        $view = file_get_contents(resource_path('views/commercial/vendors/create.blade.php'));

        foreach ([1, 2, 3, 4] as $step) {
            $this->assertStringContainsString('data-step-panel="'.$step.'"', $view);
            $this->assertStringContainsString('data-step-button="'.$step.'"', $view);
        }

        foreach (['shopName', 'sellerType', 'main_category', 'logistics_type', 'identityNumber', 'mmNumber'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $view);
        }

        $this->assertStringNotContainsString('Je confirme que le vendeur', $view);
        $this->assertStringContainsString("commercial.vendors.partials.location-picker", $view);
    }

    public function test_professional_styles_use_readable_field_sizes(): void
    {
        $styles = file_get_contents(resource_path('views/commercial/accounts/partials/professional-form-styles.blade.php'));

        $this->assertStringContainsString('height:50px', $styles);
        $this->assertStringContainsString('font-size:14px', $styles);
        $this->assertStringContainsString('.ocf-wizard', $styles);
        $this->assertStringContainsString('@media(max-width:700px)', $styles);
    }
}
