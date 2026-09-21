@extends('layouts.staff')
@section('title', $title . ' | Support OVANIE')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        <p class="page-subtitle">Consultation en temps réel des données centrales de la marketplace. Aucun enregistrement n’est dupliqué dans l’espace Support.</p>
    </div>
    <div class="page-actions">
        @if(auth()->user()->hasStaffPermission('tickets.write'))<a class="btn btn-primary" href="{{ route('support.tickets.create') }}"><i data-lucide="circle-plus"></i>Créer un ticket</a>@endif
    </div>
</div>
<form class="filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher une référence, un nom, un e-mail…">
    <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
    @if(request('q'))<a class="btn" href="{{ url()->current() }}">Réinitialiser</a>@endif
</form>
<section class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Dossier</th><th>Contact / référence</th><th>Détail</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><span class="record-title">{{ $record['primary'] ?: 'Sans libellé' }}</span><span class="record-sub">#{{ $record['id'] ?? '—' }}</span></td>
                    <td>{{ $record['secondary'] ?: 'Non renseigné' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string)($record['detail'] ?? ''), 90) }}</td>
                    <td><span class="status {{ strtolower((string)$record['status']) }}">{{ str_replace('_',' ',(string)$record['status']) }}</span></td>
                    <td>{{ optional($record['created_at'])->format('d/m/Y H:i') }}</td>
                    <td>@if(auth()->user()->hasStaffPermission('tickets.write'))<a class="btn" href="{{ route('support.tickets.create', $record['ticket_query'] ?? []) }}"><i data-lucide="ticket-plus"></i>Ouvrir un ticket</a>@else<span class="record-sub">Lecture seule</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i data-lucide="database"></i><div>Aucune donnée réelle disponible pour ce module.</div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
        <div class="pagination-row">
            <span>{{ $records->firstItem() }}–{{ $records->lastItem() }} sur {{ $records->total() }}</span>
            <div class="pagination-actions">
                @if($records->onFirstPage())<span class="btn" style="opacity:.45">Précédent</span>@else<a class="btn" href="{{ $records->previousPageUrl() }}">Précédent</a>@endif
                @if($records->hasMorePages())<a class="btn" href="{{ $records->nextPageUrl() }}">Suivant</a>@else<span class="btn" style="opacity:.45">Suivant</span>@endif
            </div>
        </div>
    @endif
</section>
@endsection
