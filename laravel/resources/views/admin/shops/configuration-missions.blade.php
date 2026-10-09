@extends('admin.layouts.app')

@section('title', 'Missions boutiques | Administration OVANIE')
@section('page-title', 'Missions de régularisation boutiques')

@push('styles')
<style>
.cfg-wrap{display:grid;gap:18px}.cfg-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.cfg-head h2{margin:0;color:#10264f}.cfg-head p{margin:6px 0 0;color:#6f809b}.cfg-actions{display:flex;gap:8px;flex-wrap:wrap}.cfg-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 14px;border-radius:9px;border:1px solid #d9e2ef;background:#fff;color:#17345f;text-decoration:none;font-weight:800;font-size:12px;cursor:pointer}.cfg-btn.primary{background:#0d61e8;color:#fff;border-color:#0d61e8}.cfg-btn.danger{color:#b42318;border-color:#f3c4bf}.cfg-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.cfg-kpi{background:#fff;border:1px solid #e0e7f0;border-radius:13px;padding:16px}.cfg-kpi span{display:block;color:#7a899e;font-size:11px;font-weight:700}.cfg-kpi strong{display:block;margin-top:5px;color:#10264f;font-size:26px}.cfg-filter{display:grid;grid-template-columns:1fr 210px auto;gap:10px}.cfg-filter input,.cfg-filter select{height:42px;border:1px solid #d9e2ef;border-radius:9px;padding:0 12px}.cfg-list{display:grid;gap:12px}.cfg-card{background:#fff;border:1px solid #dfe7f0;border-radius:14px;overflow:hidden}.cfg-card-head{display:flex;justify-content:space-between;gap:15px;padding:16px 18px;border-bottom:1px solid #edf1f6}.cfg-card-head h3{margin:0;color:#10264f}.cfg-meta{margin-top:5px;color:#728096;font-size:11px}.cfg-badges{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}.cfg-badge{padding:6px 9px;border-radius:999px;font-size:10px;font-weight:850}.cfg-badge.ok{background:#eafaf2;color:#047857}.cfg-badge.warn{background:#fff4e8;color:#b45309}.cfg-body{display:grid;grid-template-columns:1fr 1.2fr;gap:18px;padding:16px 18px}.cfg-info{display:grid;gap:8px;color:#415879;font-size:12px}.cfg-location-warning{color:#b45309;font-weight:800}.cfg-form{display:grid;gap:10px}.cfg-form select,.cfg-form textarea{width:100%;border:1px solid #d9e2ef;border-radius:9px;padding:10px}.cfg-tasks{display:flex;gap:12px;flex-wrap:wrap;color:#344e73;font-size:11px}.cfg-tasks label{display:flex;align-items:center;gap:6px}.cfg-current{padding:10px 12px;background:#f7f9fc;border-radius:9px;color:#516785;font-size:11px}.cfg-card-foot{display:flex;justify-content:space-between;gap:10px;padding:12px 18px;border-top:1px solid #edf1f6;background:#fbfcfe}.cfg-empty{padding:48px;text-align:center;background:#fff;border:1px solid #e0e7f0;border-radius:14px;color:#617493}@media(max-width:900px){.cfg-kpis{grid-template-columns:repeat(2,1fr)}.cfg-body{grid-template-columns:1fr}.cfg-filter{grid-template-columns:1fr}}@media(max-width:520px){.cfg-kpis{grid-template-columns:1fr}.cfg-head,.cfg-card-head,.cfg-card-foot{flex-direction:column}.cfg-badges{justify-content:flex-start}}
</style>
@endpush

@section('content')
@php
    $missionStatusLabels = [
        'pending' => 'À démarrer',
        'in_progress' => 'En cours',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];
@endphp
<div class="cfg-wrap">
    <div class="cfg-head">
        <div>
            <h2>Boutiques à régulariser</h2>
            <p>Les boutiques incomplètes sont détectées automatiquement. Affectez un commercial pour finaliser leur configuration.</p>
        </div>
        <div class="cfg-actions"><a class="cfg-btn" href="{{ route('admin.shops.index') }}">Retour aux boutiques</a></div>
    </div>

    <section class="cfg-kpis">
        <article class="cfg-kpi"><span>À régulariser</span><strong>{{ $stats['total'] }}</strong></article>
        <article class="cfg-kpi"><span>Logistique non confirmée</span><strong>{{ $stats['logistics'] }}</strong></article>
        <article class="cfg-kpi"><span>Reversement non confirmé</span><strong>{{ $stats['payout'] }}</strong></article>
        <article class="cfg-kpi"><span>Missions actives</span><strong>{{ $stats['assigned'] }}</strong></article>
    </section>

    <form class="cfg-filter" method="GET">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Boutique, vendeur, téléphone ou email…">
        <select name="missing">
            <option value="">Tous les besoins</option>
            <option value="both" @selected(request('missing')==='both')>Logistique + reversement</option>
            <option value="logistics" @selected(request('missing')==='logistics')>Logistique</option>
            <option value="payout" @selected(request('missing')==='payout')>Reversement</option>
        </select>
        <button class="cfg-btn primary" type="submit">Filtrer</button>
    </form>

    @if($shops->isEmpty())
        <div class="cfg-empty">Aucune boutique ne nécessite de régularisation.</div>
    @else
        <section class="cfg-list">
            @foreach($shops as $shop)
                @php($mission = $shop->activeConfigurationAssignment)
                @php($needsLogistics = $shop->needsLogisticsConfiguration())
                @php($needsPayout = $shop->needsPayoutConfiguration())
                <article class="cfg-card">
                    <header class="cfg-card-head">
                        <div>
                            <h3>{{ $shop->name }}</h3>
                            <div class="cfg-meta">{{ $shop->user?->name ?: 'Vendeur' }} · {{ $shop->user?->phone ?: $shop->user?->email }} · {{ $shop->products_count }} produit(s)</div>
                        </div>
                        <div class="cfg-badges">
                            <span class="cfg-badge {{ $needsLogistics ? 'warn' : 'ok' }}">Logistique : {{ $needsLogistics ? 'à configurer' : 'configurée' }}</span>
                            <span class="cfg-badge {{ $needsPayout ? 'warn' : 'ok' }}">Reversement : {{ $needsPayout ? 'à configurer' : 'configuré' }}</span>
                        </div>
                    </header>
                    <div class="cfg-body">
                        <div class="cfg-info">
                            @unless($needsLogistics)
                                <div><strong>Mode logistique confirmé :</strong> {{ $shop->logistics_mode_label }}</div>
                            @endunless
                            @unless($needsPayout)
                                <div><strong>Mode de reversement confirmé :</strong> {{ $shop->payment_mode_label }}</div>
                            @endunless
                            <div>
                                <strong>Localisation :</strong>
                                @if($shop->configuration_location_verified)
                                    {{ $shop->configuration_location_label }}
                                @else
                                    <span class="cfg-location-warning">À vérifier avec le vendeur</span>
                                @endif
                            </div>
                            @if($mission)
                                <div class="cfg-current"><strong>Mission active :</strong> {{ $mission->commercial?->name }} · {{ $missionStatusLabels[$mission->status] ?? 'À démarrer' }}<br>
                                    @if($mission->configure_logistics) Logistique {{ $mission->logistics_completed_at ? '✓' : '⚠' }} · @endif
                                    @if($mission->configure_payout) Reversement {{ $mission->payout_completed_at ? '✓' : '⚠' }} · @endif
                                    @if($mission->manage_products) Produits {{ $mission->products_completed_at ? '✓' : '⚠' }} @endif
                                </div>
                            @endif
                        </div>
                        <form class="cfg-form" method="POST" action="{{ route('admin.shop-configuration-missions.store', $shop) }}">
                            @csrf
                            <select name="commercial_id" required>
                                <option value="">Affecter un commercial…</option>
                                @foreach($commercials as $commercial)
                                    <option value="{{ $commercial->id }}" @selected((int)old('commercial_id', $mission?->commercial_id) === $commercial->id)>{{ $commercial->name }} — {{ $commercial->email }}</option>
                                @endforeach
                            </select>
                            <div class="cfg-tasks">
                                <label><input type="checkbox" name="configure_logistics" value="1" @checked($needsLogistics)> Configurer la logistique</label>
                                <label><input type="checkbox" name="configure_payout" value="1" @checked($needsPayout)> Configurer le reversement</label>
                                <label><input type="checkbox" name="manage_products" value="1" checked> Ajouter / mettre à jour les produits</label>
                            </div>
                            <textarea name="notes" rows="2" placeholder="Consigne facultative pour le commercial…"></textarea>
                            <button class="cfg-btn primary" type="submit">{{ $mission ? 'Réaffecter la mission' : 'Affecter la mission' }}</button>
                        </form>
                    </div>
                    @if($mission)
                        <footer class="cfg-card-foot">
                            <span class="cfg-meta">Mission affectée le {{ optional($mission->assigned_at)->format('d/m/Y à H:i') }}</span>
                            <form method="POST" action="{{ route('admin.shop-configuration-missions.cancel', $mission) }}">@csrf @method('DELETE')<button class="cfg-btn danger" type="submit">Annuler la mission</button></form>
                        </footer>
                    @endif
                </article>
            @endforeach
        </section>
        <div>{{ $shops->links() }}</div>
    @endif
</div>
@endsection
