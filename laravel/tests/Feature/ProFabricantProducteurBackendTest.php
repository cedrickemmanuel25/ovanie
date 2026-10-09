<?php

namespace Tests\Feature;

use App\Models\ProDocument;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProFabricantProducteurBackendTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
    }

    public function test_fabricant_page_uses_the_profile_specific_view_and_schema(): void
    {
        $user = $this->fabricantUser();

        $this->actingAs($user, 'pro')
            ->get($this->proHost . '/compte/professionnel/fabricant_producteur')
            ->assertOk()
            ->assertSee('Fabricant / Producteur')
            ->assertSee('factory_position', false)
            ->assertSee('representative_id_type', false)
            ->assertSee('factory_photos', false)
            ->assertSee('data-required-fields', false)
            ->assertSee('data-required-documents', false);
    }

    public function test_empty_final_submission_is_rejected_by_backend(): void
    {
        $user = $this->fabricantUser();

        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/fabricant_producteur')
            ->post($this->proHost . '/compte/professionnel/fabricant_producteur', [
                'action' => 'submit',
            ])
            ->assertSessionHasErrors([
                'responsible_position',
                'professional_email',
                'professional_phone',
                'legal_name',
                'legal_form',
                'registration_number',
                'city',
                'address',
                'factory_name',
                'moq',
                'representative_id_type',
                'representative_id_number',
                'documents.registry',
                'documents.representative_id',
            ]);
    }

    public function test_fabricant_submit_persists_organization_profile_data_and_documents(): void
    {
        $user = $this->fabricantUser();

        $payload = [
            'action' => 'submit',
            'responsible_position' => 'direction_generale',
            'professional_email' => 'direction@fabricant.test',
            'professional_phone' => '+2250102030405',
            'professional_whatsapp' => '+2250102030405',
            'legal_name' => 'OVANIE Industrie Test SARL',
            'trade_name' => 'OVANIE Industrie',
            'registration_country' => 'Côte d’Ivoire',
            'city' => 'Abidjan',
            'address' => 'Zone industrielle de Yopougon',
            'legal_form' => 'sarl',
            'registration_number' => 'CI-ABJ-2026-B-12345',
            'tax_number' => '1234567 F',
            'founded_year' => 2024,
            'website' => 'https://example.test',
            'sector' => 'Matériaux de construction',
            'product_categories' => 'Ciment, béton, préfabrication',
            'factory_name' => 'Usine de Yopougon',
            'factory_country' => 'Côte d’Ivoire',
            'factory_city' => 'Abidjan',
            'factory_address' => 'Zone industrielle, lot 12',
            'factory_position' => '5.3453, -4.0053',
            'factory_founded_year' => 2024,
            'factory_area' => 5000,
            'production_lines' => 3,
            'capacity_monthly' => '1000 Tonnes',
            'capacity_annual' => '12000 Tonnes',
            'main_equipment' => 'Concasseur; mélangeur; silo',
            'products_manufactured' => 'Ciment, béton prêt à l’emploi',
            'markets_served' => 'Côte d’Ivoire, sous-région',
            'export_countries' => 'Sénégal, Mali',
            'moq' => '25 Tonnes',
            'samples' => 'yes',
            'production_lead_time' => '10 Jours',
            'countries_served' => 'Côte d’Ivoire, Ghana, Sénégal',
            'export_enabled' => 'yes',
            'shipping_modes' => ['terrestre', 'maritime'],
            'incoterms' => 'EXW, FOB, CIF',
            'representative_id_type' => 'cni',
            'representative_id_number' => 'CI123456789',
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
                'tax_certificate' => UploadedFile::fake()->create('attestation-fiscale.pdf', 100, 'application/pdf'),
                'representative_id' => UploadedFile::fake()->image('cni.jpg'),
                'factory_photos' => [UploadedFile::fake()->image('usine.jpg')],
                'technical_documents' => [UploadedFile::fake()->create('fiche-technique.pdf', 100, 'application/pdf')],
            ],
        ];

        $this->actingAs($user, 'pro')
            ->post($this->proHost . '/compte/professionnel/fabricant_producteur', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pro.roles.edit'));

        $profile = $user->fresh()->professionalProfile()->with('organization')->firstOrFail();
        $organization = $profile->organization;

        $this->assertSame('OVANIE Industrie Test SARL', $organization->legal_name);
        $this->assertSame('Côte d’Ivoire', $organization->registration_country);
        $this->assertSame('CI-ABJ-2026-B-12345', $organization->registration_number);
        $this->assertSame('1234567 F', $organization->tax_number);
        $this->assertSame('pending', $organization->verification_status);

        $this->assertSame('Usine de Yopougon', $profile->profile_data['factory_name']);
        $this->assertSame('5.3453, -4.0053', $profile->profile_data['factory_position']);
        $this->assertSame(['terrestre', 'maritime'], $profile->profile_data['shipping_modes']);
        $this->assertSame('cni', $profile->profile_data['representative_id_type']);
        $this->assertSame('pending', $profile->status);

        foreach (['registry', 'tax_certificate', 'representative_id', 'factory_photos', 'technical_documents'] as $type) {
            $this->assertTrue(ProDocument::query()
                ->where('pro_organization_id', $organization->id)
                ->where('document_type', $type)
                ->exists(), "Document {$type} non persisté");
        }
    }

    public function test_export_dependencies_are_enforced_only_when_export_is_enabled(): void
    {
        $user = $this->fabricantUser();

        $payload = $this->minimalSubmissionPayload();
        $payload['export_enabled'] = 'yes';
        $payload['shipping_modes'] = [];
        $payload['incoterms'] = '';

        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/fabricant_producteur')
            ->post($this->proHost . '/compte/professionnel/fabricant_producteur', $payload)
            ->assertSessionHasErrors(['shipping_modes', 'incoterms']);
    }

    private function fabricantUser(): ProUser
    {
        $user = ProUser::query()->create([
            'first_name' => 'Yao',
            'last_name' => 'Kouakou',
            'name' => 'Yao Kouakou',
            'email' => 'fabricant-backend@example.com',
            'phone' => '+2250102030405',
            'residence_country' => 'Côte d’Ivoire',
            'preferred_language' => 'fr',
            'password' => 'ProPassword123',
            'account_type' => 'pro',
            'onboarding_path' => 'professional',
            'status' => 'active',
            'professional_status' => 'draft',
            'locale' => 'fr',
            'currency' => 'XOF',
        ]);

        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id,
            'profile_key' => 'fabricant_producteur',
            'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire',
            'registration_country' => 'Côte d’Ivoire',
            'desired_functions' => ['sell'],
            'status' => 'draft',
        ]);

        return $user->fresh();
    }

    private function minimalSubmissionPayload(): array
    {
        return [
            'action' => 'submit',
            'responsible_position' => 'direction_generale',
            'professional_email' => 'direction@fabricant.test',
            'professional_phone' => '+2250102030405',
            'legal_name' => 'OVANIE Industrie Test SARL',
            'registration_country' => 'Côte d’Ivoire',
            'city' => 'Abidjan',
            'address' => 'Zone industrielle de Yopougon',
            'legal_form' => 'sarl',
            'registration_number' => 'CI-ABJ-2026-B-12345',
            'tax_number' => '1234567 F',
            'founded_year' => 2024,
            'sector' => 'Matériaux de construction',
            'product_categories' => 'Ciment',
            'factory_name' => 'Usine de Yopougon',
            'factory_country' => 'Côte d’Ivoire',
            'factory_city' => 'Abidjan',
            'factory_address' => 'Zone industrielle, lot 12',
            'factory_position' => '5.3453, -4.0053',
            'factory_founded_year' => 2024,
            'factory_area' => 5000,
            'production_lines' => 3,
            'capacity_monthly' => '1000 Tonnes',
            'capacity_annual' => '12000 Tonnes',
            'products_manufactured' => 'Ciment',
            'markets_served' => 'Côte d’Ivoire',
            'moq' => '25 Tonnes',
            'samples' => 'yes',
            'production_lead_time' => '10 Jours',
            'countries_served' => 'Côte d’Ivoire',
            'export_enabled' => 'no',
            'representative_id_type' => 'cni',
            'representative_id_number' => 'CI123456789',
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
                'tax_certificate' => UploadedFile::fake()->create('attestation-fiscale.pdf', 100, 'application/pdf'),
                'representative_id' => UploadedFile::fake()->image('cni.jpg'),
                'factory_photos' => [UploadedFile::fake()->image('usine.jpg')],
            ],
        ];
    }
}
