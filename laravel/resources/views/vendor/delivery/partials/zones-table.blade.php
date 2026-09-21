<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:auto;">
    <table style="width:100%;border-collapse:collapse;min-width:760px;">
        <thead>
            <tr style="background:#f8fafc;text-align:left;">
                <th style="padding:12px;">Commune</th>
                <th style="padding:12px;">Quartier</th>
                <th style="padding:12px;">Couverture</th>
                <th style="padding:12px;">Véhicule</th>
                <th style="padding:12px;">Prix</th>
                <th style="padding:12px;">Delai</th>
                <th style="padding:12px;">Capacite</th>
                <th style="padding:12px;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($zones as $zone)
                <tr style="border-top:1px solid #e5e7eb;">
                    <td style="padding:12px;">{{ $zone->commune }}</td>
                    <td style="padding:12px;">{{ $zone->district ?: 'Tous quartiers' }}</td>
                    <td style="padding:12px;">{{ match($zone->coverage_type ?? 'commune') {
                        'district' => 'Quartier exact',
                        'city' => 'Zone large explicite',
                        'custom_zone' => 'Zone personnalisee',
                        default => 'Commune exacte',
                    } }}</td>
                    <td style="padding:12px;">{{ match($zone->vehicle_code) {
                        'moto' => 'Moto',
                        'tricycle' => 'Tricycle',
                        'pickup' => 'Pickup',
                        'camion_3t' => 'Camion 3T',
                        'camion_10t' => 'Camion 10T',
                        default => 'Tarif général',
                    } }}</td>
                    <td style="padding:12px;">{{ number_format($zone->delivery_price, 0, ',', ' ') }} FCFA</td>
                    <td style="padding:12px;">{{ $zone->estimated_delay }}</td>
                    <td style="padding:12px;">{{ $zone->max_weight_kg ?: '-' }} kg / {{ $zone->max_volume_m3 ?: '-' }} m3</td>
                    <td style="padding:12px;">{{ $zone->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:16px;color:#64748b;">Aucune zone configuree.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
