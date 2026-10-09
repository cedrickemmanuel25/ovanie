<?php

namespace Tests\Feature;

use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Parcours professionnel : « Parlez-nous de votre activité » puis « Informations métier » (Fabricant / Producteur). */
class ProBusinessOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
    }

    public function test_account_step_renders_and_creates_the_professional_account(): void
    {
        $url = $this->host . '/compte/inscription/pro';
        $this->get($url)->assertOk()->assertSee('Créons votre compte')->assertSee('Coordonnées')->assertSee('Votre mot de passe doit contenir');

        $this->post($url, ['last_name' => 'Diarra'])->assertSessionHasErrors(['first_name', 'phone', 'email', 'password', 'terms']);

        $this->post($url, [
            'last_name' => 'Diarra', 'first_name' => 'Moussa', 'residence_country' => 'Sénégal', 'phone_country' => '+221', 'phone' => '77 123 45 67',
            'email' => 'moussa@example.com', 'preferred_language' => 'fr', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => '1',
        ])->assertRedirect(route('pro.register.qualification'));

        $created = ProUser::query()->where('email', 'moussa@example.com')->firstOrFail();
        $this->assertSame('+221771234567', $created->phone);
        $this->assertSame('professional', $created->onboarding_path);
    }

    public function test_qualification_defaults_registration_country_and_can_save_and_quit(): void
    {
        $user = $this->user();
        $url = $this->host . '/compte/qualification';

        $this->actingAs($user, 'pro')->get($url)->assertOk()
            ->assertSee('Parlez-nous de votre activité')->assertSee('Ce que nous vous demanderons')->assertSee('Continuer avec ce profil');

        $this->actingAs($user, 'pro')->post($url, [
            'action' => 'continue', 'entity_type' => 'organization', 'professional_profile' => 'fabricant_producteur',
            'activity_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'],
        ])->assertRedirect(route('pro.register.professional.form', ['profile' => 'fabricant_producteur']));

        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertSame('Côte d’Ivoire', $profile->registration_country);

        $this->actingAs($user, 'pro')->post($url, ['action' => 'draft', 'quit' => '1', 'professional_profile' => 'fabricant_producteur'])
            ->assertRedirect(route('pro.login'));
        $this->assertGuest('pro');
    }

    public function test_fabricant_form_renders_five_tabs_saves_draft_and_submits(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'fabricant_producteur', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/fabricant_producteur';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Responsable du profil', 'Identité légale', 'Site de production', 'Conditions commerciales', 'Documents de l’entreprise', 'Valider et passer aux fonctions', 'Yamoussoukro'] as $text) {
            $page->assertSee($text);
        }

        // Brouillon partiel, avec correction du nom depuis l'onglet Responsable.
        $this->actingAs($user, 'pro')->post($url, [
            'action' => 'draft', 'identity_last_name' => 'Kouamé', 'identity_first_name' => 'Daniel', 'legal_name' => 'EIBTP SARL',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Kouamé', $user->fresh()->last_name);

        // Soumission incomplète : les erreurs reviennent et rien n'est soumis.
        $this->actingAs($user, 'pro')->post($url, ['action' => 'submit'])->assertSessionHasErrors(['factory_name', 'moq', 'documents.registry']);

        $data = [
            'action' => 'submit',
            'responsible_position' => 'Direction générale', 'professional_email' => 'contact@eibtp.ci', 'professional_phone' => '+2250712345678', 'professional_whatsapp' => '+2250712345678',
            'legal_name' => 'EIBTP SARL', 'trade_name' => 'EIBTP', 'registration_country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Zone industrielle de Yopougon',
            'legal_form' => 'sarl', 'registration_number' => 'CI-ABJ-2023-B-12345', 'tax_number' => '1234567 F', 'founded_year' => '2015',
            'sector' => 'Matériaux de construction', 'product_categories' => 'Ciment, Briques et parpaings',
            'factory_name' => 'Usine de Yopougon', 'factory_country' => 'Côte d’Ivoire', 'factory_city' => 'Yopougon', 'factory_address' => 'Rue des Usines, lot 12',
            'factory_position' => '5.3453, -4.0053', 'factory_founded_year' => '2016', 'factory_area' => '5000', 'factory_area_unit' => 'm2', 'production_lines' => '3',
            'capacity_monthly' => '1000', 'capacity_monthly_unit' => 'tonnes', 'capacity_annual' => '12000', 'capacity_annual_unit' => 'tonnes',
            'main_equipment' => 'Concasseur à mâchoires · Liebherr · ×2 · 2021', 'products_manufactured' => 'Ciment, Granulats', 'markets_served' => 'Marché local et sous-régional',
            // MOQ « Sur demande » : aucune valeur chiffrée n'est envoyée.
            'moq_mode' => 'on_request', 'samples' => 'yes', 'production_lead_time' => '10', 'production_lead_time_unit' => 'jours', 'production_lead_time_mode' => 'fixed',
            'countries_served' => 'Côte d’Ivoire, Ghana', 'export_enabled' => 'yes', 'export_countries' => 'Ghana', 'shipping_modes' => ['terrestre', 'maritime'], 'incoterms' => 'EXW, FOB',
            'representative_id_type' => 'cni', 'representative_id_number' => 'CI0012345678',
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
                'tax_certificate' => UploadedFile::fake()->create('dfe.pdf', 200, 'application/pdf'),
                'representative_id' => UploadedFile::fake()->image('cni.jpg'),
                'factory_photos' => [UploadedFile::fake()->image('usine.jpg')],
            ],
        ];

        // Export « Oui » sans mode d'expédition : refusé.
        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['shipping_modes' => []]))->assertSessionHasErrors('shipping_modes');

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));

        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('Sur demande', $profile->profile_data['moq']);
        $this->assertSame('tonnes', $profile->profile_data['capacity_monthly_unit']);
        $this->assertSame('EIBTP SARL', $profile->organization->legal_name);
        $this->assertSame(4, $profile->organization->documents()->count());

        // Au retour, les documents déjà fournis ne sont plus redemandés.
        $this->actingAs($user->fresh(), 'pro')->get($url)->assertOk()->assertSee('Fourni');
    }

    public function test_grossiste_form_renders_and_submits_with_warehouses_and_trade_rules(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'grossiste', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/grossiste';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Identité légale', 'Catalogue et distribution', 'Lieu principal d’expédition', 'Commerce international', 'Documents légaux', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $warehouse = ['name' => 'Abidjan Principal', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Zone industrielle de Yopougon', 'type' => 'principal', 'capacity' => '5000', 'capacity_unit' => 'm2'];
        $data = [
            'action' => 'submit',
            'legal_name' => 'Distribution BTP SARL', 'registration_country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Zone industrielle de Yopougon',
            'legal_form' => 'sarl', 'registration_number' => 'CI-ABJ-2023-B-12345', 'tax_number' => '1234567 F',
            'representative_name' => 'Kouassi Jean Marc', 'responsible_position' => 'gerant', 'professional_phone' => '+2250712345678', 'professional_email' => 'contact@dbtp.ci',
            'categories_sold' => 'Ciment, Fer à béton', 'goods_origin' => 'Côte d’Ivoire, Chine', 'distribution_status' => 'distributeur',
            'stock_capacity' => '5000', 'stock_unit' => 'tonnes', 'sale_units' => 'tonnes', 'moq' => '1', 'moq_unit' => 'tonnes',
            'sales_volume' => '500', 'sales_volume_unit' => 'tonnes', 'volume_periodicity' => 'mensuel', 'zones_served' => 'Afrique de l’Ouest', 'countries_served' => 'Côte d’Ivoire, Ghana',
            'dispatch_country' => 'Côte d’Ivoire', 'dispatch_city' => 'Abidjan', 'dispatch_address' => 'Rue des Entrepôts', 'has_warehouses' => 'yes', 'warehouses' => json_encode([$warehouse]),
            'preparation_lead_time' => '24', 'preparation_lead_unit' => 'heures', 'preparation_workdays' => ['lun', 'mar', 'mer', 'jeu', 'ven'],
            'trade_flows' => 'both', 'supply_countries' => 'Chine', 'destination_countries' => 'Ghana', 'transport_modes' => ['maritime'],
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
                'tax_certificate' => UploadedFile::fake()->create('dfe.pdf', 200, 'application/pdf'),
            ],
        ];

        // Import déclaré sans pays d'approvisionnement, ou « Oui » sans entrepôt : refusé.
        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['supply_countries' => '']))->assertSessionHasErrors('supply_countries');
        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['warehouses' => '']))->assertSessionHasErrors('warehouses');

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('Distribution BTP SARL', $profile->organization->legal_name);
        $this->assertSame('Abidjan Principal', json_decode($profile->profile_data['warehouses'], true)[0]['name']);
    }

    public function test_demi_grossiste_form_renders_and_submits_with_quantity_tiers(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'demi_grossiste', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/demi_grossiste';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Identité professionnelle', 'Activité en semi-gros', 'Paliers de quantité', 'Origine d’expédition', 'Conditions commerciales', 'Justificatifs', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $data = [
            'action' => 'submit',
            'legal_name' => 'Distributions Express', 'legal_form' => 'sarl', 'registration_country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Rue des Entrepôts',
            'representative_name' => 'Koné Awa', 'responsible_position' => 'Gérant', 'professional_phone' => '+2250712345678', 'professional_email' => 'contact@dexpress.ci',
            'categories' => 'Fer à béton, Peintures', 'origin_country' => 'Côte d’Ivoire', 'sale_units' => 'Sacs, Cartons', 'minimum_quantity' => '10', 'minimum_quantity_unit' => 'Unités',
            'supply_frequency' => 'hebdomadaire', 'quantity_tiers' => json_encode([['from' => '10', 'to' => '49', 'unit' => 'Unités'], ['from' => '50', 'to' => '', 'unit' => 'Unités']]),
            'dispatch_country' => 'Côte d’Ivoire', 'dispatch_city' => 'Abidjan', 'cities_served' => 'Abidjan, Bouaké', 'regions_served' => 'District d’Abidjan', 'countries_served' => 'Côte d’Ivoire',
            'availability' => 'stock', 'preparation_time' => '24', 'preparation_unit' => 'heures', 'restock_frequency' => 'hebdomadaire', 'volume_negotiation' => 'yes',
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
                'professional_proof' => UploadedFile::fake()->create('bail.pdf', 200, 'application/pdf'),
            ],
        ];

        // Palier dont la fin est inférieure au début : refusé.
        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['quantity_tiers' => json_encode([['from' => '50', 'to' => '10', 'unit' => 'Unités']])]))
            ->assertSessionHasErrors('quantity_tiers');

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertCount(2, json_decode($profile->profile_data['quantity_tiers'], true));
    }

    public function test_revendeur_form_renders_and_submits(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'revendeur_professionnel', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/revendeur_professionnel';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Identité professionnelle', 'Catégories achetées', 'Activité commerciale', 'Besoins d’approvisionnement', 'Documents légaux', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $data = [
            'action' => 'submit',
            'legal_name' => 'Quincaillerie Pro Services', 'registration_country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Zone 4, rue des Commerces',
            'representative_name' => 'Kouassi Jean', 'responsible_position' => 'Gérant / Fondateur', 'professional_phone' => '+2250712345678', 'professional_email' => 'contact@qps.ci',
            'purchase_categories' => 'Quincaillerie, Électricité', 'resale_categories' => 'Outillage', 'sourcing_countries' => 'Côte d’Ivoire, Chine',
            'sales_zones' => 'Abidjan, National', 'purchase_frequency' => 'mensuelle', 'importer' => 'yes', 'commercial_supply_countries' => 'Chine',
            'needs' => ['rfq', 'find_suppliers'],
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
                'professional_proof' => UploadedFile::fake()->create('preuve.pdf', 200, 'application/pdf'),
            ],
        ];

        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['needs' => []]))->assertSessionHasErrors('needs');

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame(['rfq', 'find_suppliers'], $profile->profile_data['needs']);
    }

    public function test_entreprise_btp_form_renders_and_submits(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'entreprise_btp', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/entreprise_btp';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Identité légale', 'Spécialités et travaux', 'Achats professionnels', 'Contacts clés', 'Usages souhaités', 'Documents de l’entreprise', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $data = [
            'action' => 'submit',
            'legal_name' => 'BTP Africa Construction SARL', 'legal_form' => 'sarl', 'registration_number' => 'CI-ABJ-2023-B-12345', 'city' => 'Abidjan', 'address' => 'Plateau, avenue Jean-Paul II',
            'responsible_position' => 'Direction générale', 'professional_email' => 'contact@btpafrica.ci', 'professional_phone' => '+2250712345678',
            'specialties' => 'Bâtiment résidentiel, Génie civil', 'intervention_zones' => 'Côte d’Ivoire, Ghana', 'work_types' => ['batiment', 'genie_civil'],
            'purchase_categories' => 'Ciment, Fer à béton', 'purchase_scope' => ['local'], 'btp_functions' => ['rfq', 'orders'],
            'documents' => [
                'registry' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
                'representative_id' => UploadedFile::fake()->image('cni.jpg'),
            ],
        ];

        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['work_types' => []]))->assertSessionHasErrors('work_types');

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('BTP Africa Construction SARL', $profile->organization->legal_name);
        $this->assertSame(['batiment', 'genie_civil'], $profile->profile_data['work_types']);
    }

    public function test_artisan_form_renders_and_submits_as_independent_or_company(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'entrepreneur_artisan', 'entity_type' => 'independent',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['services'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/entrepreneur_artisan';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Vous exercez comme', 'Métier principal', 'Zones d’intervention', 'Réalisations', 'Qualifications', 'Pièce d’identité', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $data = [
            'action' => 'submit', 'profile_entity_type' => 'independent', 'independent_status' => 'artisan_independant',
            'registration_country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'professional_phone' => '+2250712345678', 'professional_email' => 'moussa@example.com',
            'main_trade' => 'electricien', 'specialties' => ['tableaux_electriques'], 'experience_level' => '5_10', 'services' => ['installation'],
            'intervention_zones' => 'Abidjan', 'availability' => 'maintenant', 'team_size' => 'solo', 'intervention_capacity' => 'petits_travaux',
            'references_json' => json_encode([['title' => 'Villa', 'type' => 'Installation', 'location' => 'Abidjan', 'year' => '2025']]),
            'references_completed' => '1', 'qualifications_completed' => '1',
            'documents' => [
                'representative_id' => UploadedFile::fake()->image('cni.jpg'),
                'professional_document' => UploadedFile::fake()->create('carte.pdf', 200, 'application/pdf'),
                'activity_proof' => UploadedFile::fake()->create('facture.pdf', 200, 'application/pdf'),
            ],
        ];

        // En structure, la raison sociale et la forme juridique deviennent obligatoires.
        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['profile_entity_type' => 'organization']))->assertSessionHasErrors(['legal_name', 'legal_form']);

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('independent', $profile->entity_type);
        $this->assertSame('electricien', $profile->profile_data['main_trade']);
    }

    public function test_promoteur_form_renders_and_submits(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'promoteur_immobilier', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/promoteur_immobilier';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Identité légale', 'Segments immobiliers', 'Types de programmes', 'Projets en cours', 'Consultation des fournisseurs', 'Vérification du profil', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $data = [
            'action' => 'submit',
            'legal_name' => 'SIPIM SA', 'legal_form' => 'sa', 'registration_country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Cocody, rue des Jardins',
            'registration_number' => 'CI-ABJ-2024-B-12345', 'tax_number' => '1234567 F',
            'representative_name' => 'Kouamé Jean Marc', 'responsible_position' => 'Direction générale', 'professional_phone' => '+2250712345678', 'professional_email' => 'contact@sipim.ci',
            'real_estate_activities' => ['promoteur'], 'activity_zones' => 'Abidjan', 'real_estate_segments' => ['residentiel'],
            'project_zones' => 'Abidjan', 'program_types' => ['residentiel'],
            'current_projects' => json_encode([['name' => 'Résidence Horizon', 'type' => 'Résidentiel', 'location' => 'Cocody']]),
            'purchase_categories' => 'Ciment, Carrelage', 'purchase_local_countries' => 'Côte d’Ivoire', 'rfq_usage' => 'regular', 'tender_usage' => 'frequent',
            'documents' => [
                'company_documents' => [UploadedFile::fake()->create('statuts.pdf', 200, 'application/pdf')],
                'registry' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
                'tax_certificate' => UploadedFile::fake()->create('fiscal.pdf', 200, 'application/pdf'),
                'professional_proofs' => [UploadedFile::fake()->create('references.pdf', 200, 'application/pdf')],
            ],
        ];

        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['program_types' => []]))->assertSessionHasErrors('program_types');

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('Résidence Horizon', json_decode($profile->profile_data['current_projects'], true)[0]['name']);
    }

    public function test_prestataire_form_renders_and_submits_as_independent(): void
    {
        $user = $this->user();
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => 'prestataire_btp', 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['services'], 'status' => 'draft',
        ]);
        $url = $this->host . '/compte/professionnel/prestataire_btp';

        $page = $this->actingAs($user, 'pro')->get($url)->assertOk();
        foreach (['Vous exercez comme', 'Catégorie de service', 'Zones d’intervention', 'Liste des équipements', 'Références', 'Qualifications', 'Tarification', 'Documents professionnels', 'Valider et passer aux fonctions'] as $text) {
            $page->assertSee($text);
        }

        $data = [
            'action' => 'submit', 'profile_entity_type' => 'independent',
            'trade_name' => 'Traoré Élec', 'independent_status' => 'artisan', 'city' => 'Abidjan',
            'professional_email' => 'ali@example.com', 'professional_phone' => '+2250712345678', 'professional_whatsapp' => '+2250712345678',
            'service_category' => 'installation', 'service_subcategory' => 'Électricité', 'service_title' => 'Installation de systèmes électriques',
            'services_description' => 'Installation de réseaux électriques tertiaires.', 'skills' => 'Câblage, Mise en service',
            'intervention_zones' => 'Abidjan', 'availability' => 'Disponible immédiatement', 'team_capacity' => 'Intervention légère', 'team_size' => '1 personne', 'indicative_lead_time' => 'Moins de 24 h',
            'means_types' => 'Équipements', 'equipment_json' => json_encode([['name' => 'Perceuse', 'type' => 'Outillage', 'ownership' => 'Propriété']]),
            'references_json' => '[]', 'references_status' => 'completed', 'qualifications_json' => '[]', 'qualifications_status' => 'completed',
            'pricing_mode' => 'quote',
            'documents' => ['professional_documents' => [UploadedFile::fake()->create('attestation.pdf', 200, 'application/pdf')]],
        ];

        // En entreprise, les informations légales deviennent obligatoires.
        $this->actingAs($user, 'pro')->post($url, array_merge($data, ['profile_entity_type' => 'organization']))->assertSessionHasErrors(['legal_name', 'legal_form', 'registration_number']);

        $this->actingAs($user, 'pro')->post($url, $data)->assertRedirect(route('pro.roles.edit'));
        $profile = ProProfessionalProfile::query()->firstOrFail();
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('independent', $profile->entity_type);
        $this->assertSame('installation', $profile->profile_data['service_category']);
    }

    private function user(): ProUser
    {
        return ProUser::query()->create([
            'first_name' => 'Daniel', 'last_name' => 'Kouame', 'name' => 'Daniel Kouame', 'email' => 'daniel@example.com', 'phone' => '+2250700000009',
            'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional',
            'status' => 'active', 'professional_status' => 'not_started',
        ]);
    }
}
