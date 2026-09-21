@extends('layouts.staff')
@section('title', 'Base de connaissances IA | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">Base de connaissances IA</h1><p class="page-subtitle">Les agents IA consultent exclusivement les articles publiés, approuvés, déjà arrivés à leur date de publication et non expirés.</p></div>
    @if(auth('admin')->user()->hasStaffPermission('support.ai.knowledge.manage'))<div class="page-actions"><a class="btn btn-primary" href="{{ route('support.knowledge.create') }}"><i data-lucide="file-plus-2"></i>Nouvel article</a></div>@endif
</div>

<div class="grid kpi-grid">
    @foreach([
        ['label'=>'Disponibles pour l’IA','value'=>$stats['published_for_ai'],'icon'=>'book-open-check'],
        ['label'=>'Brouillons','value'=>$stats['draft'],'icon'=>'file-pen-line'],
        ['label'=>'Archivés','value'=>$stats['archived'],'icon'=>'archive'],
        ['label'=>'Publiés mais expirés','value'=>$stats['expired'],'icon'=>'calendar-x'],
    ] as $stat)
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">{{ $stat['label'] }}</span><span class="kpi-icon"><i data-lucide="{{ $stat['icon'] }}"></i></span></div><div class="kpi-value">{{ $stat['value'] }}</div></div>
    @endforeach
</div>

<form class="filters" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Titre, catégorie ou contenu"><select name="status"><option value="">Tous les statuts</option>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button></form>

<section class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Article</th><th>Catégorie / source</th><th>Version</th><th>Statut IA</th><th>Validation</th><th>Expiration</th><th>Actions</th></tr></thead><tbody>
@forelse($articles as $article)
@php $available = $article->status === 'published' && $article->published_at?->lte(now()) && (!$article->expires_at || $article->expires_at->isFuture()); @endphp
<tr>
    <td><span class="record-title">{{ $article->title }}</span><span class="record-sub">{{ \Illuminate\Support\Str::limit(strip_tags($article->content),100) }}</span></td>
    <td>{{ $article->category }}<span class="record-sub">{{ $article->source_type }} · {{ $article->source_reference ?: 'Source interne' }}</span></td>
    <td>v{{ $article->version }}</td>
    <td><span class="status {{ $available ? 'active' : ($article->status === 'published' ? 'unavailable' : $article->status) }}">{{ $available ? 'Utilisé par l’IA' : ($article->status === 'published' ? 'Non disponible' : $article->status) }}</span></td>
    <td>{{ $article->approver?->name ?: 'Non approuvé' }}<span class="record-sub">{{ $article->reviewed_at?->format('d/m/Y H:i') ?: '—' }}</span></td>
    <td>{{ $article->expires_at?->format('d/m/Y H:i') ?: 'Sans expiration' }}</td>
    <td>@if(auth('admin')->user()->hasStaffPermission('support.ai.knowledge.manage'))<div class="page-actions"><a class="btn" href="{{ route('support.knowledge.edit',$article) }}">Modifier</a>@if($article->status !== 'published')<form method="POST" action="{{ route('support.knowledge.destroy',$article) }}" onsubmit="return confirm('Supprimer cet article non publié ?')">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Supprimer</button></form>@endif</div>@endif</td>
</tr>
@empty<tr><td colspan="7"><div class="empty"><i data-lucide="book-open"></i><div>Aucun article enregistré.</div></div></td></tr>@endforelse
</tbody></table></div><div class="pagination-row"><span>{{ $articles->total() }} article(s)</span><div>{{ $articles->links() }}</div></div></section>
@endsection
