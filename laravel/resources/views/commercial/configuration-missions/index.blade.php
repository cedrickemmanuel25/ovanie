@extends('layouts.staff')
@section('title','Missions boutiques | Commercial OVANIE')
@section('inline_styles')
.mission-grid{display:grid;gap:13px}.mission-card{background:#fff;border:1px solid #dfe7f0;border-radius:14px;overflow:hidden}.mission-head{display:flex;justify-content:space-between;gap:14px;padding:17px 18px;border-bottom:1px solid #edf1f6}.mission-head h2{margin:0;color:#10264f;font-size:14px}.mission-meta{margin-top:5px;color:#74839a;font-size:9.5px}.mission-status{height:max-content;padding:6px 9px;border-radius:999px;background:#fff4e8;color:#a65705;font-size:9px;font-weight:850}.mission-status.progress{background:#eef4ff;color:#075ee8}.mission-body{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:15px 18px}.mission-task{padding:11px;border-radius:10px;background:#f7f9fc}.mission-task strong{display:block;color:#16365f;font-size:10px}.mission-task span{display:block;margin-top:5px;color:#70819b;font-size:9px}.mission-task.ok{background:#eefaf4}.mission-task.ok strong{color:#047857}.mission-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 18px;border-top:1px solid #edf1f6}.mission-empty{padding:52px 20px;text-align:center}.mission-empty h3{color:#10264f}.mission-empty p{color:#74839a}.page-actions{display:flex;gap:8px}@media(max-width:760px){.mission-body{grid-template-columns:1fr}.mission-head,.mission-foot{flex-direction:column;align-items:stretch}}
@endsection
@section('content')
<div class="page-header"><div><h1 class="page-title">Missions boutiques</h1><p class="page-subtitle">Boutiques historiques affectées par l’administrateur. Elles sont séparées des boutiques que vous avez ouvertes vous-même.</p></div><div class="page-actions"><a class="btn" href="{{ route('commercial.vendors.index') }}">Mes boutiques</a></div></div>
@if($missions->isEmpty())
<section class="card mission-empty"><h3>Aucune mission boutique en cours</h3><p>Lorsqu’un administrateur vous affecte une boutique historique à régulariser, elle apparaît ici.</p></section>
@else
<section class="mission-grid">
@foreach($missions as $mission)
<article class="mission-card">
<header class="mission-head"><div><h2>{{ $mission->shop?->name }}</h2><div class="mission-meta">{{ $mission->shop?->user?->name }} · {{ $mission->shop?->configuration_location_label ?? 'À vérifier avec le vendeur' }}</div></div><span class="mission-status {{ $mission->status==='in_progress'?'progress':'' }}">{{ $mission->status==='in_progress'?'En cours':'À démarrer' }}</span></header>
<div class="mission-body">
@if($mission->configure_logistics)<div class="mission-task {{ $mission->logistics_completed_at?'ok':'' }}"><strong>{{ $mission->logistics_completed_at?'✓':'⚠' }} Logistique</strong><span>{{ $mission->logistics_completed_at?'Configuration terminée':'À configurer avec le vendeur' }}</span></div>@endif
@if($mission->configure_payout)<div class="mission-task {{ $mission->payout_completed_at?'ok':'' }}"><strong>{{ $mission->payout_completed_at?'✓':'⚠' }} Reversement</strong><span>{{ $mission->payout_completed_at?'Coordonnées confirmées':'À configurer avec le vendeur' }}</span></div>@endif
@if($mission->manage_products)<div class="mission-task"><strong>Produits</strong><span>{{ $mission->shop?->products()->count() ?? 0 }} produit(s) · ajout autorisé pendant la mission</span></div>@endif
</div>
<footer class="mission-foot"><span class="mission-meta">Affectée le {{ optional($mission->assigned_at)->format('d/m/Y H:i') }}</span><a class="btn btn-orange" href="{{ route('commercial.configuration-missions.show',$mission) }}">Ouvrir la mission</a></footer>
</article>
@endforeach
</section><div class="pagination-row">{{ $missions->links() }}</div>
@endif
@endsection
