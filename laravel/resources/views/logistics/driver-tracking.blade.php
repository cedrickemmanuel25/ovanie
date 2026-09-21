@extends('layouts.logistics')

@section('title', 'Tracking chauffeur | OVANIE')

@section('content')
<div style="max-width:760px;display:grid;gap:16px;">
    <h1 style="margin:0;font-size:24px;">Tracking chauffeur</h1>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
        <p>Expedition {{ $shipment->tracking_number ?: '#'.$shipment->id }}</p>
        <p id="gps-status" style="font-weight:800;color:#b45309;">En attente de permission GPS</p>
        <p id="gps-last" style="color:#64748b;"></p>
    </div>
</div>

<script>
const statusEl = document.getElementById('gps-status');
const lastEl = document.getElementById('gps-last');
let lastSentAt = 0;

function sendPosition(position) {
    const now = Date.now();
    if (now - lastSentAt < 10000) return;
    lastSentAt = now;

    fetch('{{ url('/api/driver/location/update') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            driver_id: '{{ $assignment?->driver_id }}',
            shipment_id: '{{ $shipment->id }}',
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            speed: position.coords.speed,
            heading: position.coords.heading,
            recorded_at: new Date().toISOString()
        })
    }).then(async response => {
        if (!response.ok) throw new Error((await response.json()).message || 'Erreur tracking');
        statusEl.textContent = 'Position envoyee';
        statusEl.style.color = '#166534';
        lastEl.textContent = 'Derniere mise a jour : ' + new Date().toLocaleTimeString();
    }).catch(error => {
        statusEl.textContent = error.message;
        statusEl.style.color = '#b91c1c';
    });
}

if (!navigator.geolocation) {
    statusEl.textContent = 'GPS indisponible sur cet appareil';
} else {
    navigator.geolocation.watchPosition(sendPosition, function () {
        statusEl.textContent = 'Permission GPS refusee ou position indisponible';
        statusEl.style.color = '#b91c1c';
    }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });
}
</script>
@endsection
