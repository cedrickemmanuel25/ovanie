@extends('layouts.staff')

@section('title', 'Clients | Commercial OVANIE')

@section('inline_styles')
.client-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.client-stat{position:relative;min-height:132px;padding:18px;overflow:hidden}.client-stat-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.client-stat-label{color:#6f7f96;font-size:9.5px;font-weight:800}.client-stat-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#edf4ff;color:#174b8f}.client-stat-icon.orange{background:#fff2e8;color:#f2690a}.client-stat-icon.green{background:#eafaf2;color:#078255}.client-stat strong{display:block;margin-top:13px;color:#10264f;font-size:25px;line-height:1}.client-stat p{margin:7px 0 0;color:#8290a3;font-size:9px;line-height:1.45}
.client-toolbar{display:grid;grid-template-columns:minmax(0,1fr) 230px auto;gap:10px;margin-bottom:18px}.client-toolbar input,.client-toolbar select{height:46px;border:1px solid #d9e2ed;border-radius:10px;background:#fff;padding:0 13px;color:#17345f;font-size:10.5px;outline:none}.client-toolbar input:focus,.client-toolbar select:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.08)}
.client-list{display:grid;gap:12px}.client-card{padding:0;overflow:hidden}.client-card-main{display:grid;grid-template-columns:minmax(260px,1.25fr) minmax(210px,.9fr) minmax(170px,.65fr) auto;gap:18px;align-items:center;padding:17px 18px}.client-identity{display:flex;align-items:center;gap:12px;min-width:0}.client-avatar{width:46px;height:46px;display:grid;place-items:center;flex:0 0 46px;border-radius:14px;background:linear-gradient(135deg,#153f78,#285f9f);color:#fff;font-size:13px;font-weight:900}.client-name{min-width:0}.client-name h3{margin:0;color:#10264f;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.client-name p{margin:4px 0 0;color:#7c899c;font-size:9.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.client-contact{display:grid;gap:6px}.client-contact-line{display:flex;align-items:center;gap:7px;color:#3e5677;font-size:9.5px;min-width:0}.client-contact-line svg{width:15px;flex:0 0 15px;color:#7889a1}.client-contact-line span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.client-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.client-metric{padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fafcff}.client-metric span{display:block;color:#8a96a8;font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.client-metric strong{display:block;margin-top:5px;color:#17345f;font-size:11px}.client-actions{display:flex;justify-content:flex-end;gap:7px;flex-wrap:wrap}.client-actions .btn{min-height:39px;padding:0 12px}.client-card-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 18px;border-top:1px solid #edf1f6;background:#fbfcfe}.client-status{display:inline-flex;align-items:center;gap:6px;padding:5px 8px;border-radius:999px;font-size:8.5px;font-weight:850}.client-status.active{background:#eafaf2;color:#087b50}.client-status.inactive{background:#f2f4f7;color:#596779}.client-status.suspended{background:#fff0f0;color:#b42318}.client-date{color:#8793a5;font-size:8.8px}.empty-clients{padding:55px 20px;text-align:center}.empty-clients-icon{width:58px;height:58px;margin:0 auto 13px;display:grid;place-items:center;border-radius:16px;background:#edf4ff;color:#174b8f}.empty-clients h3{margin:0;color:#10264f;font-size:14px}.empty-clients p{max-width:430px;margin:7px auto 16px;color:#7e8b9d;font-size:9.8px;line-height:1.55}.pagination-wrap{margin-top:16px}
@media(max-width:1180px){.client-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.client-card-main{grid-template-columns:minmax(240px,1fr) minmax(210px,.8fr);}.client-actions{justify-content:flex-start}.client-metrics{order:3}.client-actions{order:4}}
@media(max-width:760px){.client-stats{grid-template-columns:1fr 1fr}.client-toolbar{grid-template-columns:1fr}.client-card-main{grid-template-columns:1fr;gap:14px}.client-actions{display:grid;grid-template-columns:1fr 1fr}.client-actions .btn{width:100%}.client-card-foot{align-items:flex-start;flex-direction:column}.page-actions{width:100%}.page-actions .btn{width:100%;justify-content:center}}
@media(max-width:480px){.client-stats{grid-template-columns:1fr}.client-actions{grid-template-columns:1fr}.client-metrics{grid-template-columns:1fr 1fr}}
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Clients</h1>
        <p class="page-subtitle">Consultez uniquement les clients créés et suivis depuis votre espace Commercial.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-orange" href="{{ route('commercial.clients.create') }}"><i data-lucide="user-plus"></i>Créer un client</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:16px">{{ session('success') }}</div>
@endif

<div class="client-stats">
    <section class="card client-stat">
        <div class="client-stat-head"><span class="client-stat-label">Clients suivis</span><span class="client-stat-icon"><i data-lucide="users"></i></span></div>
        <strong>{{ number_format($stats['total']) }}</strong>
        <p>Comptes créés depuis votre espace Commercial.</p>
    </section>
    <section class="card client-stat">
        <div class="client-stat-head"><span class="client-stat-label">Comptes actifs</span><span class="client-stat-icon green"><i data-lucide="user-check"></i></span></div>
        <strong>{{ number_format($stats['active']) }}</strong>
        <p>Clients pouvant accéder à leur compte.</p>
    </section>
    <section class="card client-stat">
        <div class="client-stat-head"><span class="client-stat-label">Avec commandes</span><span class="client-stat-icon orange"><i data-lucide="shopping-bag"></i></span></div>
        <strong>{{ number_format($stats['with_orders']) }}</strong>
        <p>Clients ayant déjà passé au moins une commande.</p>
    </section>
    <section class="card client-stat">
        <div class="client-stat-head"><span class="client-stat-label">Nouveaux ce mois</span><span class="client-stat-icon"><i data-lucide="calendar-plus"></i></span></div>
        <strong>{{ number_format($stats['new_this_month']) }}</strong>
        <p>Nouveaux comptes ajoutés depuis le début du mois.</p>
    </section>
</div>

<form class="client-toolbar" method="GET" action="{{ route('commercial.clients.index') }}">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher par nom, téléphone, e-mail ou ville…" autocomplete="off">
    <select name="status" aria-label="Filtrer par statut">
        <option value="">Tous les statuts</option>
        <option value="active" @selected(request('status') === 'active')>Actifs</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option>
        <option value="suspended" @selected(request('status') === 'suspended')>Suspendus</option>
    </select>
    <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
</form>

@if($clients->count())
    <div class="client-list">
        @foreach($clients as $client)
            @php
                $displayName = $client->name ?: trim(($client->first_name ?? '').' '.($client->last_name ?? ''));
                $words = preg_split('/\s+/', trim($displayName)) ?: [];
                $initials = collect($words)->filter()->take(2)->map(fn($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                $status = strtolower((string) ($client->status ?: 'inactive'));
                $statusLabel = match($status) {
                    'active' => 'Actif',
                    'suspended' => 'Suspendu',
                    default => 'Inactif',
                };
                $leadQuery = [
                    'user_id' => $client->id,
                    'contact_name' => $displayName,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'city' => $client->city,
                    'lead_type' => 'buyer',
                    'source' => 'client',
                    'status' => 'new',
                ];
            @endphp

            <article class="card client-card">
                <div class="client-card-main">
                    <div class="client-identity">
                        <div class="client-avatar">{{ $initials ?: 'CL' }}</div>
                        <div class="client-name">
                            <h3>{{ $displayName ?: 'Client sans nom' }}</h3>
                            <p>Client OVANIE #{{ $client->id }}</p>
                        </div>
                    </div>

                    <div class="client-contact">
                        <div class="client-contact-line"><i data-lucide="mail"></i><span>{{ $client->email ?: 'E-mail non renseigné' }}</span></div>
                        <div class="client-contact-line"><i data-lucide="phone"></i><span>{{ $client->phone ?: 'Téléphone non renseigné' }}</span></div>
                        <div class="client-contact-line"><i data-lucide="map-pin"></i><span>{{ $client->city ?: 'Ville non renseignée' }}</span></div>
                    </div>

                    <div class="client-metrics">
                        <div class="client-metric"><span>Commandes</span><strong>{{ number_format($client->orders_count) }}</strong></div>
                        <div class="client-metric"><span>Inscription</span><strong>{{ optional($client->created_at)->format('d/m/Y') }}</strong></div>
                    </div>

                    <div class="client-actions">
                        <a class="btn" href="{{ route('commercial.orders.index', ['q' => $client->email ?: $client->phone]) }}"><i data-lucide="clipboard-list"></i>Commandes</a>
                        <a class="btn btn-orange" href="{{ route('commercial.leads.create', $leadQuery) }}"><i data-lucide="target"></i>Opportunité</a>
                        @if($client->phone)
                            <a class="btn" href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}" aria-label="Appeler {{ $displayName }}"><i data-lucide="phone-call"></i>Appeler</a>
                        @endif
                    </div>
                </div>

                <div class="client-card-foot">
                    <span class="client-status {{ $status }}"><i data-lucide="{{ $status === 'active' ? 'circle-check' : ($status === 'suspended' ? 'circle-slash' : 'circle-minus') }}"></i>{{ $statusLabel }}</span>
                    <span class="client-date">Créé le {{ optional($client->created_at)->format('d/m/Y à H:i') }}</span>
                </div>
            </article>
        @endforeach
    </div>

    <div class="pagination-wrap">{{ $clients->links() }}</div>
@else
    <section class="card empty-clients">
        <div class="empty-clients-icon"><i data-lucide="users"></i></div>
        <h3>Aucun client trouvé</h3>
        <p>{{ request()->filled('q') || request()->filled('status') ? 'Aucun client ne correspond aux filtres sélectionnés.' : 'Créez votre premier client pour commencer son suivi commercial.' }}</p>
        @unless(request()->filled('q') || request()->filled('status'))
            <a class="btn btn-orange" href="{{ route('commercial.clients.create') }}"><i data-lucide="user-plus"></i>Créer un client</a>
        @endunless
    </section>
@endif
@endsection
