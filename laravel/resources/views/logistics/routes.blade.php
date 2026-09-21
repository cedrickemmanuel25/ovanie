@extends('layouts.logistics')

@section('title', 'Tournées')
@section('crumb', 'Tournées')

@section('content')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900">Planification des tournées</h1>
        <p class="mt-1 text-sm text-slate-500">Construisez une tournée à partir d’expéditions géolocalisées. Le moteur conserve l’ordre enlèvement → livraison pour chaque mission.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="xl:col-span-2 card p-6">
            <div class="border-b border-slate-100 pb-4 mb-5">
                <h2 class="text-lg font-extrabold text-slate-900">Nouvelle tournée</h2>
                <p class="text-sm text-slate-500 mt-1">Sélectionnez un livreur puis les expéditions compatibles.</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-extrabold text-slate-600 mb-2">Livreur</label>
                    <select id="routeDriverId" class="form-select w-full">
                        <option value="">Sélectionner un livreur</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }} · {{ strtoupper(str_replace('_', ' ', $driver->vehicle ?: 'véhicule non défini')) }} · {{ $driver->zone ?: 'zone non définie' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <h3 class="font-extrabold text-slate-900">Expéditions disponibles</h3>
                            <p class="text-xs text-slate-500 mt-1">Seules les missions OVANIE avec coordonnées complètes sont affichées.</p>
                        </div>
                        <span class="badge-gray">{{ $shipments->count() }} disponible(s)</span>
                    </div>

                    <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="data-table min-w-[800px]">
                            <thead>
                                <tr>
                                    <th class="w-12"></th>
                                    <th>Expédition</th>
                                    <th>Enlèvement</th>
                                    <th>Destination</th>
                                    <th>Véhicule requis</th>
                                    <th>Poids</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shipments as $shipment)
                                    <tr>
                                        <td><input type="checkbox" class="route-shipment rounded border-slate-300" value="{{ $shipment->id }}"></td>
                                        <td class="font-bold text-slate-800">TRK {{ $shipment->tracking_number ?: '#'.$shipment->id }}</td>
                                        <td>
                                            <div class="text-sm font-bold text-slate-800">{{ $shipment->shop?->name ?: 'Boutique' }}</div>
                                            <div class="text-xs text-slate-500 mt-1">{{ \Illuminate\Support\Str::limit($shipment->pickup_address, 55) }}</div>
                                        </td>
                                        <td>
                                            <div class="text-sm font-bold text-slate-800">{{ $shipment->order?->client?->name ?: 'Client' }}</div>
                                            <div class="text-xs text-slate-500 mt-1">{{ \Illuminate\Support\Str::limit($shipment->delivery_address, 55) }}</div>
                                        </td>
                                        <td class="text-sm font-bold text-slate-700">{{ $shipment->vehicle_label ?: $shipment->vehicle_code ?: 'À déterminer' }}</td>
                                        <td class="text-sm text-slate-600">{{ number_format((float)($shipment->total_weight_kg ?? 0), 1, ',', ' ') }} kg</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Aucune expédition géolocalisée disponible.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-slate-500">Le serveur contrôle à nouveau la compatibilité véhicule avant de créer la tournée.</p>
                    <button id="optimizeRoute" class="btn-primary">Optimiser la tournée</button>
                </div>

                <div id="routeResult" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-5"></div>
            </div>
        </section>

        <aside class="card p-6">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-extrabold text-slate-900">Dernières tournées</h2>
                <p class="text-sm text-slate-500 mt-1">Historique des plans générés.</p>
            </div>
            <div class="space-y-3">
                @forelse($routes as $route)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <strong class="text-sm text-slate-900">Tournée #{{ $route->id }}</strong>
                            <span class="{{ $route->status === 'optimized' ? 'badge-green' : 'badge-gray' }}">{{ $route->status }}</span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 space-y-1">
                            <div>Livreur : {{ $route->driver?->name ?: '—' }}</div>
                            <div>{{ $route->stops->count() }} arrêt(s)</div>
                            <div>{{ $route->total_distance_km ? number_format($route->total_distance_km, 1, ',', ' ').' km' : 'Distance non calculée' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Aucune tournée créée.</div>
                @endforelse
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('optimizeRoute');
    const result = document.getElementById('routeResult');

    button?.addEventListener('click', async () => {
        const driverId = Number(document.getElementById('routeDriverId')?.value || 0);
        const shipmentIds = [...document.querySelectorAll('.route-shipment:checked')].map(input => Number(input.value));

        result.classList.remove('hidden');
        result.innerHTML = '<div class="text-sm font-bold text-slate-600">Optimisation en cours…</div>';

        if (!driverId || shipmentIds.length === 0) {
            result.innerHTML = '<div class="text-sm font-bold text-rose-600">Sélectionnez un livreur et au moins une expédition.</div>';
            return;
        }

        try {
            const response = await fetch(@json(route('logistics.routes.optimize')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ driver_id: driverId, shipment_ids: shipmentIds })
            });

            const payload = await response.json();
            if (!response.ok) {
                const message = payload.message || Object.values(payload.errors || {}).flat().join(' ');
                throw new Error(message || 'Optimisation impossible.');
            }

            const route = payload.route;
            const stops = route?.stops || [];
            result.innerHTML = `
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-lg font-extrabold text-slate-900">Tournée #${route.id}</div>
                        <div class="text-sm text-slate-500 mt-1">${stops.length} arrêt(s) · ${route.total_distance_km ?? '—'} km · ${route.total_duration_minutes ?? '—'} min</div>
                    </div>
                    <span class="badge-green">${route.status}</span>
                </div>
                <div class="mt-4 space-y-2">
                    ${stops.map(stop => `<div class="rounded-xl bg-white border border-slate-200 px-4 py-3 text-sm"><strong>${stop.stop_order}. ${stop.type === 'pickup' ? 'Enlèvement' : 'Livraison'}</strong><div class="text-slate-500 mt-1">${escapeHtml(stop.address || 'Adresse non renseignée')}</div></div>`).join('')}
                </div>`;
        } catch (error) {
            result.innerHTML = '<div class="text-sm font-bold text-rose-600">' + escapeHtml(error.message || 'Erreur inconnue') + '</div>';
        }
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    }
})();
</script>
@endpush
