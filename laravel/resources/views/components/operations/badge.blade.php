@props(['status' => 'pending', 'label' => null])
@php
    [$tone, $text, $icon] = match($status) {
        'in_progress' => ['green', 'En cours', 'check-circle'],
        'done', 'delivered' => ['green', 'Livrée', 'check-circle'],
        'finished' => ['gray', 'Terminée', 'check-circle'],
        'planned' => ['blue', 'Planifiée', null],
        // 'assigned' distingué de 'picked_up'/'in_transit' : un livreur affecté
        // n'a pas forcément encore accepté ni récupéré la commande. Les confondre
        // faisait afficher "En transit" dès l'affectation, avant même l'acceptation.
        'assigned' => ['orange', 'Affecté — en attente', 'clock'],
        // Livreur ayant accepté la mission et démarré ses collectes, mais pas
        // encore arrivé à la boutique (voir DeliveryAssignment::status='collecting').
        'en_route_pickup' => ['blue', 'En route vers la boutique', 'truck'],
        'in_transit', 'picked_up' => ['blue', 'En transit', 'check-circle'],
        'current' => ['blue', 'En cours', 'check-circle'],
        'late' => ['orange', 'En retard', 'clock'],
        'ready_for_pickup', 'to_assign' => ['orange', 'À affecter', null],
        'unassigned' => ['orange', 'À affecter', null],
        'incident', 'delivery_failed' => ['red', 'Incident ouvert', 'warning'],
        default => ['gray', 'En attente', 'check-circle'],
    };
@endphp
<span {{ $attributes->class(['ops-badge', 'is-'.$tone]) }}>@if($icon)<x-operations.icon :name="$icon"/>@endif{{ $label ?? $text }}</span>
