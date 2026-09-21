<?php

namespace Tests\Unit;

use Tests\TestCase;

class OpenShopLocalityUiRegressionTest extends TestCase
{
    public function test_open_shop_uses_searchable_location_comboboxes_and_professional_copy(): void
    {
        $view = file_get_contents(resource_path('views/open-shop.blade.php'));
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringContainsString('data-geo-combobox', $view);
        $this->assertStringContainsString('Sélectionner un quartier', $view);
        $this->assertStringContainsString('geo-combobox', $script);
        $this->assertStringContainsString('Recherche de votre position GPS', $script);
        $this->assertStringNotContainsString('Quartiers chargés depuis le catalogue sécurisé OVANIE', $script);
    }

    public function test_changing_commune_clears_dependent_location_and_ignores_stale_geocoding(): void
    {
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringContainsString('clearDependentLocationDetails({ dispatch: true })', $script);
        $this->assertStringContainsString("landmarkInput.value = ''", $script);
        $this->assertStringContainsString("addressInput.value = ''", $script);
        $this->assertStringContainsString('requestSequence !== geoRequestSequence || signature !== addressSignature()', $script);
    }

    public function test_step_two_hides_technical_feedback_and_never_autofills_a_generic_landmark(): void
    {
        $view = file_get_contents(resource_path('views/open-shop.blade.php'));
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringNotContainsString('localités disponibles. Recherchez puis sélectionnez', $view);
        $this->assertStringNotContainsString('Emplacement pour les enlèvements', $view);
        $this->assertStringContainsString("const geocoderWritableFieldIds = ['region', 'city', 'commune', 'district', 'address'];", $script);
        $this->assertStringContainsString('removeGenericLandmarkValue();', $script);
    }

    public function test_step_two_does_not_expose_or_require_exact_location_confirmation(): void
    {
        $view = file_get_contents(resource_path('views/open-shop.blade.php'));
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringNotContainsString('id="locationSummaryCard"', $view);
        $this->assertStringNotContainsString('id="exactLocationTitle"', $view);
        $this->assertStringNotContainsString('id="useLocationButton"', $view);
        $this->assertStringNotContainsString('hasConfirmedExactLocation()', $script);
        $this->assertStringNotContainsString('geo-combobox__option-meta', $view);
        $this->assertStringNotContainsString('geo-combobox__badge', $view);
        $this->assertStringContainsString('Sélectionner une commune', $view);
        $this->assertStringContainsString('Sélectionner un quartier', $view);
    }

    public function test_logistics_mode_uses_clear_professional_copy(): void
    {
        $view = file_get_contents(resource_path('views/open-shop.blade.php'));

        $this->assertStringContainsString('OVANIE Logistics', $view);
        $this->assertStringContainsString('Logistique vendeur', $view);
        $this->assertStringContainsString('Confiez la prise en charge de vos livraisons au réseau logistique OVANIE.', $view);
        $this->assertStringContainsString('Organisez directement les livraisons de vos commandes avec vos propres moyens.', $view);
        $this->assertStringNotContainsString('OVANIE organise la grille tarifaire', $view);
        $this->assertStringNotContainsString('Vous gérez vos zones, capacités, tarifs et délais', $view);
        $this->assertStringNotContainsString('en arrière-plan', $view);
        $this->assertStringNotContainsString('id="sellerLogisticsNotice"', $view);
    }

    public function test_removed_logistics_notice_does_not_stop_wizard_initialization(): void
    {
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringContainsString('if (!sellerLogisticsNotice)', $script);
        $this->assertStringContainsString("nextButton?.addEventListener('click'", $script);
    }

    public function test_terms_checkbox_only_looks_checked_when_it_is_really_checked(): void
    {
        $styles = file_get_contents(public_path('css/open-shop.css'));
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringContainsString('.terms-accept:has(input:checked) .terms-accept__box::after', $styles);
        $this->assertStringContainsString('opacity: 0;', $styles);
        $this->assertStringContainsString('event.stopPropagation();', $script);
        $this->assertStringNotContainsString('termsRead', $script);
    }

    public function test_ovanie_logistics_captures_gps_in_background_without_new_frontend_controls(): void
    {
        $view = file_get_contents(resource_path('views/open-shop.blade.php'));
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringNotContainsString('id="useLocationButton"', $view);
        $this->assertStringContainsString('captureOvanieGpsInBackground', $script);
        $this->assertStringContainsString("currentStep === 2 && selectedLogisticsType() === 'ovanie'", $script);
        $this->assertStringContainsString("storeCoordinates(lng, lat, 'browser_gps'", $script);
    }
}
