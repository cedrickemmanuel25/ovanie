<?php

namespace Tests\Feature;

use App\Models\ProCompanyMembership;
use App\Models\ProOrganization;
use App\Models\ProProfessionalProfile;
use App\Models\ProPublication;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Passe en revue tous les écrans d'accès OVANIE PRO : chacun doit s'afficher sans erreur serveur.
 * Avec la variable OA_OUT=dossier, le HTML de chaque écran est enregistré pour un contrôle dans le navigateur.
 */
class ProAccessSmokeTest extends TestCase
{
    use RefreshDatabase;

    private string $host;
    private array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = 'https://' . config('ovanie.pro_domain');
    }

    public function test_every_access_screen_renders(): void
    {
        // ---------- Visiteur ----------
        foreach ([
            'connexion' => '/compte/connexion',
            'inscription-choix' => '/compte/inscription',
            'inscription-pro' => '/compte/inscription/pro',
            'mot-de-passe-oublie' => '/compte/mot-de-passe-oublie',
            'reinitialiser' => '/compte/reinitialiser',
            'invitation-saisie' => '/compte/invitation',
            'invitation-invalide' => '/compte/invitation/' . str_repeat('z', 40),
            'publier' => '/compte/publier',
            'publier-compte-offre' => '/compte/publier/offre/compte',
            'publier-compte-besoin' => '/compte/publier/besoin/compte',
        ] as $name => $path) {
            $this->check($name, $this->get($this->host . $path));
        }

        // ---------- Compte professionnel, pour chacun des 8 profils ----------
        foreach (array_keys((array) config('ovanie_pro.professional_profiles')) as $i => $profileKey) {
            $user = $this->user("pro$i@example.com", "+22507000001$i", 'professional');
            if ($i === 0) {
                $this->check('qualification', $this->actingAs($user, 'pro')->get($this->host . '/compte/qualification'));
            }
            $entities = (array) config("ovanie_pro_forms.profiles.$profileKey.entity_types");
            foreach ($entities as $entity) {
                ProProfessionalProfile::query()->updateOrCreate(['pro_user_id' => $user->id], [
                    'profile_key' => $profileKey, 'entity_type' => $entity, 'activity_country' => 'Côte d’Ivoire',
                    'registration_country' => 'Côte d’Ivoire', 'desired_functions' => ['buy'], 'status' => 'draft',
                ]);
                $this->check("metier-$profileKey-$entity", $this->actingAs($user->fresh(), 'pro')->get($this->host . "/compte/professionnel/$profileKey"));
            }
            // Retour d'une validation en erreur : la page doit se réafficher avec les erreurs, sans planter.
            $this->actingAs($user->fresh(), 'pro')->from($this->host . "/compte/professionnel/$profileKey")
                ->post($this->host . "/compte/professionnel/$profileKey", ['action' => 'submit', 'profile_entity_type' => $entities[0]]);
            $this->check("metier-$profileKey-erreurs", $this->actingAs($user->fresh(), 'pro')->get($this->host . "/compte/professionnel/$profileKey"));
        }

        // ---------- Profil soumis : fonctions, compte, équipe ----------
        $owner = $this->user('owner@example.com', '+2250700000099', 'professional');
        $org = ProOrganization::query()->create(['owner_pro_user_id' => $owner->id, 'entity_type' => 'organization', 'legal_name' => 'Test SARL', 'country' => 'Côte d’Ivoire', 'verification_status' => 'pending']);
        ProCompanyMembership::query()->create(['pro_user_id' => $owner->id, 'pro_organization_id' => $org->id, 'organization_name' => 'Test SARL', 'permission' => 'administrator', 'status' => 'active']);
        ProProfessionalProfile::query()->create(['pro_user_id' => $owner->id, 'pro_organization_id' => $org->id, 'profile_key' => 'grossiste', 'entity_type' => 'organization', 'activity_country' => 'Côte d’Ivoire', 'status' => 'pending', 'submitted_at' => now()]);
        $this->check('fonctions', $this->actingAs($owner, 'pro')->get($this->host . '/compte/roles'));
        $owner->forceFill(['onboarding_completed_at' => now()])->save();
        $this->check('mon-espace', $this->actingAs($owner->fresh(), 'pro')->get($this->host . '/compte/mon-espace'));
        $this->check('equipe', $this->actingAs($owner->fresh(), 'pro')->get($this->host . "/compte/entreprise/{$org->id}/equipe"));

        $token = str_repeat('d', 40);
        $org->invitations()->create(['inviter_pro_user_id' => $owner->id, 'organization_name' => 'Test SARL', 'email' => 'invite@example.com', 'permission' => 'buyer', 'token_hash' => hash('sha256', $token), 'status' => 'pending', 'expires_at' => now()->addDays(7)]);
        auth('pro')->logout();
        $this->check('invitation', $this->get($this->host . '/compte/invitation/' . $token));

        // ---------- Compte simple : offre et besoin ----------
        $simple = $this->user('simple@example.com', '+2250700000098', 'simple');
        foreach (['offre' => 'offer', 'besoin' => 'need'] as $type => $kind) {
            $this->check("publier-$type-formulaire", $this->actingAs($simple, 'pro')->get($this->host . "/compte/publier/$type/nouveau"));
            $publication = new ProPublication(['title' => 'Test', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan']);
            $publication->forceFill(['pro_user_id' => $simple->id, 'kind' => $kind, 'status' => 'draft'])->save();
            $this->check("publier-$type-modifier", $this->actingAs($simple, 'pro')->get($this->host . "/compte/publier/$type/{$publication->id}/modifier"));
            $this->check("publier-$type-verification", $this->actingAs($simple, 'pro')->get($this->host . "/compte/publier/$type/{$publication->id}/verification"));
        }

        $this->assertSame([], $this->failures, "Écrans en erreur :\n" . implode("\n", $this->failures));
    }

    private function check(string $name, $response): void
    {
        $status = $response->getStatusCode();
        if ($status !== 200) {
            $detail = $response->exception ? ' — ' . mb_substr($response->exception->getMessage(), 0, 200) : ($response->headers->get('Location') ? ' → ' . $response->headers->get('Location') : '');
            $this->failures[] = "$name : HTTP $status$detail";
        }
        if (($out = getenv('OA_OUT')) && $status === 200) {
            $html = preg_replace('#https?://[^/"]+/(css|js|ovanie-pro|images|storage)/#', 'http://127.0.0.1:8000/$1/', $response->getContent());
            file_put_contents($out . '/' . $name . '.html', $html);
        }
    }

    private function user(string $email, string $phone, string $path): ProUser
    {
        return ProUser::query()->create([
            'first_name' => 'Test', 'last_name' => 'Compte', 'name' => 'Test Compte', 'email' => $email, 'phone' => $phone,
            'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => $path === 'simple' ? 'simple' : 'pro',
            'onboarding_path' => $path, 'status' => 'active', 'professional_status' => 'not_started',
        ]);
    }
}
