<?php

namespace Tests\Feature;

use App\Models\ProCompanyMembership;
use App\Models\ProOrganization;
use App\Models\ProPasswordResetCode;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Fichier temporaire : rend les écrans pour capture visuelle (base SQLite en mémoire). */
class TmpRenderProAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Outil de capture d'écran : ne s'exécute que si un dossier de sortie est fourni (OA_OUT).
        if (! getenv('OA_OUT')) {
            $this->markTestSkipped('Outil de rendu : définir OA_OUT pour l’exécuter.');
        }
    }

    public function test_render(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $out = getenv('OA_OUT');
        $save = function (string $name, $response) use ($out) {
            $html = $response->getContent();
            $html = preg_replace('#https?://[^/"\']+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $html);
            file_put_contents($out . '/' . $name . '.html', $html);
        };

        $save('login', $this->get($host . '/compte/connexion'));
        $save('forgot', $this->get($host . '/compte/mot-de-passe-oublie'));
        $save('forgot-sent', $this->withSession(['reset_challenge' => str_repeat('a', 48)])->get($host . '/compte/mot-de-passe-oublie'));

        $admin = ProUser::query()->create(['first_name' => 'Jean Marc', 'last_name' => 'Kouassi', 'name' => 'Kouassi Jean Marc', 'email' => 'admin@btp.test', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'pending']);
        $challenge = str_repeat('b', 48);
        ProPasswordResetCode::query()->create(['pro_user_id' => $admin->id, 'challenge_hash' => hash('sha256', $challenge), 'code_hash' => Hash::make('473921'), 'channel' => 'mail', 'attempts' => 0, 'expires_at' => now()->addMinutes(10)]);
        $save('reset', $this->get($host . '/compte/reinitialiser/' . $challenge));
        $expired = str_repeat('c', 48);
        ProPasswordResetCode::query()->create(['pro_user_id' => $admin->id, 'challenge_hash' => hash('sha256', $expired), 'code_hash' => Hash::make('821003'), 'channel' => 'mail', 'attempts' => 0, 'expires_at' => now()->subMinute()]);
        $save('reset-expired', $this->get($host . '/compte/reinitialiser/' . $expired));
        $save('reset-success', $this->withSession(['password_reset_success' => true])->get($host . '/compte/reinitialiser'));

        $org = ProOrganization::query()->create(['owner_pro_user_id' => $admin->id, 'entity_type' => 'organization', 'legal_name' => 'BTP Afrique Construction SARL', 'country' => 'Côte d’Ivoire', 'verification_status' => 'pending']);
        ProCompanyMembership::query()->create(['pro_user_id' => $admin->id, 'pro_organization_id' => $org->id, 'organization_name' => $org->display_name, 'permission' => 'administrator', 'status' => 'active']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $admin->id, 'pro_organization_id' => $org->id, 'profile_key' => 'entreprise_btp', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'responsible_position' => 'Directeur des opérations', 'status' => 'pending', 'submitted_at' => now()]);
        $token = str_repeat('d', 40);
        $org->invitations()->create(['inviter_pro_user_id' => $admin->id, 'organization_name' => $org->display_name, 'email' => 'moussa@btp.test', 'permission' => 'site_manager', 'token_hash' => hash('sha256', $token), 'status' => 'pending', 'expires_at' => now()->addDays(7)]);
        $save('invitation', $this->get($host . '/compte/invitation/' . $token));
        $save('invitation-expired', $this->get($host . '/compte/invitation/' . str_repeat('z', 40)));

        $admin->roles()->create(['role' => 'buyer_pro']);
        $save('roles', $this->actingAs($admin, 'pro')->withSession(['_old_input' => ['roles' => ['buyer_pro', 'seller_supplier', 'requester']]])->get($host . '/compte/roles'));

        $this->assertTrue(true);
    }

    public function test_render_publication(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $out = getenv('OA_OUT');
        $save = function (string $name, $response) use ($out) {
            $html = $response->getContent();
            $html = preg_replace('#https?://[^/"\']+/storage/pro/publications/\d+/photos/[a-z]+-\d\.jpg#', 'file:///' . $out . '/__IMG__', $html);
            $html = preg_replace_callback('#file:///' . preg_quote($out, '#') . '/__IMG__#', fn () => 'file:///' . $out . '/' . (str_contains($html, 'blocs') ? 'blocs.jpg' : 'ciment.jpg'), $html);
            $html = preg_replace('#https?://[^/"\']+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $html);
            file_put_contents($out . '/' . $name . '.html', $html);
        };

        $save('pub-start', $this->get($host . '/compte/publier?type=offre'));
        $save('pub-account-offre', $this->get($host . '/compte/publier/offre/compte'));
        $save('pub-account-besoin', $this->get($host . '/compte/publier/besoin/compte'));

        $root = \App\Models\Category::query()->create(['name' => 'Matériaux de construction', 'slug' => 'materiaux-construction', 'status' => 'actif', 'is_active' => true]);
        $ciment = \App\Models\Category::query()->create(['name' => 'Ciment', 'slug' => 'ciment', 'parent_id' => $root->id, 'status' => 'actif', 'is_active' => true]);
        $blocs = \App\Models\Category::query()->create(['name' => 'Blocs béton', 'slug' => 'blocs-beton', 'parent_id' => $root->id, 'status' => 'actif', 'is_active' => true]);
        $user = ProUser::query()->create(['first_name' => 'Moussa', 'last_name' => 'Diarra', 'name' => 'Moussa Diarra', 'email' => 'moussa@test.ci', 'phone' => '+2250712345678', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'simple', 'onboarding_path' => 'simple', 'status' => 'active', 'professional_status' => 'not_started']);

        $offer = new \App\Models\ProPublication([
            'nature' => 'product', 'title' => 'Lot de 500 sacs de ciment disponibles à Abidjan', 'category_id' => $root->id, 'subcategory_id' => $ciment->id,
            'description' => "Nous proposons un stock de ciment disponible immédiatement à Abidjan. Produits en bon état, conditionnés en sacs de 50 kg.\nPossibilité de livraison selon la quantité. N’hésitez pas à nous contacter pour plus d’informations.",
            'quantity' => 500, 'unit' => 'sac_50', 'price' => 5500, 'currency' => 'XOF', 'price_negotiable' => true, 'min_order_quantity' => 50, 'min_order_unit' => 'sac_50',
            'availability' => 'immediate', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'location_detail' => 'Zone industrielle de Yopougon', 'validity_days' => 30, 'expires_on' => now()->addDays(30),
        ]);
        $offer->forceFill(['pro_user_id' => $user->id, 'kind' => 'offer', 'status' => 'draft'])->save();
        foreach ([1, 2, 3] as $i) {
            $offer->files()->create(['type' => 'photo', 'disk' => 'public', 'path' => "pro/publications/{$offer->id}/photos/ciment-{$i}.jpg", 'original_name' => "ciment-{$i}.jpg", 'is_main' => $i === 1, 'sort_order' => $i]);
        }
        $offer->files()->create(['type' => 'document', 'disk' => 'local', 'path' => 'x', 'original_name' => 'Fiche technique.pdf', 'size' => 2400000, 'sort_order' => 9]);

        $save('pub-form-offre', $this->actingAs($user, 'pro')->get($host . "/compte/publier/offre/{$offer->id}/modifier"));
        $save('pub-review-offre', $this->actingAs($user, 'pro')->get($host . "/compte/publier/offre/{$offer->id}/verification"));

        $need = new \App\Models\ProPublication([
            'nature' => 'product', 'title' => 'Recherche 1 000 blocs béton pour chantier à Cocody', 'category_id' => $root->id, 'subcategory_id' => $blocs->id,
            'description' => 'Je recherche 1 000 blocs béton creux pour un chantier à Cocody. Les blocs doivent être de bonne qualité, conformes aux standards et disponibles dans les meilleurs délais.',
            'quantity' => 1000, 'unit' => 'bloc', 'deadline' => '15', 'specifications' => 'Blocs béton creux standard 15x20x40 cm ou équivalent. Bonne qualité et résistance.',
            'alternatives_accepted' => true, 'budget_mode' => 'proposals', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'location_detail' => 'Cocody',
        ]);
        $need->forceFill(['pro_user_id' => $user->id, 'kind' => 'need', 'status' => 'draft'])->save();
        foreach ([1, 2, 3] as $i) {
            $need->files()->create(['type' => 'photo', 'disk' => 'public', 'path' => "pro/publications/{$need->id}/photos/blocs-{$i}.jpg", 'original_name' => "blocs-{$i}.jpg", 'is_main' => $i === 1, 'sort_order' => $i]);
        }
        $need->files()->create(['type' => 'document', 'disk' => 'local', 'path' => 'y', 'original_name' => 'Plan_chantier.pdf', 'size' => 2400000, 'sort_order' => 9]);

        $save('pub-form-besoin', $this->actingAs($user, 'pro')->get($host . "/compte/publier/besoin/{$need->id}/modifier"));
        $save('pub-review-besoin', $this->actingAs($user, 'pro')->get($host . "/compte/publier/besoin/{$need->id}/verification"));

        $this->assertTrue(true);
    }

    public function test_render_business(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $out = getenv('OA_OUT');
        $save = function (string $name, $response) use ($out) {
            $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $response->getContent());
            file_put_contents($out . '/' . $name . '.html', $html);
        };
        $save('biz-register', $this->get($host . '/compte/inscription/pro'));
        $user = ProUser::query()->create(['first_name' => 'Daniel', 'last_name' => 'Kouame', 'name' => 'Daniel Kouame', 'email' => 'daniel@test.ci', 'phone' => '+2250712345678', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        $save('biz-qualification', $this->actingAs($user, 'pro')->get($host . '/compte/qualification'));

        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'fabricant_producteur', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'], 'status' => 'draft',
            'responsible_position' => 'Direction générale', 'professional_email' => 'contact@eibtp.ci', 'professional_phone' => '+2250712345678',
            'profile_data' => ['product_categories' => 'Ciment, Briques et parpaings, Béton prêt à l’emploi', 'main_equipment' => 'Concasseur à mâchoires · Liebherr · ×2 · 2021, Silo de stockage', 'products_manufactured' => 'Ciment, Granulats', 'countries_served' => 'Côte d’Ivoire, Ghana, Mali', 'export_enabled' => 'yes', 'export_countries' => 'Ghana, Togo', 'incoterms' => 'EXW, FOB, CIF', 'shipping_modes' => ['terrestre', 'maritime'], 'samples' => 'yes', 'moq' => '25', 'production_lead_time' => '10']]);
        $save('biz-form', $this->actingAs($user->fresh(), 'pro')->get($host . '/compte/professionnel/fabricant_producteur'));
        $this->assertTrue(true);
    }

    public function test_render_grossiste(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $out = getenv('OA_OUT');
        $user = ProUser::query()->create(['first_name' => 'Jean Marc', 'last_name' => 'Kouassi', 'name' => 'Jean Marc Kouassi', 'email' => 'jm@test.ci', 'phone' => '+2250712345679', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'grossiste', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'], 'status' => 'draft',
            'profile_data' => ['categories_sold' => 'Ciment, Fer à béton', 'brands' => 'Dangote, Sika', 'goods_origin' => 'Côte d’Ivoire, Chine', 'distribution_status' => 'distributeur', 'zones_served' => 'Afrique de l’Ouest', 'countries_served' => 'Côte d’Ivoire, Ghana', 'has_warehouses' => 'yes', 'trade_flows' => 'both', 'supply_countries' => 'Chine, Turquie', 'destination_countries' => 'Ghana, Mali', 'transport_modes' => ['terrestre', 'maritime'],
                'warehouses' => json_encode([['name' => 'Abidjan Principal', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan', 'address' => 'Zone industrielle de Yopougon', 'type' => 'principal', 'capacity' => '5000', 'capacity_unit' => 'm2']])]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/grossiste')->getContent());
        file_put_contents($out . '/gro-form.html', $html);
        $this->assertTrue(true);
    }

    public function test_render_demi_grossiste(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $out = getenv('OA_OUT');
        $user = ProUser::query()->create(['first_name' => 'Awa', 'last_name' => 'Koné', 'name' => 'Awa Koné', 'email' => 'awa@test.ci', 'phone' => '+2250712345670', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'demi_grossiste', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['sell'], 'status' => 'draft',
            'profile_data' => ['categories' => 'Fer à béton, Peintures', 'brands_origins' => 'Sika, Legrand', 'sale_units' => 'Sacs, Cartons', 'cities_served' => 'Abidjan, Bouaké', 'regions_served' => 'District d’Abidjan', 'countries_served' => 'Côte d’Ivoire', 'volume_negotiation' => 'yes',
                'quantity_tiers' => json_encode([['from' => '10', 'to' => '49', 'unit' => 'Unités'], ['from' => '50', 'to' => '', 'unit' => 'Unités']])]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/demi_grossiste')->getContent());
        file_put_contents($out . '/dg-form.html', $html);
        $this->assertTrue(true);
    }

    public function test_render_revendeur(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $user = ProUser::query()->create(['first_name' => 'Jean', 'last_name' => 'Kouassi', 'name' => 'Jean Kouassi', 'email' => 'rv@test.ci', 'phone' => '+2250712345671', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'revendeur_professionnel', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
            'profile_data' => ['purchase_categories' => 'Quincaillerie, Électricité', 'resale_categories' => 'Outillage', 'sourcing_countries' => 'Côte d’Ivoire, Chine', 'sales_zones' => 'Abidjan, National', 'needs' => ['rfq', 'find_suppliers']]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/revendeur_professionnel')->getContent());
        file_put_contents(getenv('OA_OUT') . '/rv-form.html', $html);
        $this->assertTrue(true);
    }

    public function test_render_entreprise_btp(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $user = ProUser::query()->create(['first_name' => 'Jean Marc', 'last_name' => 'Kouassi', 'name' => 'Jean Marc Kouassi', 'email' => 'btp@test.ci', 'phone' => '+2250712345672', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'entreprise_btp', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
            'profile_data' => ['specialties' => 'Bâtiment résidentiel, Génie civil', 'work_types' => ['batiment', 'genie_civil'], 'intervention_zones' => 'Côte d’Ivoire, Ghana', 'project_categories' => 'Bureaux', 'purchase_categories' => 'Ciment, Fer à béton', 'purchase_scope' => ['local'], 'btp_functions' => ['rfq', 'orders']]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/entreprise_btp')->getContent());
        file_put_contents(getenv('OA_OUT') . '/btp-form.html', $html);
        $this->assertTrue(true);
    }

    public function test_render_artisan(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $user = ProUser::query()->create(['first_name' => 'Moussa', 'last_name' => 'Koné', 'name' => 'Moussa Koné', 'email' => 'art@test.ci', 'phone' => '+2250712345673', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'entrepreneur_artisan', 'entity_type' => 'independent', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['services'], 'status' => 'draft',
            'profile_data' => ['independent_status' => 'artisan_independant', 'main_trade' => 'electricien', 'specialties' => ['tableaux_electriques', 'depannage'], 'experience_level' => '5_10', 'services' => ['installation', 'depannage'], 'intervention_zones' => 'Abidjan, Bingerville', 'availability' => 'maintenant', 'team_size' => 'solo', 'intervention_capacity' => 'petits_travaux', 'client_types' => ['particuliers'],
                'references_json' => json_encode([['title' => 'Installation électrique villa', 'type' => 'Installation', 'location' => 'Abidjan', 'year' => '2025', 'description' => 'Réseau électrique complet d’une villa R+1.', 'client' => 'Client privé']])]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/entrepreneur_artisan')->getContent());
        file_put_contents(getenv('OA_OUT') . '/art-form.html', $html);
        $this->assertTrue(true);
    }

    public function test_render_promoteur(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $user = ProUser::query()->create(['first_name' => 'Jean Marc', 'last_name' => 'Kouamé', 'name' => 'Jean Marc Kouamé', 'email' => 'promo@test.ci', 'phone' => '+2250712345674', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'promoteur_immobilier', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
            'profile_data' => ['real_estate_activities' => ['promoteur', 'constructeur'], 'activity_zones' => 'Abidjan, Bouaké', 'real_estate_segments' => ['residentiel'], 'project_zones' => 'Abidjan', 'program_types' => ['residentiel', 'mixte'], 'purchase_categories' => 'Ciment, Carrelage', 'purchase_local_countries' => 'Côte d’Ivoire', 'purchase_volume_band' => 'regular', 'rfq_usage' => 'regular', 'tender_usage' => 'frequent',
                'current_projects' => json_encode([['name' => 'Résidence Horizon Riviera', 'type' => 'Résidentiel', 'location' => 'Cocody, Abidjan', 'status' => 'En cours', 'description' => 'Programme résidentiel moyen standing.']])]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/promoteur_immobilier')->getContent());
        file_put_contents(getenv('OA_OUT') . '/promo-form.html', $html);
        $this->assertTrue(true);
    }

    public function test_render_prestataire(): void
    {
        $host = 'https://' . config('ovanie.pro_domain');
        $user = ProUser::query()->create(['first_name' => 'Ali', 'last_name' => 'Traoré', 'name' => 'Ali Traoré', 'email' => 'presta@test.ci', 'phone' => '+2250712345675', 'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active', 'professional_status' => 'not_started']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $user->id, 'profile_key' => 'prestataire_btp', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['services'], 'status' => 'draft',
            'profile_data' => ['service_category' => 'installation', 'service_subcategory' => 'Électricité', 'skills' => 'Câblage, Mise en service', 'intervention_zones' => 'Abidjan', 'availability' => 'Disponible immédiatement', 'means_types' => 'Équipements', 'pricing_mode' => 'indicative', 'indicative_rate' => '25000', 'pricing_unit' => 'Journée',
                'equipment_json' => json_encode([['name' => 'Groupe électrogène 50 kVA', 'type' => 'Groupe électrogène', 'quantity' => '2', 'ownership' => 'Propriété']])]]);
        $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images)/#', 'http://127.0.0.1:8000/$1/', $this->actingAs($user, 'pro')->get($host . '/compte/professionnel/prestataire_btp')->getContent());
        file_put_contents(getenv('OA_OUT') . '/presta-form.html', $html);
        $this->assertTrue(true);
    }
}
