<?php

namespace Tests\Feature;

use App\Models\ProDocument;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProEntrepreneurArtisanBackendTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
    }

    public function test_entrepreneur_artisan_uses_dedicated_screen(): void
    {
        $user = $this->artisanUser();

        $this->actingAs($user, 'pro')
            ->get($this->proHost . '/compte/professionnel/entrepreneur_artisan')
            ->assertOk()
            ->assertSee('Entrepreneur / Artisan professionnel')
            ->assertSee('Identité professionnelle')
            ->assertSee('Métier')
            ->assertSee('Intervention')
            ->assertSee('Réalisations')
            ->assertSee('Qualifications')
            ->assertSee('Vérification')
            ->assertSee('profile_entity_type', false)
            ->assertSee('documents[representative_id]', false);
    }

    public function test_empty_final_submission_is_rejected(): void
    {
        $user = $this->artisanUser();

        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/entrepreneur_artisan')
            ->post($this->proHost . '/compte/professionnel/entrepreneur_artisan', [
                'action' => 'submit',
                'profile_entity_type' => 'independent',
            ])
            ->assertSessionHasErrors([
                'independent_status', 'registration_country', 'city', 'professional_phone', 'professional_email',
                'main_trade', 'specialties', 'experience_level', 'services',
                'intervention_zones', 'availability', 'team_size', 'intervention_capacity',
                'references_completed', 'qualifications_completed',
                'documents.representative_id', 'documents.professional_document', 'documents.activity_proof',
            ]);
    }

    public function test_submit_persists_artisan_profile_and_documents(): void
    {
        $user = $this->artisanUser();

        $payload = [
            'action' => 'submit',
            'profile_entity_type' => 'independent',
            'trade_name' => 'Kouakou Électricité Services',
            'independent_status' => 'artisan_independant',
            'registration_country' => 'Côte d’Ivoire',
            'city' => 'Abidjan',
            'address' => 'Cocody, Riviera 3',
            'professional_phone' => '+2250701020304',
            'professional_whatsapp' => '+2250701020304',
            'professional_email' => 'artisan@example.com',
            'main_trade' => 'electricien',
            'specialties' => ['installation_batiment', 'cablage', 'depannage'],
            'experience_level' => '5_10',
            'services' => ['installation', 'maintenance', 'depannage'],
            'intervention_zones' => 'Abidjan, Cocody, Yopougon',
            'availability' => 'maintenant',
            'team_size' => 'solo',
            'intervention_capacity' => 'petits_travaux',
            'client_types' => ['particuliers', 'entreprises'],
            'references_json' => json_encode([[
                'title' => 'Installation électrique villa',
                'type' => 'Installation',
                'location' => 'Abidjan, Côte d’Ivoire',
                'year' => '2026',
                'description' => 'Installation complète du réseau électrique.',
                'client' => 'Client privé',
            ]]),
            'references_completed' => '1',
            'qualifications_completed' => '1',
            'documents' => [
                'representative_id' => UploadedFile::fake()->create('cni.pdf', 120, 'application/pdf'),
                'professional_document' => UploadedFile::fake()->create('carte-artisan.pdf', 120, 'application/pdf'),
                'activity_proof' => UploadedFile::fake()->create('facture-chantier.pdf', 120, 'application/pdf'),
            ],
        ];

        $this->actingAs($user, 'pro')
            ->post($this->proHost . '/compte/professionnel/entrepreneur_artisan', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pro.roles.edit'));

        $profile = $user->fresh()->professionalProfile()->with('organization')->firstOrFail();
        $organization = $profile->organization;

        $this->assertSame('independent', $profile->entity_type);
        $this->assertSame('Kouakou Électricité Services', $organization->trade_name);
        $this->assertSame('Abidjan', $organization->city);
        $this->assertSame('pending', $organization->verification_status);
        $this->assertSame('electricien', $profile->profile_data['main_trade']);
        $this->assertSame(['installation_batiment', 'cablage', 'depannage'], $profile->profile_data['specialties']);
        $this->assertSame('pending', $profile->status);

        foreach (['representative_id', 'professional_document', 'activity_proof'] as $type) {
            $this->assertTrue(ProDocument::query()
                ->where('pro_organization_id', $organization->id)
                ->where('document_type', $type)
                ->exists(), "Document {$type} non persisté");
        }
    }

    public function test_user_can_switch_from_independent_to_organization(): void
    {
        $user = $this->artisanUser();

        $this->actingAs($user, 'pro')
            ->post($this->proHost . '/compte/professionnel/entrepreneur_artisan', [
                'action' => 'draft',
                'profile_entity_type' => 'organization',
                'legal_name' => 'Kouakou BTP SARL',
                'legal_form' => 'sarl',
                'registration_country' => 'Côte d’Ivoire',
                'city' => 'Abidjan',
                'professional_phone' => '+2250701020304',
                'professional_email' => 'contact@kouakoubtp.test',
            ])
            ->assertSessionHasNoErrors();

        $profile = $user->fresh()->professionalProfile()->with('organization')->firstOrFail();
        $this->assertSame('organization', $profile->entity_type);
        $this->assertSame('Kouakou BTP SARL', $profile->organization->legal_name);
    }

    private function artisanUser(): ProUser
    {
        $user = ProUser::query()->create([
            'first_name' => 'Yao',
            'last_name' => 'Kouakou',
            'name' => 'Yao Kouakou',
            'email' => 'artisan@example.com',
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
            'profile_key' => 'entrepreneur_artisan',
            'entity_type' => 'independent',
            'activity_country' => 'Côte d’Ivoire',
            'registration_country' => 'Côte d’Ivoire',
            'desired_functions' => ['services'],
            'status' => 'draft',
        ]);

        return $user->fresh();
    }
}
