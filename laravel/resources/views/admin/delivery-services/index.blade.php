@extends('admin.layouts.app')

@section('title', 'Services de livraison | Admin OVANIE')
@section('page-title', 'Services de livraison')

@section('content')
<style>
    .admin-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
    .admin-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px}
    .btn{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:11px 16px;font-weight:800;text-decoration:none;border:none;cursor:pointer}
    .btn-primary{background:#ff5a1f;color:#fff}.btn-light{background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0}.btn-danger{background:#fee2e2;color:#991b1b}
    table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px}th{color:#64748b;font-weight:900;text-transform:uppercase;font-size:12px}.badge{padding:5px 9px;border-radius:999px;font-weight:900;font-size:12px}.green{background:#dcfce7;color:#166534}.blue{background:#dbeafe;color:#1d4ed8}.orange{background:#ffedd5;color:#9a3412}.muted{color:#64748b}
</style>

@if(session('success'))<div class="admin-card" style="margin-bottom:14px;color:#166534;">{{ session('success') }}</div>@endif

<div class="admin-card">
    <div class="admin-actions">
        <div>
            <h2 style="margin:0;color:#0f172a;">Gestion des services de livraison</h2>
            <p class="muted" style="margin:6px 0 0;">Les options checkout viennent de cette base de données, pas du code.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.delivery-services.create') }}">Ajouter un service</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Service</th><th>Type</th><th>Transporteur</th><th>Délai</th><th>Tarifs</th><th>Statut</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($services as $service)
                <tr>
                    <td><strong>{{ $service->name }}</strong><br><span class="muted">{{ $service->code }}</span></td>
                    <td><span class="badge {{ $service->provider_type === 'ovanie' ? 'orange' : ($service->provider_type === 'partner' ? 'blue' : 'green') }}">{{ strtoupper($service->provider_type) }}</span></td>
                    <td>{{ $service->carrier?->name ?? '—' }}</td>
                    <td>{{ $service->estimated_hours }}h</td>
                    <td>{{ $service->rates->count() }} règle(s)</td>
                    <td>{{ $service->is_active ? 'Actif' : 'Inactif' }}</td>
                    <td style="display:flex;gap:8px;">
                        <a class="btn btn-light" href="{{ route('admin.delivery-services.edit', $service) }}">Modifier</a>
                        <form method="POST" action="{{ route('admin.delivery-services.destroy', $service) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger" type="submit">Désactiver</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun service de livraison.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $services->links() }}</div>
</div>
@endsection
