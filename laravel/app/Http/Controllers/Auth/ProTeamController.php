<?php

namespace App\Http\Controllers\Auth;

use App\Models\ProCompanyInvitation;
use App\Models\ProCompanyMembership;
use App\Models\ProOrganization;
use App\Notifications\ProCompanyInvitationNotification;
use App\Services\Sms\SmsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** ACC-13 : seul un administrateur de l'organisation invite et gère les collaborateurs. */
class ProTeamController extends ProAccessController
{
    public function index(ProOrganization $organization): View
    {
        $this->authorizeAdmin($organization);

        return view('pro.auth.team', [
            'organization' => $organization,
            'members' => $organization->memberships()->with('user')->orderByRaw("status = 'active' desc")->orderBy('id')->get(),
            'invitations' => $organization->invitations()->with('inviter')->whereIn('status', ['pending', 'expired'])->latest()->get(),
            'permissions' => $this->permissions(),
            'expiryOptions' => (array) config('ovanie_pro.invitation_expiry_days', [7 => '7 jours']),
            'phoneCodes' => $this->phoneCodes(),
            'user' => $this->proUser(),
        ]);
    }

    public function invite(Request $request, ProOrganization $organization): RedirectResponse
    {
        $this->authorizeAdmin($organization);
        $this->mergePhone($request);
        if ($request->filled('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone_country' => $this->phoneCodeRule(),
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+[0-9]{8,15}$/'],
            'permission' => ['required', Rule::in(array_keys($this->permissions()))],
            'expires_in' => ['required', Rule::in(array_map('strval', array_keys((array) config('ovanie_pro.invitation_expiry_days', []))))],
        ], [
            'email.required_without' => 'Indiquez l’e-mail ou le téléphone du collaborateur.',
            'phone.required_without' => 'Indiquez l’e-mail ou le téléphone du collaborateur.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
        ], ['permission' => 'permission', 'expires_in' => 'durée de validité']);

        $alreadyMember = $organization->memberships()->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where(fn ($q) => $q
                ->when($data['email'] ?? null, fn ($q, $email) => $q->orWhereRaw('LOWER(email) = ?', [$email]))
                ->when($data['phone'] ?? null, fn ($q, $phone) => $q->orWhere('phone', $phone))))
            ->exists();
        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => 'Cette personne fait déjà partie de votre organisation.']);
        }

        $pending = $organization->invitations()->where('status', 'pending')->where('expires_at', '>', now())
            ->where(fn ($q) => $q
                ->when($data['email'] ?? null, fn ($q, $email) => $q->orWhere('email', $email))
                ->when($data['phone'] ?? null, fn ($q, $phone) => $q->orWhere('phone', $phone)))
            ->exists();
        if ($pending) {
            throw ValidationException::withMessages(['email' => 'Une invitation est déjà en attente pour ce contact. Renvoyez-la depuis la liste.']);
        }

        $token = Str::random(48);
        $invitation = $organization->invitations()->create([
            'inviter_pro_user_id' => $this->proUser()->id,
            'organization_name' => $organization->display_name,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'permission' => $data['permission'],
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addDays((int) $data['expires_in']),
        ]);

        return $this->deliver($invitation, $token, 'Invitation créée.');
    }

    /** Renvoyer = nouveau jeton et nouvelle échéance ; l'ancien lien cesse de fonctionner. */
    public function resend(ProOrganization $organization, ProCompanyInvitation $invitation): RedirectResponse
    {
        $this->authorizeAdmin($organization);
        abort_unless((int) $invitation->pro_organization_id === (int) $organization->id && in_array($invitation->status, ['pending', 'expired'], true), 404);

        $token = Str::random(48);
        $days = max(1, (int) round($invitation->created_at->diffInDays($invitation->expires_at ?? $invitation->created_at->copy()->addDays(7))));
        $invitation->forceFill([
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addDays($days),
        ])->save();

        return $this->deliver($invitation, $token, 'Invitation renvoyée. L’ancien lien n’est plus valable.');
    }

    public function cancel(ProOrganization $organization, ProCompanyInvitation $invitation): RedirectResponse
    {
        $this->authorizeAdmin($organization);
        abort_unless((int) $invitation->pro_organization_id === (int) $organization->id, 404);

        if ($invitation->status === 'pending') {
            $invitation->forceFill(['status' => 'cancelled'])->save();
        }

        return back()->with('success', 'Invitation annulée.');
    }

    /** Changer la permission ou désactiver un collaborateur (la désactivation retire l'accès). */
    public function updateMember(Request $request, ProOrganization $organization, ProCompanyMembership $membership): RedirectResponse
    {
        $this->authorizeAdmin($organization);
        abort_unless((int) $membership->pro_organization_id === (int) $organization->id, 404);

        $data = $request->validate([
            'permission' => ['required', Rule::in(array_keys($this->permissions()))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ((int) $membership->pro_user_id === (int) $organization->owner_pro_user_id
            && ($data['status'] !== 'active' || $data['permission'] !== 'administrator')) {
            return back()->withErrors(['member' => 'Le créateur de l’organisation reste administrateur.']);
        }

        $membership->forceFill($data)->save();

        return back()->with('success', 'Accès du collaborateur mis à jour.');
    }

    private function deliver(ProCompanyInvitation $invitation, string $token, string $message): RedirectResponse
    {
        $url = route('pro.invitation.show', ['token' => $token]);

        if ($invitation->email) {
            try {
                Notification::route('mail', $invitation->email)->notify(new ProCompanyInvitationNotification(
                    $url,
                    $invitation->organization->display_name,
                    $this->permissions()[$invitation->permission] ?? $invitation->permission,
                    $this->proUser()->full_name,
                    $invitation->expires_at->format('d/m/Y'),
                ));
                $message .= ' Un e-mail a été envoyé à ' . $invitation->email . '.';
            } catch (\Throwable $e) {
                Log::warning('Invitation OVANIE PRO non envoyée par e-mail', ['invitation' => $invitation->id, 'error' => $e->getMessage()]);
                $message .= ' L’e-mail n’a pas pu partir.';
            }
        }

        // Une invitation créée uniquement avec un téléphone doit réellement être livrée.
        // Auparavant le backend acceptait le téléphone mais n'envoyait jamais de SMS.
        if ($invitation->phone) {
            try {
                $sent = SmsManager::send(
                    $invitation->phone,
                    'OVANIE PRO : ' . $this->proUser()->full_name . ' vous invite à rejoindre '
                    . $invitation->organization->display_name . '. Invitation : ' . $url
                );
                $message .= $sent
                    ? ' Un SMS a été envoyé au ' . $invitation->phone . '.'
                    : ' Le SMS n’est pas configuré ou n’a pas pu être envoyé : partagez le lien ci-dessous.';
            } catch (\Throwable $e) {
                Log::warning('Invitation OVANIE PRO non envoyée par SMS', ['invitation' => $invitation->id, 'error' => $e->getMessage()]);
                $message .= ' Le SMS n’a pas pu partir : partagez le lien ci-dessous.';
            }
        }

        // Le lien n'est affiché qu'une fois : il n'est jamais stocké en clair.
        return back()->with('success', $message)->with('invitation_link', $url);
    }

    private function authorizeAdmin(ProOrganization $organization): void
    {
        abort_unless($this->proUser()->permissionIn($organization) === 'administrator', 403, 'Seul un administrateur de l’organisation peut gérer les collaborateurs.');
    }
}
