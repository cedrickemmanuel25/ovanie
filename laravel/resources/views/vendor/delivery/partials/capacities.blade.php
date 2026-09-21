<div style="display:grid;gap:8px;">
    @forelse($capacities ?? [] as $capacity)
        <div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;">
            {{ $capacity->vehicle_type }} - {{ $capacity->max_weight_kg }} kg - {{ $capacity->max_volume_m3 }} m3
        </div>
    @empty
        <p style="color:#64748b;">Aucune capacite specifique configuree.</p>
    @endforelse
</div>
