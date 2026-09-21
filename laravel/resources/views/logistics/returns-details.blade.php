@extends('layouts.logistics-operations')
@section('title','Détail retour')
@section('page-class','ops-directory directory-detail directory-return-detail')
@include('logistics.directory.assets')
@section('content')
@php
use App\ViewModels\LogisticsDirectoryData as D;
use App\Models\ReturnModel;

[$status,$tone]=D::returnWorkflowStatus($return);
$meta=is_array($return->meta)?$return->meta:[];
$collection=(array)($meta['collection']??[]);
$decision=(array)($meta['vendor_decision']??[]);
$decisionType=(string)($decision['type']??'');
if($decisionType===''){
    $decisionType=match(true){
        $return->status===ReturnModel::STATUS_REJECTED=>'reject',
        $return->status===ReturnModel::STATUS_REFUNDED=>'refund',
        $return->status===ReturnModel::STATUS_ACCEPTED && $return->return_type==='refund'=>'refund',
        $return->status===ReturnModel::STATUS_ACCEPTED && $return->return_type==='return'=>'accept',
        default=>'',
    };
}

$decisionLabel=match($decisionType){'reject'=>'Rejeté','refund'=>'Remboursé','accept'=>'Retour confirmé',default=>'En attente vendeur'};
$actionClass=match($decisionType){'reject'=>'return-action-reject','refund'=>'return-action-refund','accept'=>'return-action-accept',default=>''};
$published=(bool)($decision['published_at']??false);
$decisionProofs=(array)($decision['proofs']??[]);
$isRejected=$decisionType==='reject' || $return->status===ReturnModel::STATUS_REJECTED;
$isAcceptedReturn=!$isRejected && $return->return_type==='return' && ($decisionType==='accept' || $return->status===ReturnModel::STATUS_ACCEPTED);
$refundRelevant=!$isRejected && (
    $decisionType==='refund'
    || $return->status===ReturnModel::STATUS_REFUNDED
    || in_array($return->logistics_status,[ReturnModel::LOGISTICS_REFUND_REVIEW,ReturnModel::LOGISTICS_REFUND_PENDING,ReturnModel::LOGISTICS_REFUNDED],true)
    || !empty($return->refund_prepared_at)
    || !empty($return->refunded_at)
);
$collectionRelevant=$isAcceptedReturn;
$hasCollectionDetails=!empty($collection['driver_id']) && !empty($collection['address']) && !empty($collection['date']) && !empty($collection['slot']);
$client=$return->client??$return->order?->client;
$product=$return->product??$return->orderItem?->product;
$driver=$returnDriver??null;
$proof=$return->photo_proof?route('logistics.private-documents.return',$return):null;
$refundValue=$refundRelevant ? ($refundAmount ?? ((float)$return->refund_amount > 0 ? (float)$return->refund_amount : null)) : null;
$amount=$refundValue!==null && $refundValue>0 ? number_format($refundValue,0,',',' ').' FCFA' : 'À calculer';
$address=$collection['address']??$return->order?->delivery_address??'Adresse non renseignée';
$collectionStatus=D::returnLogisticsStatusLabel($return->logistics_status);
$refundMethod=(string)(data_get($meta,'refund_method')??data_get($decision,'refund_details.method')??'');
$refundReference=(string)(data_get($meta,'refund_reference')??data_get($decision,'refund_details.reference')??'');
$refundState=match(true){
    $return->status===ReturnModel::STATUS_REFUNDED=>'Effectué',
    $return->logistics_status===ReturnModel::LOGISTICS_REFUND_PENDING=>'En attente d’exécution',
    $return->logistics_status===ReturnModel::LOGISTICS_REFUND_REVIEW=>'En analyse',
    !empty($return->refund_prepared_at)=>'Préparé',
    default=>'En traitement',
};
$nextLabel=match(true){
    $return->status===ReturnModel::STATUS_REFUNDED=>'Remboursement effectué',
    $return->logistics_status===ReturnModel::LOGISTICS_REFUND_PENDING=>'Confirmer le remboursement',
    $return->logistics_status===ReturnModel::LOGISTICS_RECEIVED=>'Préparer le remboursement',
    $return->logistics_status===ReturnModel::LOGISTICS_REFUND_REVIEW=>'Préparer le remboursement',
    $return->logistics_status===ReturnModel::LOGISTICS_IN_TRANSIT=>'Confirmer la réception',
    $return->logistics_status===ReturnModel::LOGISTICS_PICKUP_PLANNED=>'Confirmer la collecte',
    $return->logistics_status===ReturnModel::LOGISTICS_PENDING_PICKUP=>'Planifier la collecte',
    default=>'Mettre à jour le retour'
};
$showWorkflowAction=!$isRejected && $decisionType!=='' && !in_array($return->status,[ReturnModel::STATUS_REFUNDED,ReturnModel::STATUS_REJECTED,ReturnModel::STATUS_CLOSED],true);

$kpis=[
    ['list','Retour',D::ref($return,'RET'),'blue'],
    ['truck','Commande',$return->order?->order_number??$return->order_reference??'—','orange'],
    ['box','Statut',$status,$tone],
];
if($collectionRelevant){$kpis[]=['truck','Collecte',$collectionStatus,'green'];}
if($refundRelevant){$kpis[]=['wallet','Montant remboursement',$amount,'green'];}
$kpis[]=['clock','Dernière mise à jour',$return->updated_at?->format('d/m/Y H:i')??'—','blue'];
$kpiClass=count($kpis)===4?'four':(count($kpis)===6?'six':'');
@endphp

<x-operations.directory-header title="Détail retour" section="Retours" :url="route('logistics.returns')" subtitle="Suivi du dossier, preuves et traitement logistique">
    <a class="ops-button" href="{{ route('logistics.returns') }}">← Retour à la liste</a>
    @if($decisionType !== '' && !$published)
        <form method="post" action="{{ route('logistics.returns.publish-decision',$return) }}">
            @csrf @method('PATCH')
            <button class="ops-button return-action-button {{ $actionClass }}" type="submit">{{ $decisionLabel }}</button>
        </form>
    @elseif($decisionType !== '' && $published)
        <span class="ops-button return-action-button {{ $actionClass }} is-complete">{{ $decisionLabel }} ✓</span>
    @endif
    @if($collectionRelevant && !$hasCollectionDetails && !in_array($return->status,[ReturnModel::STATUS_REFUNDED,ReturnModel::STATUS_REJECTED,ReturnModel::STATUS_CLOSED],true))
        <button class="ops-button" data-directory-open="return-collect"><x-operations.icon name="calendar"/>Planifier la collecte</button>
    @endif
</x-operations.directory-header>

@if($errors->has('collection'))
    <div class="directory-soft" style="margin-bottom:12px"><strong>Collecte à renseigner :</strong> {{ $errors->first('collection') }}</div>
@endif

<div class="directory-kpis {{ $kpiClass }}">
@foreach($kpis as [$icon,$label,$value,$color])
    <x-operations.kpi :icon="$icon" :label="$label" :value="$value" :tone="$color"/>
@endforeach
</div>

<div class="directory-detail-grid">
    <div class="directory-stack">
        <x-operations.panel title="Informations du retour" icon="help">
            <div class="directory-two">
                <dl class="directory-facts">
                    <dt>Client</dt><dd>{{ $client?->name??$return->order?->customer_name??'—' }}</dd>
                    <dt>Téléphone</dt><dd>{{ $client?->phone??'—' }}</dd>
                    <dt>E-mail</dt><dd>{{ $client?->email??'—' }}</dd>
                    <dt>Motif du retour</dt><dd>{{ $return->reason??'—' }}</dd>
                    <dt>Date de demande</dt><dd>{{ $return->request_date?->format('d/m/Y')??$return->created_at?->format('d/m/Y')??'—' }}</dd>
                </dl>
                <dl class="directory-facts">
                    <dt>N° commande</dt><dd>{{ $return->order?->order_number??$return->order_reference??'—' }}</dd>
                    <dt>Type</dt><dd>{{ ['return'=>'Retour produit','refund'=>'Remboursement','claim'=>'Réclamation'][$return->return_type]??'Autre' }}</dd>
                    <dt>Quantité</dt><dd>{{ $return->quantity ?: 1 }}</dd>
                    <dt>Canal</dt><dd>{{ $meta['channel']??'Espace client' }}</dd>
                    <dt>Adresse de livraison</dt><dd>{{ $return->order?->delivery_address??'—' }}</dd>
                </dl>
            </div>

            @if($isRejected)
                <div class="directory-soft" style="margin-top:14px">
                    <strong>Motif du rejet vendeur</strong>
                    <p style="margin-top:7px">{{ $decision['response']??$return->vendor_response??'Aucun motif de rejet renseigné.' }}</p>
                    <small>
                        {{ !empty($decision['decided_at']) ? 'Rejet enregistré le '.\Carbon\Carbon::parse($decision['decided_at'])->format('d/m/Y à H:i') : (($return->rejected_at?->format('d/m/Y à H:i')) ? 'Rejet enregistré le '.$return->rejected_at->format('d/m/Y à H:i') : '') }}
                        @if($published) — transmis au client le {{ \Carbon\Carbon::parse($decision['published_at'])->format('d/m/Y à H:i') }} @else — en attente de transmission par la Logistique @endif
                    </small>
                </div>

                @if(count($decisionProofs))
                    <div class="directory-files" style="margin-top:12px">
                        @foreach($decisionProofs as $index=>$decisionProof)
                            @php($proofName=is_array($decisionProof)?($decisionProof['name']??basename($decisionProof['path']??'')):basename($decisionProof))
                            <div><x-operations.icon name="list"/><span>{{ $proofName ?: 'Preuve du rejet '.($index+1) }}</span><a href="{{ route('logistics.private-documents.return-decision',[$return,$index]) }}" target="_blank" rel="noopener" title="Voir la preuve"><x-operations.icon name="download"/></a></div>
                        @endforeach
                    </div>
                @endif
            @elseif(!empty($decision['response']))
                <div class="directory-soft" style="margin-top:14px"><strong>Précision du vendeur</strong><p style="margin-top:7px">{{ $decision['response'] }}</p></div>
            @endif
        </x-operations.panel>

        <x-operations.panel title="Produit concerné" icon="box">
            <div class="directory-map-layout">
                <div class="directory-product"><x-operations.product-picture :product="$product" :name="$return->product_name"/>
                    <dl class="directory-facts">
                        <dt>Produit</dt><dd>{{ $product?->name??$return->product_name??'Produit' }}</dd>
                        <dt>Marque</dt><dd>{{ $product?->brand??'—' }}</dd>
                        <dt>Quantité</dt><dd>{{ $return->quantity ?: 1 }} unité(s)</dd>
                        <dt>Catégorie</dt><dd>{{ $product?->category?->name??'—' }}</dd>
                    </dl>
                </div>
                <dl class="directory-facts"><dt>État déclaré</dt><dd><x-operations.tag tone="red">{{ $return->reason??'Non renseigné' }}</x-operations.tag></dd><dt>Référence article</dt><dd>{{ $product?->sku??$product?->slug??'—' }}</dd></dl>
            </div>
        </x-operations.panel>

        <x-operations.panel title="Preuve fournie par le client" icon="list">
            <div class="directory-two">
                @if($proof)<img class="directory-preview" src="{{ $proof }}" alt="Preuve du retour">
                @else<div class="directory-placeholder"><x-operations.icon name="list"/>Aucune preuve jointe par le client</div>@endif
                <div class="directory-files">
                    @if($proof)<div><x-operations.icon name="list"/><span>{{ basename($return->photo_proof) }}<small>{{ $return->request_date?->format('d/m/Y') }}</small></span><a href="{{ $proof }}"><x-operations.icon name="download"/></a></div>
                    @else<p class="directory-empty">Aucun fichier disponible.</p>@endif
                </div>
            </div>
        </x-operations.panel>

        <x-operations.panel title="Historique du retour" icon="clock">
            @if($returnHistories->isNotEmpty())
                <div class="directory-timeline">
                    @foreach($returnHistories as $history)
                        <div><b style="background:var(--green)">✓</b><strong>{{ $history->label??'Mise à jour' }}</strong><small>{{ $history->created_at?->format('d/m/Y H:i') }}</small><small>{{ $history->message }}</small></div>
                    @endforeach
                </div>
            @else
                <p class="directory-empty">Aucun événement complémentaire enregistré.</p>
            @endif
        </x-operations.panel>
    </div>

    <aside class="directory-stack">
        @if($collectionRelevant)
            <x-operations.panel title="Collecte / livraison retour" icon="truck">
                <x-slot:actions><x-operations.tag>{{ $collectionStatus }}</x-operations.tag></x-slot:actions>
                <dl class="directory-facts">
                    <dt>Adresse de collecte</dt><dd>{{ $address }}</dd>
                    <dt>Date</dt><dd>{{ !empty($collection['date']) ? \Carbon\Carbon::parse($collection['date'])->format('d/m/Y') : 'À planifier' }}</dd>
                    <dt>Créneau</dt><dd>{{ $collection['slot']??'À planifier' }}</dd>
                    <dt>Destination</dt><dd>{{ $collection['destination']??'À renseigner' }}</dd>
                    <dt>Livreur</dt><dd>{{ $driver?->name??'Non affecté' }}</dd>
                    <dt>Téléphone livreur</dt><dd>{{ $driver?->phone??'—' }}</dd>
                    <dt>Véhicule</dt><dd>{{ $driver?->vehicle??'—' }}</dd>
                    <dt>Statut</dt><dd>{{ $collectionStatus }}</dd>
                </dl>
            </x-operations.panel>
        @endif

        @if($refundRelevant)
            <x-operations.panel title="Remboursement" icon="wallet" id="refund">
                <x-slot:actions><x-operations.tag :tone="$return->status===ReturnModel::STATUS_REFUNDED?'green':'orange'">{{ $refundState }}</x-operations.tag></x-slot:actions>
                <dl class="directory-facts">
                    <dt>Montant</dt><dd>{{ $amount }}</dd>
                    @if($return->status===ReturnModel::STATUS_REFUNDED)<dt>Montant remboursé</dt><dd>{{ $amount }}</dd>@endif
                    <dt>Mode</dt><dd>{{ D::refundMethodLabel($refundMethod) }}</dd>
                    <dt>Référence</dt><dd>{{ $refundReference!==''?$refundReference:'—' }}</dd>
                    <dt>Date</dt><dd>{{ $return->refunded_at?->format('d/m/Y H:i')??'En attente' }}</dd>
                </dl>
            </x-operations.panel>
        @endif

        <x-operations.panel title="Actions rapides" icon="bolt">
            <div class="directory-form-grid">
                @if($decisionType !== '' && !$published)
                    <form method="post" action="{{ route('logistics.returns.publish-decision',$return) }}">@csrf @method('PATCH')<button class="ops-button return-action-button {{ $actionClass }}" type="submit">{{ $decisionLabel }}</button></form>
                @elseif($decisionType !== '' && $published)
                    <span class="ops-button return-action-button {{ $actionClass }} is-complete">{{ $decisionLabel }} ✓</span>
                @endif
                @if($proof)<a class="ops-button" href="{{ $proof }}">Voir preuve client</a>@endif
                @if($client?->phone)<a class="ops-button" href="tel:{{ $client->phone }}">Contacter le client</a>@endif
                @if($return->order_item_id)<a class="ops-button" href="{{ route('logistics.shipments.details',$return->order_item_id) }}">Ouvrir la commande ↗</a>@endif
                @if($showWorkflowAction)
                    <button class="ops-button" data-directory-open="return-refund">{{ $nextLabel }}</button>
                @endif
            </div>
        </x-operations.panel>

        <x-operations.panel title="Notes internes" icon="list">
            <div class="directory-notes">
                @forelse($meta['notes']??[] as $note)<div><span class="directory-avatar">RL</span><span><strong>{{ $note['author']??'Logistique' }}</strong><p>{{ $note['text']??'' }}</p><small>{{ !empty($note['at'])?\Carbon\Carbon::parse($note['at'])->format('d/m/Y H:i'):'—' }}</small></span></div>
                @empty<p class="directory-empty">Aucune note interne.</p>@endforelse
            </div>
            <details class="directory-note-disclosure"><summary class="directory-link">+ Ajouter une note</summary><form class="directory-form" method="post" action="{{ route('logistics.returns.notes',$return) }}">@csrf<label>Ajouter une note<textarea name="note" required maxlength="1000"></textarea></label><button class="ops-button">Enregistrer</button></form></details>
        </x-operations.panel>
    </aside>
</div>

@if($collectionRelevant)
    @include('logistics.directory.return-collection')
@endif

@if($showWorkflowAction)
<dialog class="directory-modal" id="return-refund">
    <header><div><h2>{{ $nextLabel }}</h2><p>{{ D::ref($return,'RET') }} — {{ $product?->name??$return->product_name }}</p></div><button data-directory-close aria-label="Fermer">×</button></header>
    <form class="directory-form" method="post" action="{{ route('logistics.returns.approve',$return) }}">
        @csrf @method('PATCH')
        @if($return->logistics_status===ReturnModel::LOGISTICS_REFUND_PENDING)
            <p>Renseignez les informations du remboursement réellement exécuté.</p>
            <div class="directory-form-grid">
                <label>Référence du remboursement<input name="refund_reference" required maxlength="190"></label>
                <label>Mode de remboursement<select name="refund_method" required><option value="mobile_money">Mobile Money</option><option value="bank_transfer">Virement bancaire</option><option value="cash">Espèces</option><option value="paydunya_manual">PayDunya</option></select></label>
            </div>
        @else
            <p>{{ $nextLabel }} à partir des informations réellement enregistrées pour ce dossier.</p>
        @endif
        <footer><button type="button" class="ops-button" data-directory-close>Annuler</button><button class="ops-button ops-button-primary">Confirmer</button></footer>
    </form>
</dialog>
@endif
@endsection
