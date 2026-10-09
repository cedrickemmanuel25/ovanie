<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Page « Utilisateurs » de l'administration : création, modification et anonymisation d'un compte.
 * Le formulaire de la page envoie toujours vers la même adresse, avec form_mode = create ou edit.
 */
class AdminUserController extends Controller
{
    public function save(Request $request): RedirectResponse
    {
        $editing = $request->input('form_mode') === 'edit';
        $user = $editing ? User::query()->findOrFail((int) $request->input('user_id')) : null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'client', 'vendor', 'logistique'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ], [], ['name' => 'nom', 'email' => 'e-mail', 'phone' => 'téléphone', 'role' => 'rôle', 'status' => 'statut']);

        if ($user) {
            // Un administrateur ne peut pas se retirer lui-même ses droits ni se suspendre.
            if ((int) $user->id === (int) $request->user()->id && ($data['role'] !== $user->role || $data['status'] !== 'active')) {
                throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas modifier le rôle ni le statut de votre propre compte.']);
            }
            $user->update($data);

            return redirect()->route('admin.users.index')->with('success', 'Le compte de ' . $user->name . ' a été mis à jour.');
        }

        // Mot de passe provisoire aléatoire, affiché une seule fois à l'administrateur.
        $password = Str::password(14);
        User::create($data + ['password' => Hash::make($password)]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Compte créé pour ' . $data['email'] . '. Mot de passe provisoire à transmettre : ' . $password);
    }

    /** Le bouton « Modifier » de la liste envoie en PUT vers l'adresse du compte. */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['form_mode' => 'edit', 'user_id' => $user->id]);

        return $this->save($request);
    }

    public function destroy(Request $request, User $user, AccountDeletionService $deletionService): RedirectResponse
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas anonymiser votre propre compte.']);
        }

        try {
            if ($user->role === 'client') {
                $deletionService->assertCanSelfDelete($user);
            }
            $deletionService->anonymize($user);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Le compte a été anonymisé et suspendu. Les historiques commerciaux et financiers sont conservés.');
    }
}
