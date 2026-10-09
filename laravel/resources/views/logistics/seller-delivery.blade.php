@extends('layouts.logistics-operations')
@section('title','Boutiques en livraison propre')
@section('page-class','ops-directory')
@include('logistics.directory.assets')

@section('content')
<x-operations.directory-header
    title="Boutiques en livraison propre"
    section="Boutiques en livraison propre"
    subtitle="Boutiques qui livrent elles-mêmes leurs commandes. Celles qui n’ont pas terminé le réglage de leurs livraisons sont à relancer."
/>

<div class="directory-kpis" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <x-operations.kpi icon="alert" label="Livraison à configurer" :value="$summary['to_configure']" tone="red"/>
    <x-operations.kpi icon="check-circle" label="Livraison configurée" :value="$summary['ready']" tone="green"/>
    <x-operations.kpi icon="warehouse" label="Boutiques en livraison propre" :value="$summary['total']" tone="blue"/>
</div>

<div class="directory-status-tabs" role="tablist" aria-label="Filtrer les boutiques">
    <a class="directory-status-tab {{ $filter === 'a-configurer' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $filter === 'a-configurer' ? 'true' : 'false' }}"
       href="{{ route('logistics.seller-delivery', array_filter(['q' => $search])) }}">
        <span>À configurer</span><span class="directory-status-tab-count">{{ $summary['to_configure'] }}</span>
    </a>
    <a class="directory-status-tab {{ $filter === 'toutes' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $filter === 'toutes' ? 'true' : 'false' }}"
       href="{{ route('logistics.seller-delivery', array_filter(['etat' => 'toutes', 'q' => $search])) }}">
        <span>Toutes les boutiques</span><span class="directory-status-tab-count">{{ $summary['total'] }}</span>
    </a>
</div>

<form class="directory-filters" method="get" action="{{ route('logistics.seller-delivery') }}">
    @if($filter === 'toutes')<input type="hidden" name="etat" value="toutes">@endif
    <div class="directory-search"><x-operations.icon name="search"/><input name="q" value="{{ $search }}" placeholder="Rechercher une boutique, un vendeur, un téléphone, une commune…" aria-label="Rechercher une boutique"></div>
    <button class="ops-button ops-button-navy" type="submit">Rechercher</button>
</form>

<x-operations.panel :title="($filter === 'toutes' ? 'Boutiques en livraison propre' : 'Boutiques à relancer').' ('.$shops->total().')'" icon="warehouse" class="directory-table-panel">
    <div class="directory-table-wrap">
        <table class="directory-table">
            <thead><tr>
                <th>Boutique</th>
                <th>Vendeur</th>
                <th>Localisation</th>
                <th>Zones actives</th>
                <th>Ce qu’il manque</th>
                <th>Contact</th>
            </tr></thead>
            <tbody>
            @forelse($shops as $shop)
                @php
                    $gaps = $shop->delivery_gaps;
                    $phone = preg_replace('/[^0-9+]/', '', (string) ($shop->user?->phone ?? ''));
                    $whatsapp = ltrim($phone, '+');
                    if ($whatsapp !== '' && strlen($whatsapp) === 10 && str_starts_with($whatsapp, '0')) { $whatsapp = '225'.$whatsapp; }
                    $activeZones = $shop->sellerDeliveryZones->where('is_active', true)->count();
                    // Numéro lisible : « 07 01 02 03 04 » pour un numéro ivoirien à 10 chiffres.
                    $digits = preg_replace('/\D/', '', $phone);
                    if (strlen($digits) === 13 && str_starts_with($digits, '225')) { $digits = substr($digits, 3); }
                    $phoneLabel = strlen($digits) === 10 ? trim(chunk_split($digits, 2, ' ')) : (string) ($shop->user?->phone ?? '');
                @endphp
                <tr>
                    <td>
                        <strong>{{ $shop->name ?: 'Boutique sans nom' }}</strong>
                        <br><small>Créée le {{ optional($shop->created_at)->format('d/m/Y') ?: '—' }} · {{ $shop->status === 'approved' ? 'Approuvée' : ($shop->status === 'rejected' ? 'Rejetée' : 'En attente') }}</small>
                    </td>
                    <td>{{ $shop->user?->name ?: '—' }}</td>
                    <td>{{ collect([$shop->commune, $shop->city])->filter()->implode(', ') ?: '—' }}</td>
                    <td>{{ $activeZones }}</td>
                    <td>
                        @if($gaps)
                            <ul style="margin:0;padding-left:16px">
                                @foreach($gaps as $gap)<li>{{ $gap }}</li>@endforeach
                            </ul>
                        @else
                            <span class="is-green">Livraison configurée</span>
                        @endif
                    </td>
                    <td>
                        @if($phone !== '')
                            {{-- Le numéro est affiché en clair : il peut être lu ou recopié sans cliquer. --}}
                            <strong style="display:block;font-size:14px;white-space:nowrap;margin-bottom:8px">{{ $phoneLabel }}</strong>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <a class="ops-button ops-button-small" href="tel:{{ $phone }}"><x-operations.icon name="phone"/>Appeler</a>
                                <a class="ops-button ops-button-small" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
                            </div>
                            @if($shop->user?->email)<small style="margin-top:6px">{{ $shop->user->email }}</small>@endif
                        @elseif($shop->user?->email)
                            <a class="directory-link" href="mailto:{{ $shop->user->email }}">{{ $shop->user->email }}</a>
                        @else
                            Aucun contact renseigné
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="directory-empty">
                    {{ $search !== '' ? 'Aucune boutique ne correspond à cette recherche.' : ($filter === 'toutes' ? 'Aucune boutique n’utilise sa propre livraison pour le moment.' : 'Toutes les boutiques en livraison propre ont configuré leurs livraisons.') }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-operations.directory-pagination :paginator="$shops"/>
</x-operations.panel>
@endsection
