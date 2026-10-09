<?php

namespace Tests\Feature;

use App\Models\ProDocument;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProRevendeurProfessionnelBackendTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
    }

    public function test_revendeur_uses_dedicated_screen_and_schema(): void
    {
        $user = $this->revendeurUser();

        $this->actingAs($user, 'pro')
            ->get($this->proHost . '/compte/professionnel/revendeur_professionnel')
            ->assertOk()
            ->assertSee('Revendeur professionnel')
            ->assertSee('Approvisionnement produits')
            ->assertSee('Activité commerciale')
            ->assertSee('Besoins d’approvisionnement')
            ->assertSee('commercial_supply_countries', false)
            ->assertSee('documents[registry]', false);
    }

    public function test_empty_final_submission_is_rejected(): void
    {
        $user = $this->revendeurUser();

        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/revendeur_professionnel')
            ->post($this->proHost . '/compte/professionnel/revendeur_professionnel', ['action' => 'submit'])
            ->assertSessionHasErrors([
                'legal_name', 'registration_country', 'city', 'address', 'representative_name',
                'responsible_position', 'professional_phone', 'professional_email',
                'purchase_categories', 'resale_categories', 'sourcing_countries',
                'sales_zones', 'purchase_frequency', 'importer', 'commercial_supply_countries',
                'needs', 'documents.registry', 'documents.professional_proof',
            ]);
    }

    public function test_submit_persists_revendeur_data_and_required_documents(): void
    {
        $user = $this->revendeurUser();

        $payload = [
            'action' => 'submit',
            'legal_name' => 'Quincaillerie Pro Services SARL',
            'registration_country' => 'Côte d’Ivoire',
            'city' => 'Abidjan',
            'address' => 'Zone 4, Rue des Commerces',
            'registration_number' => 'CI-ABJ-2026-B-12345',
            'tax_number' => '1234567 F',
            'representative_name' => 'Yao Kouakou',
            'responsible_position' => 'gerant',
            'professional_phone' => '+2250701020304',
            'professional_whatsapp' => '+2250701020304',
            'professional_email' => 'contact@revendeur.test',
            'purchase_categories' => 'Quincaillerie, Électricité, Peinture',
            'resale_categories' => 'Électricité, Outillage, Plomberie',
            'sourcing_countries' => 'Côte d’Ivoire, Ghana, Turquie, Chine',
            'usual_suppliers' => 'Würth, Rexel',
            'usual_brands' => 'Legrand, Schneider, Sika',
            'sales_zones' => 'Abidjan, Bouaké, National',
            'purchase_volumes' => '5000',
            'purchase_volume_unit' => 'cartons',
            'purchase_volume_period' => 'mensuelle',
            'purchase_frequency' => 'mensuelle',
            'importer' => 'yes',
            'commercial_supply_countries' => 'Côte d’Ivoire, Chine, Turquie, Maroc, Ghana',
            'needs' => ['rfq', 'find_suppliers', 'compare_offers', 'international_sourcing'],
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm-revendeur.pdf', 120, 'application/pdf'),
                'professional_proof' => UploadedFile::fake()->create('justificatif-activite.pdf', 120, 'application/pdf'),
            ],
        ];

        $this->actingAs($user, 'pro')
            ->post($this->proHost . '/compte/professionnel/revendeur_professionnel', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pro.roles.edit'));

        $profile = $user->fresh()->professionalProfile()->with('organization')->firstOrFail();
        $organization = $profile->organization;

        $this->assertSame('Quincaillerie Pro Services SARL', $organization->legal_name);
        $this->assertSame('Côte d’Ivoire', $organization->registration_country);
        $this->assertSame('pending', $organization->verification_status);
        $this->assertSame('Quincaillerie, Électricité, Peinture', $profile->profile_data['purchase_categories']);
        $this->assertSame('mensuelle', $profile->profile_data['purchase_frequency']);
        $this->assertSame(['rfq', 'find_suppliers', 'compare_offers', 'international_sourcing'], $profile->profile_data['needs']);
        $this->assertSame('pending', $profile->status);

        foreach (['registry', 'professional_proof'] as $type) {
            $this->assertTrue(ProDocument::query()
                ->where('pro_organization_id', $organization->id)
                ->where('document_type', $type)
                ->exists(), "Document {$type} non persisté");
        }
    }

    private function revendeurUser(): ProUser
    {
        $user = ProUser::query()->create([
            'first_name' => 'Yao',
            'last_name' => 'Kouakou',
            'name' => 'Yao Kouakou',
            'email' => 'revendeur@example.com',
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
            'profile_key' => 'revendeur_professionnel',
            'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire',
            'registration_country' => 'Côte d’Ivoire',
            'desired_functions' => ['buy', 'sell'],
            'status' => 'draft',
        ]);

        return $user->fresh();
    }
}
