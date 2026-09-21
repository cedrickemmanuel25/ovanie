<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * La suppression physique est interdite : les commandes, paiements, factures,
     * litiges et historiques doivent rester traçables. Les données personnelles
     * sont anonymisées par le même service que l'espace client OVANIE.
     */
    public function destroy(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $user = $request->user();

        if (filled($user->google_id) || filled($user->facebook_id)) {
            return Redirect::route('client.settings')->with(
                'error',
                'Pour sécuriser la suppression de ce compte social, demandez le code à usage unique depuis les paramètres de votre espace client.'
            );
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $deletion->assertCanSelfDelete($user);
        $deletion->anonymize($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with(
            'success',
            'Votre compte a été supprimé et vos données personnelles ont été anonymisées.'
        );
    }
}
