@extends('layouts.logistics-operations')
@section('title','Retours')
@section('page-class','ops-directory')
@include('logistics.directory.assets')
@section('content')
@php
use App\ViewModels\LogisticsDirectoryData as D;
$segments=collect([
    ['Demandes','pending','blue'],
    ['Enlèvement','pickup','orange'],
    ['Reçus','received','purple'],
    ['Remboursés','refunded','green'],
    ['Rejetés','rejected','red'],
])->map(fn($s)=>[$s[0],$allReturns->filter(fn($r)=>D::returnStatus($r)[2]===$s[1])->count(),$s[2]])->all();
@endphp

<x-operations.directory-header
    title="Retours"
    section="Retours"
    subtitle="Suivi réel des demandes, confirmation logistique, collecte, remboursement et transmission au client"
>
    <a class="ops-button" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}">
        <x-operations.icon name="download"/>Exporter
    </a>
</x-operations.directory-header>

<div class="directory-kpis">
@foreach($segments as [$label,$value,$tone])
    <x-operations.kpi :icon="['blue'=>'list','orange'=>'truck','purple'=>'box','green'=>'wallet','red'=>'close'][$tone]" :label="$label" :value="$value" :tone="$tone"/>
@endforeach
</div>

<form class="directory-filters">
    <div class="directory-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Rechercher un retour (N° commande, client, produit…)" aria-label="Rechercher un retour"></div>
    <label>Statut<select name="status" onchange="this.form.submit()"><option value="">Tous les statuts</option>
        @foreach(['pending'=>'Demande','pickup'=>'Enlèvement','received'=>'Reçu','refunded'=>'Remboursé','rejected'=>'Rejeté'] as $value=>$label)
            <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
        @endforeach
    </select></label>
    <label>Raison<select name="reason" onchange="this.form.submit()"><option value="">Toutes les raisons</option>
        @foreach($allReturns->pluck('reason')->filter()->unique() as $reason)
            <option value="{{ $reason }}" @selected(request('reason')===$reason)>{{ $reason }}</option>
        @endforeach
    </select></label>
    <label>Date<input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()"></label>
    <a href="{{ route('logistics.returns') }}"><x-operations.icon name="refresh"/>Réinitialiser les filtres</a>
</form>

<div class="directory-grid">
    <x-operations.panel :title="'Liste des retours ('.$returns->total().')'" icon="return" class="directory-table-panel">
        <div class="directory-table-wrap">
            <table class="directory-table">
                <thead><tr>
                    @foreach(['#','Date demande','N° commande','Client','Produit','Raison du retour','Statut','Actions'] as $h)<th>{{ $h }}</th>@endforeach
                </tr></thead>
                <tbody>
                @forelse($returns as $return)
                    @php
                        [$status,$tone]=D::returnWorkflowStatus($return);
                        $decision=(array)data_get($return->meta,'vendor_decision',[]);
                        $decisionType=(string)($decision['type']??'');
                        if($decisionType===''){
                            $decisionType=match(true){
                                $return->status==='rejected'=>'reject',
                                $return->status==='refunded'=>'refund',
                                $return->status==='accepted' && $return->return_type==='refund'=>'refund',
                                $return->status==='accepted' && $return->return_type==='return'=>'accept',
                                default=>'',
                            };
                        }
                        $published=(bool)($decision['published_at']??false);
                        $decisionLabel=match($decisionType){'reject'=>'Rejeté','refund'=>'Remboursé','accept'=>'Retour confirmé',default=>'En attente vendeur'};
                        $actionClass=match($decisionType){'reject'=>'return-action-reject','refund'=>'return-action-refund','accept'=>'return-action-accept',default=>''};
                    @endphp
                    <tr>
                        <td><a class="directory-ref" href="{{ route('logistics.returns.details',$return) }}">{{ D::ref($return,'RET') }}</a></td>
                        <td>{{ $return->request_date?->format('d/m/Y') ?? $return->created_at?->format('d/m/Y') }}</td>
                        <td>{{ $return->order?->order_number ?? $return->order_reference ?? '—' }}</td>
                        <td>{{ $return->client?->name ?? $return->order?->client?->name ?? $return->order?->customer_name ?? '—' }}</td>
                        <td><span class="directory-product"><x-operations.product-picture :product="$return->product ?? $return->orderItem?->product" :name="$return->product_name" :compact="true"/><span>{{ $return->orderItem?->product?->name ?? $return->product_name ?? 'Produit' }}<small>Qté {{ $return->quantity ?: 1 }}</small></span></span></td>
                        <td>{{ $return->reason ?: '—' }}</td>
                        <td><x-operations.tag :tone="$tone">{{ $status }}</x-operations.tag></td>
                        <td>
                            <div class="actions return-actions">
                                <a href="{{ route('logistics.returns.details',$return) }}" aria-label="Voir le retour" title="Voir le détail"><x-operations.icon name="eye"/></a>
                                @if($decisionType !== '' && !$published)
                                    <form method="post" action="{{ route('logistics.returns.publish-decision',$return) }}">
                                        @csrf @method('PATCH')
                                        <button class="ops-button return-action-button {{ $actionClass }}" type="submit">{{ $decisionLabel }}</button>
                                    </form>
                                @elseif($decisionType !== '' && $published)
                                    <span class="ops-button return-action-button {{ $actionClass }} is-complete" title="Déjà transmis au client">{{ $decisionLabel }} ✓</span>
                                @else
                                    <x-operations.tag tone="slate">En attente vendeur</x-operations.tag>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="directory-empty">Aucun retour réel correspondant aux filtres.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-operations.directory-pagination :paginator="$returns"/>
    </x-operations.panel>

    <aside class="directory-stack">
        <x-operations.panel title="Répartition par statut" icon="chart">
            @include('logistics.directory.donut',['segments'=>$segments,'unit'=>'retours'])
        </x-operations.panel>
        <x-operations.panel title="Principales raisons de retour" icon="chart">
            @php($reasonCounts=$allReturns->groupBy('reason')->map->count())
            @php($rows=$reasonCounts->sortDesc()->take(5)->map(fn($count,$reason)=>[$reason?:'Non renseigné',$count,'blue',max(1,$reasonCounts->max()?:1)])->values()->all())
            <x-operations.directory-bars :rows="$rows"/>
        </x-operations.panel>
        <x-operations.panel title="Derniers retours" icon="clock">
            @forelse($allReturns->take(3) as $r)
                <a class="directory-follow" href="{{ route('logistics.returns.details',$r) }}"><span class="directory-product"><x-operations.icon name="box"/><span><strong>{{ D::ref($r,'RET') }} — {{ $r->orderItem?->product?->name ?? $r->product_name }}</strong><small>{{ D::returnStatus($r)[0] }} — {{ $r->request_date?->format('d/m/Y') }}</small></span></span></a>
            @empty
                <p class="directory-empty">Aucun retour réel enregistré.</p>
            @endforelse
        </x-operations.panel>
    </aside>
</div>
@endsection
