@extends('pro.auth.layout')
@section('title', 'Collaborateurs')
@section('shell', 'pa-shell--lg')
@section('content')
<div class="pa-row">
    <div>
        <p class="pa-kicker">{{ $organization->display_name }}</p>
        <h1 class="pa-title">Collaborateurs</h1>
        <p class="pa-sub">Invitez vos équipes. Elles accèdent à l’entreprise sans ressaisir ses informations ni ses documents.</p>
    </div>
    <a class="pa-btn pa-btn--ghost pa-btn--sm" href="{{ route('pro.account') }}#entreprises">‹ Mon compte</a>
</div>

@include('pro.auth.partials.alerts')
@if(session('invitation_link'))
    <div class="pa-alert pa-alert--info">
        Lien d’invitation à transmettre (affiché une seule fois) :
        <div class="pa-copy"><input class="pa-input" id="invite-link" value="{{ session('invitation_link') }}" readonly><button class="pa-btn pa-btn--sm" type="button" data-copy="invite-link">Copier</button></div>
    </div>
@endif

<section class="pa-card">
    <h2 class="pa-h2">Inviter un collaborateur</h2>
    <p class="pa-section__intro" style="margin-bottom:14px">Indiquez l’e-mail et/ou le téléphone. Le lien est personnel, à usage unique et expire automatiquement.</p>
    <form method="POST" action="{{ route('pro.team.invite', $organization) }}">
        @csrf
        <div class="pa-grid">
            <div class="pa-field @error('email') has-error @enderror">
                <label class="pa-label" for="inv_email">E-mail</label>
                <input class="pa-input" id="inv_email" name="email" type="email" value="{{ old('email') }}" maxlength="255" placeholder="collaborateur@entreprise.com">
                @error('email')<p class="pa-error">{{ $message }}</p>@enderror
            </div>
            <div class="pa-field @error('phone') has-error @enderror">
                <label class="pa-label" for="inv_phone">Téléphone</label>
                <div class="pa-phone">
                    <select name="phone_country" aria-label="Indicatif">
                        @foreach($phoneCodes as $code => $label)<option value="{{ $code }}" @selected(old('phone_country', '+225') === $code)>{{ $label }}</option>@endforeach
                    </select>
                    <input id="inv_phone" name="phone" type="tel" value="{{ old('phone') ? \Illuminate\Support\Str::after(old('phone'), old('phone_country', '+225')) : '' }}" maxlength="20" placeholder="07 00 00 00 00">
                </div>
                @error('phone')<p class="pa-error">{{ $message }}</p>@enderror
            </div>
            <div class="pa-field @error('permission') has-error @enderror">
                <label class="pa-label" for="inv_permission">Permission<span class="pa-req">*</span></label>
                <select class="pa-select" id="inv_permission" name="permission" required>
                    @foreach($permissions as $key => $label)<option value="{{ $key }}" @selected(old('permission', 'read_only') === $key)>{{ $label }}</option>@endforeach
                </select>
                @error('permission')<p class="pa-error">{{ $message }}</p>@enderror
            </div>
            <div class="pa-field @error('expires_in') has-error @enderror">
                <label class="pa-label" for="inv_expiry">Validité<span class="pa-req">*</span></label>
                <select class="pa-select" id="inv_expiry" name="expires_in" required>
                    @foreach($expiryOptions as $days => $label)<option value="{{ $days }}" @selected((string) old('expires_in', 7) === (string) $days)>{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
        <button class="pa-btn" type="submit">Envoyer l’invitation</button>
    </form>
</section>

<section class="pa-card">
    <h2 class="pa-h2">Invitations en attente</h2>
    @if($invitations->isEmpty())
        <div class="pa-empty" style="margin-top:12px">Aucune invitation en attente.</div>
    @else
        <div class="pa-items" style="margin-top:12px">
            @foreach($invitations as $invitation)
                @php($expired = $invitation->status === 'expired' || $invitation->expires_at?->isPast())
                <div class="pa-item">
                    <div class="pa-item__body">
                        <span class="pa-item__title">{{ $invitation->email ?: $invitation->phone }}</span>
                        <span class="pa-item__meta">{{ $permissions[$invitation->permission] ?? $invitation->permission }} · {{ $expired ? 'Expirée le' : 'Expire le' }} {{ $invitation->expires_at?->format('d/m/Y') }}</span>
                    </div>
                    <span class="pa-tag {{ $expired ? 'pa-tag--red' : 'pa-tag--amber' }}">{{ $expired ? 'Expirée' : 'En attente' }}</span>
                    <div class="pa-item__actions">
                        <form method="POST" action="{{ route('pro.team.resend', [$organization, $invitation]) }}">@csrf<button class="pa-btn pa-btn--light pa-btn--sm" type="submit">Renvoyer</button></form>
                        @unless($expired)
                            <form method="POST" action="{{ route('pro.team.cancel', [$organization, $invitation]) }}" data-confirm="Annuler cette invitation ? Le lien ne fonctionnera plus.">@csrf<button class="pa-btn pa-btn--danger pa-btn--sm" type="submit">Annuler</button></form>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="pa-card">
    <h2 class="pa-h2">Membres</h2>
    @error('member')<div class="pa-alert pa-alert--error" style="margin-top:12px">{{ $message }}</div>@enderror
    <div class="pa-items" style="margin-top:12px">
        @foreach($members as $member)
            @php($isOwner = (int) $member->pro_user_id === (int) $organization->owner_pro_user_id)
            <div class="pa-item">
                <div class="pa-item__body">
                    <span class="pa-item__title">{{ $member->user?->full_name ?? 'Compte supprimé' }}@if((int) $member->pro_user_id === (int) $user->id) (vous)@endif</span>
                    <span class="pa-item__meta">{{ $member->user?->email }}{{ $isOwner ? ' · Créateur' : '' }}</span>
                </div>
                @if($isOwner)
                    <span class="pa-tag pa-tag--blue">{{ $permissions['administrator'] ?? 'Administrateur' }}</span>
                @else
                    <form method="POST" action="{{ route('pro.team.member.update', [$organization, $member]) }}" class="pa-inline">
                        @csrf @method('PUT')
                        <select class="pa-select" name="permission" aria-label="Permission">
                            @foreach($permissions as $key => $label)<option value="{{ $key }}" @selected($member->permission === $key)>{{ $label }}</option>@endforeach
                        </select>
                        <select class="pa-select" name="status" aria-label="Statut">
                            <option value="active" @selected($member->status === 'active')>Actif</option>
                            <option value="inactive" @selected($member->status !== 'active')>Désactivé</option>
                        </select>
                        <button class="pa-btn pa-btn--light pa-btn--sm" type="submit">Enregistrer</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endsection
