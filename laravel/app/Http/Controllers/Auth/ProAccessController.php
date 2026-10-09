<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ProUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** Outils partagés par les écrans d'accès OVANIE PRO (ACC-01 à ACC-19). */
abstract class ProAccessController extends Controller
{
    protected function proUser(): ?ProUser
    {
        /** @var ProUser|null $user */
        $user = Auth::guard('pro')->user();

        return $user;
    }

    /**
     * Politique affichée sur les formulaires PRO : 8 caractères minimum,
     * majuscule, minuscule, chiffre et caractère spécial.
     */
    protected function passwordRule(): PasswordRule
    {
        return PasswordRule::min(8)->mixedCase()->numbers()->symbols();
    }

    /** Fusionne l'indicatif et le numéro saisis en un numéro international (+225…). */
    protected function mergePhone(Request $request, string $field = 'phone', string $codeField = 'phone_country'): void
    {
        if ($request->filled($field)) {
            $request->merge([$field => $this->normalizePhone((string) $request->input($codeField, '+225'), (string) $request->input($field))]);
        }
    }

    protected function normalizePhone(string $countryCode, string $phone): ?string
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            return '+' . $digits;
        }

        if (str_starts_with($raw, '00') && strlen($digits) > 2) {
            return '+' . substr($digits, 2);
        }

        $countryDigits = preg_replace('/\D+/', '', $countryCode) ?: '225';

        return str_starts_with($digits, $countryDigits) && strlen($digits) > 10
            ? '+' . $digits
            : '+' . $countryDigits . $digits;
    }

    protected function phoneCodeRule(): array
    {
        return ['nullable', Rule::in(array_keys($this->phoneCodes()))];
    }

    /** Sépare un numéro enregistré (+22507…) en indicatif + numéro pour pré-remplir un formulaire. */
    protected function splitPhone(?string $phone): array
    {
        $phone = (string) $phone;
        $codes = array_keys($this->phoneCodes());
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($codes as $code) {
            if ($phone !== '' && str_starts_with($phone, $code)) {
                return [$code, substr($phone, strlen($code))];
            }
        }

        return ['+225', ltrim($phone, '+')];
    }

    /**
     * Résout un compte PRO à partir de l'e-mail ou du téléphone.
     * Un téléphone international (+XXX…) doit correspondre exactement. Pour un
     * numéro local, +225 est prioritaire puis un suffixe unique peut être accepté.
     */
    protected function findProUserByIdentifier(string $identifier): ?ProUser
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return ProUser::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])
                ->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?: '';
        if (strlen($digits) < 8) {
            return null;
        }

        $raw = trim($identifier);
        if (str_starts_with($raw, '+')) {
            return ProUser::query()->where('phone', '+' . $digits)->first();
        }

        if (str_starts_with($raw, '00') && strlen($digits) > 2) {
            return ProUser::query()->where('phone', '+' . substr($digits, 2))->first();
        }

        // Si l'utilisateur a saisi un indicatif connu sans +, essayer d'abord l'exact.
        foreach (array_keys($this->phoneCodes()) as $code) {
            $codeDigits = preg_replace('/\D+/', '', $code) ?: '';
            if ($codeDigits !== '' && str_starts_with($digits, $codeDigits)) {
                $exact = ProUser::query()->where('phone', '+' . $digits)->first();
                if ($exact) {
                    return $exact;
                }
                break;
            }
        }

        // Numéro local : Côte d'Ivoire par défaut pour l'interface actuelle.
        $ciCandidate = '+225' . $digits;
        if ($user = ProUser::query()->where('phone', $ciCandidate)->first()) {
            return $user;
        }

        // Dernier recours : suffixe unique uniquement, afin d'éviter une collision
        // entre deux pays partageant le même numéro national.
        $matches = ProUser::query()
            ->where('phone', 'like', '%' . $digits)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function phonesMatch(?string $left, ?string $right): bool
    {
        $a = preg_replace('/\D+/', '', (string) $left) ?: '';
        $b = preg_replace('/\D+/', '', (string) $right) ?: '';

        return $a !== '' && $b !== '' && hash_equals($a, $b);
    }

    /**
     * RG-39 : un espace Acheteur PRO / Vendeur n'est activable qu'avec un profil professionnel
     * soumis, ou en tant que collaborateur d'une organisation déjà soumise à vérification.
     */
    protected function professionalReady(ProUser $user): bool
    {
        $profile = $user->professionalProfile()->first();
        if ($profile && in_array($profile->status, ['pending', 'verified'], true)) {
            return true;
        }

        return $user->activeMemberships()
            ->whereHas('organization', fn ($q) => $q->where('verification_status', '!=', 'draft'))
            ->exists();
    }

    /**
     * Le profil professionnel doit être soumis avant l'ouverture de « Mon espace ».
     * L'étape Fonctions est volontairement facultative : la maquette prévoit « Je le ferai
     * plus tard », donc l'absence de rôle ne doit plus provoquer une boucle vers /roles.
     */
    protected function onboardingRedirect(ProUser $user): ?RedirectResponse
    {
        // Parcours entièrement terminé : « Mon espace » est ouvert.
        if ($user->onboarding_completed_at) {
            return null;
        }

        // Compte simple : tant qu'aucune offre / aucun besoin n'est publié, on reprend le parcours de publication.
        if ($user->onboarding_path === 'simple') {
            if (\App\Models\ProPublication::query()->where('pro_user_id', $user->id)->where('status', 'published')->exists()) {
                return null;
            }
            $message = 'Terminez votre publication pour accéder à votre espace.';
            $draft = \App\Models\ProPublication::query()->where('pro_user_id', $user->id)->where('status', 'draft')->latest('updated_at')->first();
            if ($draft) {
                return redirect()->route('pro.publish.edit', ['type' => $draft->kind === 'offer' ? 'offre' : 'besoin', 'publication' => $draft])->with('info', $message);
            }
            $type = $user->roles()->where('role', 'requester')->exists() ? 'besoin' : ($user->roles()->where('role', 'advertiser')->exists() ? 'offre' : null);

            return $type
                ? redirect()->route('pro.publish.form', ['type' => $type])->with('info', $message)
                : redirect()->route('pro.publish.start')->with('info', $message);
        }

        if ($user->onboarding_path !== 'professional') {
            return null;
        }

        $profile = $user->professionalProfile()->first();
        $message = 'Terminez la création de votre compte professionnel pour accéder à votre espace.';

        if (! $profile || ! $profile->entity_type) {
            return redirect()->route('pro.register.qualification')->with('info', $message);
        }
        if (! $profile->submitted_at) {
            return redirect()->route('pro.register.professional.form', ['profile' => $profile->profile_key])->with('info', $message);
        }

        // Dernière étape : Fonctions (valider, ou « Je le ferai plus tard »).
        return redirect()->route('pro.roles.edit')->with('info', 'Dernière étape : choisissez vos fonctions ou indiquez que vous le ferez plus tard.');
    }

    protected function onboardingComplete(ProUser $user): bool
    {
        return $this->onboardingRedirect($user) === null;
    }

    protected function languages(): array
    {
        return (array) config('ovanie_pro.languages', ['fr' => 'Français']);
    }

    protected function countries(): array
    {
        return (array) config('ovanie_pro.countries', ['Côte d’Ivoire']);
    }

    protected function phoneCodes(): array
    {
        return (array) config('ovanie_pro.phone_codes', ['+225' => 'CI +225']);
    }

    protected function businessRoles(): array
    {
        return (array) config('ovanie_pro.business_roles', []);
    }

    protected function permissions(): array
    {
        return (array) config('ovanie_pro.internal_permissions', []);
    }
}
