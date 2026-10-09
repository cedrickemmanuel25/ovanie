<?php

namespace Tests\Feature;

use App\Models\ProCompanyInvitation;
use App\Models\ProCompanyMembership;
use App\Models\ProDocument;
use App\Models\ProOrganization;
use App\Models\ProPasswordResetCode;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use App\Notifications\ProPasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** OVANIE PRO V5.2 révision 1 — ACC-01 à ACC-19. */
class ProProfessionalProfilesTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
        Notification::fake();
    }

    public function test_registration_choice_offers_paths_without_public_admin_option(): void
    {
        $response = $this->get($this->proHost . '/compte/inscription');

        $response->assertOk();
        $response->assertSee('Créer mon profil');
        $response->assertSee('Publier une offre');
        $response->assertSee('Rejoindre une entreprise');
        $response->assertDontSee('Administrateur OVANIE');
    }

    public function test_pro_account_is_created_before_qualification(): void
    {
        $response = $this->post($this->proHost . '/compte/inscription/pro', $this->identity('fabricant@example.com', '0102030405'));

        $response->assertRedirect(route('pro.register.qualification'));
        $this->assertDatabaseHas('pro_users', ['email' => 'fabricant@example.com', 'phone' => '+2250102030405', 'account_type' => 'pro']);
        $this->assertDatabaseCount('pro_professional_profiles', 0);
        $this->assertDatabaseCount('pro_organizations', 0);
    }

    public function test_simple_account_requires_a_purpose_and_opens_that_space(): void
    {
        $this->from($this->proHost . '/compte/inscription/simple')
            ->post($this->proHost . '/compte/inscription/simple', $this->identity('simple@example.com', '0102030406'))
            ->assertSessionHasErrors('purpose');

        $this->post($this->proHost . '/compte/inscription/simple', $this->identity('simple@example.com', '0102030406') + ['purpose' => 'advertiser'])
            ->assertRedirect(route('pro.account'));

        $user = ProUser::query()->where('email', 'simple@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('advertiser'));
    }

    public function test_company_only_profile_is_refused_for_an_independent(): void
    {
        $user = $this->proUser('independent@example.com');

        $this->actingAs($user, 'pro')->post($this->proHost . '/compte/qualification', [
            'action' => 'continue',
            'entity_type' => 'independent',
            'professional_profile' => 'fabricant_producteur',
            'activity_country' => 'Côte d’Ivoire',
            'desired_functions' => ['sell'],
        ])->assertSessionHasErrors('professional_profile');

        $this->actingAs($user, 'pro')->post($this->proHost . '/compte/qualification', [
            'action' => 'continue',
            'entity_type' => 'independent',
            'professional_profile' => 'entrepreneur_artisan',
            'activity_country' => 'Côte d’Ivoire',
            'desired_functions' => ['services'],
        ])->assertRedirect(route('pro.register.professional.form', ['profile' => 'entrepreneur_artisan']));

        $this->assertDatabaseHas('pro_professional_profiles', ['pro_user_id' => $user->id, 'entity_type' => 'independent', 'status' => 'draft']);
    }

    public function test_each_profile_renders_its_own_blocks(): void
    {
        $user = $this->proUser('forms@example.com');
        $this->qualify($user, 'fabricant_producteur');

        $fabricant = $this->actingAs($user, 'pro')->get($this->proHost . '/compte/professionnel/fabricant_producteur');
        $fabricant->assertOk();
        $fabricant->assertSee('Nom de l’usine');
        $fabricant->assertSee('Capacité mensuelle');
        $fabricant->assertDontSee('Paliers de quantité');

        $user->professionalProfile->forceFill(['profile_key' => 'demi_grossiste'])->save();
        $demi = $this->actingAs($user, 'pro')->get($this->proHost . '/compte/professionnel/demi_grossiste');
        $demi->assertOk();
        $demi->assertSee('Paliers de quantité');
        $demi->assertDontSee('Nom de l’usine');

        $user->professionalProfile->forceFill(['profile_key' => 'entrepreneur_artisan', 'entity_type' => 'independent'])->save();
        $artisan = $this->actingAs($user, 'pro')->get($this->proHost . '/compte/professionnel/entrepreneur_artisan');
        $artisan->assertOk();
        $artisan->assertSee('Métier principal');
        $artisan->assertSee('Vous exercez comme');
        $artisan->assertDontSee('Nom de l’usine');
    }

    public function test_draft_can_be_saved_without_required_fields(): void
    {
        $user = $this->proUser('draft@example.com');
        $this->qualify($user, 'prestataire_btp');
        $url = $this->proHost . '/compte/professionnel/prestataire_btp';

        $this->actingAs($user, 'pro')->from($url)->post($url, ['action' => 'draft', 'legal_name' => 'Brouillon SA'])
            ->assertRedirect($url)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pro_organizations', ['legal_name' => 'Brouillon SA', 'verification_status' => 'draft']);
        $this->assertDatabaseHas('pro_professional_profiles', ['pro_user_id' => $user->id, 'status' => 'draft']);
    }

    public function test_professional_spaces_require_a_submitted_profile(): void
    {
        $user = $this->proUser('roles@example.com');
        $this->qualify($user, 'grossiste');

        $this->actingAs($user, 'pro')->from($this->proHost . '/compte/roles')
            ->post($this->proHost . '/compte/roles', ['roles' => ['seller_supplier']])
            ->assertSessionHasErrors('roles');
        $this->assertFalse($user->hasRole('seller_supplier'));

        $user->professionalProfile->forceFill(['status' => 'pending'])->save();
        $this->actingAs($user, 'pro')->post($this->proHost . '/compte/roles', ['roles' => ['seller_supplier', 'buyer_pro']])
            ->assertRedirect(route('pro.account'));
        $this->assertTrue($user->hasRole('seller_supplier'));
        $this->assertTrue($user->hasRole('buyer_pro'));
    }

    public function test_only_an_administrator_can_invite_and_invitation_is_single_use(): void
    {
        [$admin, $organization] = $this->organizationWithAdmin();

        $outsider = $this->proUser('outsider@example.com');
        $this->actingAs($outsider, 'pro')->get($this->proHost . "/compte/entreprise/{$organization->id}/equipe")->assertForbidden();

        $response = $this->actingAs($admin, 'pro')->post($this->proHost . "/compte/entreprise/{$organization->id}/invitations", [
            'email' => 'achats@example.com', 'permission' => 'purchasing', 'expires_in' => '7',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('invitation_link');
        $link = app('session.store')->get('invitation_link');
        $token = basename($link);
        $this->assertDatabaseHas('pro_company_invitations', ['email' => 'achats@example.com', 'status' => 'pending', 'permission' => 'purchasing']);
        $this->assertDatabaseMissing('pro_company_invitations', ['token_hash' => $token]);

        $this->app['auth']->guard('pro')->logout();
        $page = $this->get($this->proHost . "/compte/invitation/$token");
        $page->assertOk()->assertSee('Test Distribution')->assertSee('vous invite à rejoindre son équipe')->assertSee('Achats')->assertSee('Acceptez l’invitation');

        $this->post($this->proHost . "/compte/invitation/$token", $this->identity('ignored@example.com', '0708091011'))
            ->assertRedirect(route('pro.account'));

        $collaborator = ProUser::query()->where('email', 'achats@example.com')->firstOrFail();
        $this->assertSame('collaborator', $collaborator->onboarding_path);
        $this->assertSame('purchasing', $collaborator->permissionIn($organization));
        $this->assertDatabaseHas('pro_company_invitations', ['email' => 'achats@example.com', 'status' => 'accepted']);

        // Une invitation utilisée ne peut pas être réutilisée.
        $this->app['auth']->guard('pro')->logout();
        $this->get($this->proHost . "/compte/invitation/$token")->assertSee('Invitation déjà utilisée');

        // La désactivation retire immédiatement l'accès entreprise.
        $membership = ProCompanyMembership::query()->where('pro_user_id', $collaborator->id)->firstOrFail();
        $this->actingAs($admin, 'pro')->put($this->proHost . "/compte/entreprise/{$organization->id}/membres/{$membership->id}", [
            'permission' => 'purchasing', 'status' => 'inactive',
        ])->assertSessionHasNoErrors();
        $this->assertNull($collaborator->fresh()->permissionIn($organization));
    }

    public function test_expired_or_cancelled_invitations_cannot_be_accepted(): void
    {
        [$admin, $organization] = $this->organizationWithAdmin();

        $expired = $this->invitation($organization, $admin, 'expired-token-0000000001', now()->subDay());
        $this->get($this->proHost . '/compte/invitation/expired-token-0000000001')->assertSee('Invitation expirée');
        $this->post($this->proHost . '/compte/invitation/expired-token-0000000001', $this->identity('late@example.com', '0708091012'));
        $this->assertDatabaseMissing('pro_users', ['email' => 'late@example.com']);
        $this->assertSame('pending', $expired->fresh()->status);

        $cancelled = $this->invitation($organization, $admin, 'cancel-token-00000000001', now()->addDays(3));
        $this->actingAs($admin, 'pro')->post($this->proHost . "/compte/entreprise/{$organization->id}/invitations/{$cancelled->id}/annuler");
        $this->assertSame('cancelled', $cancelled->fresh()->status);

        $refused = $this->invitation($organization, $admin, 'refuse-token-00000000001', now()->addDays(3));
        $this->app['auth']->guard('pro')->logout();
        $this->post($this->proHost . '/compte/invitation/refuse-token-00000000001/refuser');
        $this->assertSame('refused', $refused->fresh()->status);
    }

    public function test_login_accepts_phone_and_forgot_password_is_neutral(): void
    {
        $user = $this->proUser('phone@example.com', '+2250504030201');

        $this->post($this->proHost . '/compte/connexion', ['identifier' => '05 04 03 02 01', 'password' => 'ProPassword123'])
            ->assertRedirect(route('pro.account'));
        $this->assertAuthenticatedAs($user, 'pro');

        $this->app['auth']->guard('pro')->logout();
        $this->post($this->proHost . '/compte/mot-de-passe-oublie', ['email' => 'phone@example.com'])->assertSessionHas('status');
        $known = app('session.store')->get('status');
        $this->post($this->proHost . '/compte/mot-de-passe-oublie', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        $unknown = app('session.store')->get('status');
        $this->assertNotEmpty($known);
        $this->assertSame($known, $unknown);
    }

    public function test_password_reset_accepts_email_and_uses_a_six_digit_one_time_code(): void
    {
        $user = $this->proUser('reset@example.com', '+2250701020304');

        $response = $this->post($this->proHost . '/compte/mot-de-passe-oublie', [
            'identifier' => 'reset@example.com',
        ]);
        $response->assertRedirect(route('pro.password.request'))->assertSessionHas('reset_challenge');
        $challenge = app('session.store')->get('reset_challenge');
        $this->assertNotEmpty($challenge);
        $this->assertDatabaseHas('pro_password_reset_codes', ['pro_user_id' => $user->id, 'attempts' => 0]);

        $code = null;
        Notification::assertSentTo($user, ProPasswordResetCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;
            return strlen($code) === 6;
        });
        $this->assertNotNull($code);

        $this->post($this->proHost . '/compte/reinitialiser', [
            'challenge' => $challenge,
            'code' => $code,
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ])->assertRedirect(route('pro.password.reset'));

        $this->assertTrue(Hash::check('NouveauPass123!', $user->fresh()->password));
        $this->assertNotNull(ProPasswordResetCode::query()->where('pro_user_id', $user->id)->firstOrFail()->used_at);
    }

    public function test_phone_only_invitation_detects_an_existing_account(): void
    {
        [$admin, $organization] = $this->organizationWithAdmin();
        $existing = $this->proUser('mobile@example.com', '+2250708091011');
        $token = 'phone-only-token-0000000001';
        $organization->invitations()->create([
            'inviter_pro_user_id' => $admin->id,
            'organization_name' => $organization->display_name,
            'email' => null,
            'phone' => $existing->phone,
            'permission' => 'site_manager',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        $this->app['auth']->guard('pro')->logout();
        $this->get($this->proHost . '/compte/invitation/' . $token)
            ->assertOk()
            ->assertSee('Vous avez déjà un compte OVANIE PRO');

        $this->post($this->proHost . '/compte/invitation/' . $token)
            ->assertRedirect(route('pro.login'));
        $this->assertDatabaseCount('pro_users', 2);
    }

    public function test_account_space_stays_closed_until_professional_signup_is_finished(): void
    {
        $user = $this->proUser('onboarding@example.com');
        $account = $this->proHost . '/compte/mon-espace';

        $this->actingAs($user, 'pro')->get($account)->assertRedirect(route('pro.register.qualification'));

        $this->qualify($user, 'grossiste');
        $this->actingAs($user, 'pro')->get($account)
            ->assertRedirect(route('pro.register.professional.form', ['profile' => 'grossiste']));

        $user->professionalProfile->forceFill(['status' => 'pending', 'submitted_at' => now()])->save();

        // Profil soumis mais étape 4 non validée : retour à « Vos fonctions ».
        $this->actingAs($user, 'pro')->get($account)->assertRedirect(route('pro.roles.edit'));

        // Les fonctions ne suffisent pas tant que l'étape 4 n'est pas validée…
        $user->roles()->create(['role' => 'buyer_pro']);
        $this->actingAs($user, 'pro')->get($account)->assertRedirect(route('pro.roles.edit'));

        // … « Je le ferai plus tard » termine l'inscription.
        $this->actingAs($user, 'pro')->post($this->proHost . '/compte/roles', ['action' => 'later'])->assertRedirect(route('pro.account'));
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->actingAs($user->fresh(), 'pro')->get($account)->assertOk();
    }

    public function test_reset_code_can_be_checked_before_submitting_and_attempts_are_limited(): void
    {
        $user = $this->proUser('code@example.com');
        $challenge = str_repeat('k', 48);
        \App\Models\ProPasswordResetCode::query()->create([
            'pro_user_id' => $user->id, 'challenge_hash' => hash('sha256', $challenge), 'code_hash' => \Illuminate\Support\Facades\Hash::make('123456'),
            'channel' => 'mail', 'attempts' => 0, 'expires_at' => now()->addMinutes(10),
        ]);
        $url = $this->proHost . '/compte/reinitialiser/verifier';

        $this->postJson($url, ['challenge' => $challenge, 'code' => '123456'])->assertJson(['status' => 'valid']);
        $this->postJson($url, ['challenge' => $challenge, 'code' => '000000'])->assertJson(['status' => 'invalid']);
        foreach (range(1, 3) as $i) {
            $this->postJson($url, ['challenge' => $challenge, 'code' => '000000']);
        }
        // Cinquième erreur : le code est neutralisé, même le bon code ne passe plus.
        $this->postJson($url, ['challenge' => $challenge, 'code' => '000000'])->assertJson(['status' => 'expired']);
        $this->postJson($url, ['challenge' => $challenge, 'code' => '123456'])->assertJson(['status' => 'expired']);
    }

    public function test_account_page_lists_missing_information(): void
    {
        $user = $this->proUser('account@example.com');
        $this->qualify($user, 'grossiste');
        $user->professionalProfile->forceFill(['status' => 'pending', 'submitted_at' => now()])->save();
        $user->roles()->create(['role' => 'requester']);
        $user->forceFill(['onboarding_completed_at' => now()])->save();

        $this->actingAs($user, 'pro')->get($this->proHost . '/compte/mon-espace')
            ->assertOk()
            ->assertSee('information(s) à compléter')
            ->assertSee('Raison sociale');
    }

    private function identity(string $email, string $phone): array
    {
        return [
            'last_name' => 'Kouassi', 'first_name' => 'Yao', 'residence_country' => 'Côte d’Ivoire',
            'preferred_language' => 'fr', 'email' => $email, 'phone_country' => '+225', 'phone' => $phone,
            'password' => 'ProPassword123!', 'password_confirmation' => 'ProPassword123!', 'terms' => '1',
        ];
    }

    private function qualify(ProUser $user, string $profile): void
    {
        ProProfessionalProfile::query()->create([
            'pro_user_id' => $user->id, 'profile_key' => $profile, 'entity_type' => 'organization',
            'activity_country' => 'Côte d’Ivoire', 'registration_country' => 'Côte d’Ivoire',
            'desired_functions' => ['sell'], 'status' => 'draft',
        ]);
        $user->forceFill(['professional_profile' => $profile, 'professional_status' => 'draft'])->save();
    }

    private function organizationWithAdmin(): array
    {
        $admin = $this->proUser('admin@example.com');
        $organization = ProOrganization::query()->create([
            'owner_pro_user_id' => $admin->id, 'entity_type' => 'organization', 'legal_name' => 'Test Distribution',
            'verification_status' => 'pending',
        ]);
        ProCompanyMembership::query()->create([
            'pro_user_id' => $admin->id, 'pro_organization_id' => $organization->id,
            'organization_name' => 'Test Distribution', 'permission' => 'administrator', 'status' => 'active',
        ]);

        return [$admin, $organization];
    }

    private function invitation(ProOrganization $organization, ProUser $admin, string $token, $expiresAt): ProCompanyInvitation
    {
        return $organization->invitations()->create([
            'inviter_pro_user_id' => $admin->id, 'organization_name' => $organization->display_name,
            'email' => 'invite-' . substr($token, 0, 6) . '@example.com', 'permission' => 'read_only',
            'token_hash' => hash('sha256', $token), 'status' => 'pending', 'expires_at' => $expiresAt,
        ]);
    }

    private function proUser(string $email, ?string $phone = null): ProUser
    {
        return ProUser::query()->create([
            'first_name' => 'Test', 'last_name' => 'Pro', 'name' => 'Test Pro', 'email' => $email, 'phone' => $phone,
            'residence_country' => 'Côte d’Ivoire', 'preferred_language' => 'fr', 'password' => 'ProPassword123',
            'account_type' => 'pro', 'onboarding_path' => 'professional', 'status' => 'active',
            'professional_status' => 'not_started', 'locale' => 'fr', 'currency' => 'XOF',
        ]);
    }
}
