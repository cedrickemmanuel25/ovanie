@extends('layouts.staff')
@section('title', 'Réponses & procédures | OVANIE Support')
@section('content')
<div class="page-header">
    <div class="page-header-main"><span class="page-header-icon"><i data-lucide="book-open-check"></i></span><div><h1 class="page-title">Réponses & procédures</h1><p class="page-subtitle">Référentiel interne des réponses officielles et procédures à appliquer. Les conseillers peuvent s’y référer et les assistants automatisés n’utilisent que les contenus publiés.</p></div></div>
    @if(auth('admin')->user()->hasStaffPermission('support.ai.knowledge.manage'))<div class="page-actions"><a class="btn btn-primary" href="{{ route('support.knowledge.create') }}"><i data-lucide="file-plus-2"></i>Nouvelle procédure</a></div>@endif
</div>

<div class="grid kpi-grid">
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">Publiées</span><span class="kpi-icon"><i data-lucide="book-open-check"></i></span></div><div class="kpi-value">{{ $stats['published_for_ai'] }}</div><div class="kpi-foot">Disponibles aux conseillers et assistants</div></div>
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">Brouillons</span><span class="kpi-icon"><i data-lucide="file-pen-line"></i></span></div><div class="kpi-value">{{ $stats['draft'] }}</div><div class="kpi-foot">En préparation</div></div>
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">Archivées</span><span class="kpi-icon"><i data-lucide="archive"></i></span></div><div class="kpi-value">{{ $stats['archived'] }}</div><div class="kpi-foot">Non utilisées</div></div>
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">À revoir</span><span class="kpi-icon"><i data-lucide="calendar-x"></i></span></div><div class="kpi-value">{{ $stats['expired'] }}</div><div class="kpi-foot">Contenus expirés</div></div>
</div>

<div class="toolbar"><form class="filters" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Rechercher un titre, une catégorie ou un mot-clé"><select name="status"><option value="">Tous les statuts</option><option value="published" @selected(request('status')==='published')>Publié</option><option value="draft" @selected(request('status')==='draft')>Brouillon</option><option value="archived" @selected(request('status')==='archived')>Archivé</option></select><button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button></form></div>

<section class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Procédure</th><th>Catégorie</th><th>Statut</th><th>Version</th><th>Validation</th><th>Mise à jour / expiration</th><th>Action</th></tr></thead><tbody>
@forelse($articles as $article)
@php $available = $article->status === 'published' && $article->published_at?->lte(now()) && (!$article->expires_at || $article->expires_at->isFuture()); @endphp
<tr>
    <td><span class="record-title">{{ $article->title }}</span><span class="record-sub">{{ \Illuminate\Support\Str::limit(strip_tags($article->content),105) }}</span></td>
    <td>{{ $article->category }}<span class="record-sub">{{ $article->source_reference ?: 'Source OVANIE interne' }}</span></td>
    <td><span class="status {{ $available ? 'active' : ($article->status === 'published' ? 'unavailable' : $article->status) }}">{{ $available ? 'Disponible' : ($article->status === 'published' ? 'À revoir' : $article->status) }}</span></td>
    <td>v{{ $article->version }}</td>
    <td>{{ $article->approver?->name ?: 'Non validé' }}<span class="record-sub">{{ $article->reviewed_at?->format('d/m/Y') ?: '—' }}</span></td>
    <td>{{ $article->updated_at?->format('d/m/Y') }}<span class="record-sub">{{ $article->expires_at ? 'Expire le '.$article->expires_at->format('d/m/Y') : 'Sans expiration' }}</span></td>
    <td>@if(auth('admin')->user()->hasStaffPermission('support.ai.knowledge.manage'))<a class="btn" href="{{ route('support.knowledge.edit',$article) }}">Modifier</a>@else — @endif</td>
</tr>
@empty<tr><td colspan="7"><div class="empty"><i data-lucide="book-open"></i><div>Aucune procédure enregistrée.</div></div></td></tr>@endforelse
</tbody></table></div><div class="pagination-row"><span>{{ $articles->total() }} procédure(s)</span><div>{{ $articles->links() }}</div></div></section>
@endsection
