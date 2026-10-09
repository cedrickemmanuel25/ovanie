<?php

namespace App\Http\Controllers\Auth;

use App\Models\ProCompanyInvitation;
use App\Models\ProCompanyMembership;
use App\Models\ProUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** ACC-14 : invitation collaborateur à usage unique. */
class ProInvitationController extends ProAccessController
{
    public function entry(): View
    {
        return view('pro.auth.invitation-entry');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:500']], [], ['token' => 'code ou lien d’invitation']);
        $token = trim((string) $data['token']);
        if (preg_match('~/invitation/([A-Za-z0-9]+)~', $token, $m)) {
            $token = $m[1];
        }

        return redirect()->route('pro.invitation.show', ['token' => $token]);
    }

    public function show(string $token): View
    {
        $invitation = $this->find($token);
        $state = $this->state($invitation);
        $user = $this->proUser();
        $existingUser = $invitation ? $this->accountForInvitation($invitation) : null;
        [$phoneCode, $phoneNumber] = $this->splitPhone($invitation?->phone);

        // « Se connecter et accepter » : après connexion, retour direct sur cette invitation.
        if (! $user && $state === 'pending') {
            session()->put('url.intended', route('pro.invitation.show', ['token' => $token]));
        }
        $inviter = $invitation?->inviter;

        return view('pro.auth.invitation', [
            'invitation' => $invitation,
            'state' => $state,
            'token' => $token,
            'user' => $user,
            'wrongAccount' => $user && $invitation ? ! $this->userMatchesInvitation($user, $invitation) : false,
            'existingAccount' => (bool) $existingUser,
            'permissionLabel' => $invitation ? ($this->permissions()[$invitation->permission] ?? $invitation->permission) : null,
            'permissionDescription' => $invitation ? config('ovanie_pro.internal_permission_descriptions.' . $invitation->permission) : null,
            'inviterName' => $inviter?->full_name,
            'inviterTitle' => $inviter?->professionalProfile()->value('responsible_position'),
            'countries' => $this->countries(),
            'languages' => $this->languages(),
            'phoneCodes' => $this->phoneCodes(),
            'phoneCode' => $invitation?->phone ? $phoneCode : '+225',
            'phoneNumber' => $invitation?->phone ? $phoneNumber : '',
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->find($token);
        if ($this->state($invitation) !== 'pending') {
            return redirect()->route('pro.invitation.show', ['token' => $token]);
        }

        $user = $this->proUser();
        $newUserData = null;

        if (! $user) {
            if ($this->accountForInvitation($invitation)) {
                $request->session()->put('url.intended', route('pro.invitation.show', ['token' => $token]));

                return redirect()->route('pro.login')
                    ->with('status', 'Un compte OVANIE PRO existe déjà pour cette invitation. Connectez-vous pour l’accepter.');
            }

            // Les coordonnées fournies dans l'invitation sont verrouillées côté serveur.
            if ($invitation->email) {
                $request->merge(['email' => mb_strtolower(trim((string) $invitation->email))]);
            } else {
                $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
            }

            if ($invitation->phone) {
                $request->merge(['phone' => $invitation->phone]);
            } else {
                $this->mergePhone($request);
            }

            $countries = $this->countries();
            $defaultCountry = (string) ($invitation->organization?->country ?: ($countries[0] ?? 'Côte d’Ivoire'));
            if (! in_array($defaultCountry, $countries, true)) {
                $defaultCountry = (string) ($countries[0] ?? 'Côte d’Ivoire');
            }
            if (! $request->filled('residence_country')) {
                $request->merge(['residence_country' => $defaultCountry]);
            }
            if (! $request->filled('preferred_language')) {
                $request->merge(['preferred_language' => array_key_exists('fr', $this->languages()) ? 'fr' : array_key_first($this->languages())]);
            }

            $newUserData = $request->validate([
                'last_name' => ['required', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:100'],
                'residence_country' => ['required', Rule::in($countries)],
                'phone_country' => $this->phoneCodeRule(),
                'phone' => ['required', 'string', 'regex:/^\+[0-9]{8,15}$/', Rule::unique('pro_users', 'phone')],
                'email' => ['required', 'email', 'max:255', Rule::unique('pro_users', 'email')],
                'preferred_language' => ['required', Rule::in(array_keys($this->languages()))],
                'password' => ['required', 'confirmed', $this->passwordRule()],
                'terms' => ['accepted'],
            ], [
                'phone.unique' => 'Ce numéro est déjà associé à un compte. Connectez-vous avec ce compte pour accepter l’invitation.',
                'email.unique' => 'Cette adresse est déjà associée à un compte. Connectez-vous avec ce compte pour accepter l’invitation.',
                'phone.regex' => 'Saisissez un numéro de téléphone valide.',
                'terms.accepted' => 'Vous devez accepter les conditions d’utilisation.',
            ], [
                'last_name' => 'nom', 'first_name' => 'prénoms', 'residence_country' => 'pays de résidence',
                'phone' => 'téléphone', 'email' => 'e-mail', 'password' => 'mot de passe', 'preferred_language' => 'langue',
            ]);
        } elseif (! $this->userMatchesInvitation($user, $invitation)) {
            return back()->withErrors([
                'invitation' => 'Cette invitation est destinée à un autre compte. Déconnectez-vous puis utilisez le compte correspondant à l’e-mail ou au téléphone invité.',
            ]);
        }

        $createdUser = false;
        $acceptedUser = DB::transaction(function () use ($token, $user, $newUserData, &$createdUser) {
            /** @var ProCompanyInvitation|null $locked */
            $locked = ProCompanyInvitation::query()
                ->with('organization')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($this->state($locked) !== 'pending') {
                return null;
            }

            $acceptedUser = $user;
            if (! $acceptedUser) {
                $acceptedUser = ProUser::query()->create([
                    'first_name' => $newUserData['first_name'],
                    'last_name' => $newUserData['last_name'],
                    'name' => trim($newUserData['first_name'] . ' ' . $newUserData['last_name']),
                    'email' => $newUserData['email'],
                    'phone' => $newUserData['phone'],
                    'residence_country' => $newUserData['residence_country'],
                    'preferred_language' => $newUserData['preferred_language'],
                    'locale' => $newUserData['preferred_language'],
                    'password' => $newUserData['password'],
                    'account_type' => 'pro',
                    'onboarding_path' => 'collaborator',
                    'status' => 'active',
                    'professional_status' => 'not_started',
                    'currency' => 'XOF',
                ]);
                $createdUser = true;
            }

            ProCompanyMembership::query()->updateOrCreate(
                ['pro_user_id' => $acceptedUser->id, 'pro_organization_id' => $locked->organization->id],
                ['organization_name' => $locked->organization->display_name, 'permission' => $locked->permission, 'status' => 'active']
            );

            $locked->forceFill([
                'status' => 'accepted',
                'accepted_pro_user_id' => $acceptedUser->id,
                'accepted_at' => now(),
            ])->save();

            if ($acceptedUser->account_type !== 'pro') {
                $acceptedUser->forceFill(['account_type' => 'pro'])->save();
            }

            return $acceptedUser;
        });

        if (! $acceptedUser) {
            return redirect()->route('pro.invitation.show', ['token' => $token]);
        }

        if ($createdUser) {
            Auth::guard('pro')->login($acceptedUser);
            $request->session()->regenerate();
            $request->session()->put('pro_password_hash_' . $acceptedUser->id, $acceptedUser->getAuthPassword());
        }

        return redirect()->route('pro.account')
            ->with('success', 'Vous avez rejoint ' . $invitation->organization->display_name . '. Votre accès est actif.');
    }

    public function refuse(string $token): RedirectResponse
    {
        DB::transaction(function () use ($token) {
            $invitation = ProCompanyInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($this->state($invitation) === 'pending') {
                $invitation->forceFill(['status' => 'refused'])->save();
            }
        });

        return redirect()->route('pro.invitation.show', ['token' => $token]);
    }

    private function find(string $token): ?ProCompanyInvitation
    {
        if (strlen($token) < 16 || strlen($token) > 255) {
            return null;
        }

        return ProCompanyInvitation::query()
            ->with(['organization', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    private function accountForInvitation(ProCompanyInvitation $invitation): ?ProUser
    {
        if ($invitation->email) {
            $user = $this->findProUserByIdentifier((string) $invitation->email);
            if ($user) {
                return $user;
            }
        }

        return $invitation->phone ? $this->findProUserByIdentifier((string) $invitation->phone) : null;
    }

    private function userMatchesInvitation(ProUser $user, ProCompanyInvitation $invitation): bool
    {
        if ($invitation->email && mb_strtolower((string) $user->email) === mb_strtolower((string) $invitation->email)) {
            return true;
        }

        return ! $invitation->email && $invitation->phone && $this->phonesMatch($user->phone, $invitation->phone);
    }

    /** pending | accepted | refused | cancelled | expired | invalid */
    private function state(?ProCompanyInvitation $invitation): string
    {
        if (! $invitation || ! $invitation->organization) {
            return 'invalid';
        }
        if ($invitation->status === 'pending' && $invitation->expires_at && $invitation->expires_at->isPast()) {
            return 'expired';
        }

        return in_array($invitation->status, ['pending', 'accepted', 'refused', 'cancelled', 'expired'], true)
            ? $invitation->status
            : 'invalid';
    }
}
