@php
    $map = [
        'pending' => ['En cours', 'warning'],
        'confirmed' => ['Confirmée', 'info'],
        'paid' => ['En préparation', 'warning'],
        'processing' => ['En préparation', 'warning'],
        'shipped' => ['En livraison', 'info'],
        'delivered' => ['Livrée', 'success'],
        'completed' => ['Livrée', 'success'],
        'cancelled' => ['Annulée', 'danger'],
        'dispute' => ['Litige', 'dark-danger'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst($status), 'neutral'];
@endphp
<span class="cs-badge {{ $class }}">{{ $label }}</span>
