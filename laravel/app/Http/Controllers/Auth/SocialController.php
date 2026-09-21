<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GuestCartService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $email = strtolower(trim((string) $googleUser->getEmail()));

            if ($email === '') {
                return redirect()->route('login')->with('error', 'Impossible de récupérer votre email Google.');
            }

            $fullName = $googleUser->getName() ?? $googleUser->getNickname() ?? 'Utilisateur';
            [$firstName, $lastName] = $this->splitName($fullName);

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user && ! $this->isPublicAccount($user)) {
                return redirect()->route('login')
                    ->with('error', 'Ce portail est réservé aux comptes clients et vendeurs.');
            }

            if (! $user) {
                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => $fullName,
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'password' => bcrypt(Str::random(32)),
                    'role' => 'client',
                    'status' => 'active',
                ]);
            } else {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }

            return $this->loginPublicUser($user);
        } catch (Exception $e) {
            Log::error('Erreur Google Auth: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'Authentification Google échouée.');
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $fbUser = Socialite::driver('facebook')->stateless()->user();
            $email = strtolower(trim((string) $fbUser->getEmail()));

            if ($email === '') {
                return redirect()->route('login')->with('error', 'Impossible de récupérer votre email Facebook.');
            }

            $fullName = $fbUser->getName() ?? $fbUser->getNickname() ?? 'Utilisateur';
            [$firstName, $lastName] = $this->splitName($fullName);
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user && ! $this->isPublicAccount($user)) {
                return redirect()->route('login')
                    ->with('error', 'Ce portail est réservé aux comptes clients et vendeurs.');
            }

            if (! $user) {
                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => $fullName,
                    'email' => $email,
                    'facebook_id' => $fbUser->getId(),
                    'password' => bcrypt(Str::random(32)),
                    'role' => 'client',
                    'status' => 'active',
                ]);
            } else {
                // Ne jamais réécrire le rôle, le mot de passe ou le type de compte
                // lors de l'association d'un fournisseur social.
                $user->forceFill(['facebook_id' => $fbUser->getId()])->save();
            }

            return $this->loginPublicUser($user);
        } catch (Exception $e) {
            Log::error('Erreur Facebook Auth: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'Authentification Facebook échouée.');
        }
    }

    private function loginPublicUser(User $user)
    {
        if ((string) $user->status !== 'active') {
            return redirect()->route('login')
                ->with('error', 'Ce compte est suspendu ou désactivé. Contactez le support OVANIE.');
        }

        Auth::guard('web')->login($user, true);
        request()->session()->regenerate();
        app(GuestCartService::class)->mergeIntoUserCart(request(), $user);

        $fallback = $user->role === 'vendor'
            ? route('vendor.dashboard', absolute: false)
            : route('client.dashboard', absolute: false);

        return redirect()->intended($fallback);
    }

    private function isPublicAccount(User $user): bool
    {
        return ! (bool) $user->is_admin
            && in_array((string) $user->role, ['client', 'vendor'], true);
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? $fullName, $parts[1] ?? ''];
    }
}
