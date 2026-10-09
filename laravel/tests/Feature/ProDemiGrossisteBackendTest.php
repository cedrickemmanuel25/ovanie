<?php

namespace Tests\Feature;

use App\Models\ProDocument;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProDemiGrossisteBackendTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
    }

    public function test_demi_grossiste_uses_its_dedicated_screen_and_backend_schema(): void
    {
        $user = $this->demiGrossisteUser();

        $this->actingAs($user, 'pro')
            ->get($this->proHost . '/compte/professionnel/demi_grossiste')
            ->assertOk()
            ->assertSee('Demi-grossiste')
            ->assertSee('quantity_tiers', false)
            ->assertSee('professional_proof', false)
            ->assertSee('Conditions commerciales');
    }

    public function test_empty_final_submission_is_rejected(): void
    {
        $user = $this->demiGrossisteUser();

        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/demi_grossiste')
            ->post($this->proHost . '/compte/professionnel/demi_grossiste', ['action' => 'submit'])
            ->assertSessionHasErrors([
                'legal_name', 'legal_form', 'registration_country', 'city', 'address',
                'representative_name', 'responsible_position', 'professional_phone', 'professional_email',
                'categories', 'origin_country', 'sale_units', 'minimum_quantity', 'minimum_quantity_unit',
                'supply_frequency', 'quantity_tiers', 'dispatch_country', 'dispatch_city',
                'cities_served', 'regions_served', 'countries_served', 'availability',
                'preparation_time', 'preparation_unit', 'restock_frequency', 'volume_negotiation',
                'documents.registry', 'documents.professional_proof',
            ]);
    }

    public function test_submit_persists_company_profile_data_and_documents(): void
    {
        $user = $this->demiGrossisteUser();

        $payload = [
            'action' => 'submit',
            'legal_name' => 'Demi Gros BTP SARL',
            'legal_form' => 'sarl',
            'registration_country' => 'Côte d’Ivoire',
            'registration_number' => 'CI-ABJ-2026-B-8877',
            'tax_number' => '1234567 Z',
            'city' => 'Abidjan',
            'address' => 'Zone industrielle de Yopougon',
            'representative_name' => 'Yao Kouakou',
            'responsible_position' => 'gerant',
            'professional_phone' => '+2250701020304',
            'professional_whatsapp' => '+2250701020304',
            'professional_email' => 'contact@demigros.test',
            'website' => 'https://demigros.test',
            'categories' => 'Fer à béton, Quincaillerie',
            'brands_origins' => 'Sika, Legrand',
            'origin_country' => 'Côte d’Ivoire',
            'sale_units' => 'Sacs, Pièces, Cartons',
            'minimum_quantity' => '10',
            'minimum_quantity_unit' => 'unites',
            'stock_capacity' => '5000',
            'stock_unit' => 'unites',
            'supply_frequency' => 'hebdomadaire',
            'quantity_tiers' => json_encode([
                ['from' => '10', 'to' => '49', 'unit' => 'unites'],
                ['from' => '50', 'to' => '99', 'unit' => 'unites'],
                ['from' => '100', 'to' => '', 'unit' => 'unites'],
            ]),
            'dispatch_country' => 'Côte d’Ivoire',
            'dispatch_city' => 'Abidjan',
            'dispatch_address' => 'Rue des Entrepôts',
            'dispatch_position' => '5.3453, -4.0053',
            'cities_served' => 'Abidjan, Bouaké, San-Pédro',
            'regions_served' => 'District d’Abidjan, Haut-Sassandra',
            'countries_served' => 'Côte d’Ivoire, Ghana, Mali',
            'availability' => 'stock',
            'preparation_time' => '24',
            'preparation_unit' => 'heures',
            'restock_frequency' => 'hebdomadaire',
            'volume_negotiation' => 'yes',
            'commercial_comment' => 'Remises selon les volumes et la disponibilité.',
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 120, 'application/pdf'),
                'professional_proof' => UploadedFile::fake()->create('bail-commercial.pdf', 120, 'application/pdf'),
            ],
        ];

        $this->actingAs($user, 'pro')
            ->post($this->proHost . '/compte/professionnel/demi_grossiste', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pro.roles.edit'));

        $profile = $user->fresh()->professionalProfile()->with('organization')->firstOrFail();
        $organization = $profile->organization;

        $this->assertSame('Demi Gros BTP SARL', $organization->legal_name);
        $this->assertSame('Côte d’Ivoire', $organization->registration_country);
        $this->assertSame('pending', $organization->verification_status);
        $this->assertSame('Fer à béton, Quincaillerie', $profile->profile_data['categories']);
        $this->assertSame('Abidjan', $profile->profile_data['dispatch_city']);
        $this->assertSame('yes', $profile->profile_data['volume_negotiation']);
        $this->assertSame('pending', $profile->status);

        foreach (['registry', 'professional_proof'] as $type) {
            $this->assertTrue(ProDocument::query()
                ->where('pro_organization_id', $organization->id)
                ->where('document_type', $type)
                ->exists(), "Document {$type} non persisté");
        }
    }

    public function test_quantity_tiers_are_validated_on_final_submission(): void
    {
        $user = $this->demiGrossisteUser();
        $payload = $this->minimalSubmissionPayload();
        $payload['quantity_tiers'] = json_encode([['from' => '100', 'to' => '50', 'unit' => 'unites']]);

        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/demi_grossiste')
            ->post($this->proHost . '/compte/professionnel/demi_grossiste', $payload)
            ->assertSessionHasErrors(['quantity_tiers']);
    }

    private function demiGrossisteUser(): ProUser
    {
        $user = ProUser::query()->create([
            'first_name' => 'Yao',
            'last_name' => 'Kouakou',
            'name' => 'Yao Kouakou',
            'email' => 'demi-grossiste@example.com',
            'phone' => '+2250701020304',
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
            'profile_key' => 'demi_grossiste',
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
            'legal_name' => 'Demi Gros BTP SARL', 'legal_form' => 'sarl', 'registration_country' => 'Côte d’Ivoire',
            'city' => 'Abidjan', 'address' => 'Yopougon', 'representative_name' => 'Yao Kouakou',
            'responsible_position' => 'gerant', 'professional_phone' => '+2250701020304', 'professional_email' => 'contact@demigros.test',
            'categories' => 'Quincaillerie', 'origin_country' => 'Côte d’Ivoire', 'sale_units' => 'Pièces',
            'minimum_quantity' => '10', 'minimum_quantity_unit' => 'unites', 'supply_frequency' => 'hebdomadaire',
            'quantity_tiers' => json_encode([['from' => '10', 'to' => '49', 'unit' => 'unites']]),
            'dispatch_country' => 'Côte d’Ivoire', 'dispatch_city' => 'Abidjan', 'cities_served' => 'Abidjan',
            'regions_served' => 'District d’Abidjan', 'countries_served' => 'Côte d’Ivoire',
            'availability' => 'stock', 'preparation_time' => '24', 'preparation_unit' => 'heures',
            'restock_frequency' => 'hebdomadaire', 'volume_negotiation' => 'yes',
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
                'professional_proof' => UploadedFile::fake()->create('preuve.pdf', 100, 'application/pdf'),
            ],
        ];
    }
}
