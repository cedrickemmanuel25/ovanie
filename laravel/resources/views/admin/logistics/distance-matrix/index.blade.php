@extends('admin.layouts.app')

@section('title', 'Matrice distances | Admin OVANIE')
@section('page-title', 'Matrice distances')

@section('content')
<div style="display:grid;gap:18px;">
    @if(session('success'))
        <div style="background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;padding:12px;border-radius:8px;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.logistics.distance-matrix.store') }}" style="display:grid;gap:12px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px;">
        @csrf
        <h2 style="margin:0;font-size:18px;">Ajouter une distance fiable</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
            <input name="origin_commune" placeholder="Commune depart" required>
            <input name="destination_commune" placeholder="Commune arrivee" required>
            <input type="number" step="0.01" min="0.01" name="distance_km" placeholder="Distance km" required>
            <input type="number" min="1" name="estimated_duration_minutes" placeholder="Duree minutes">
        </div>
        <label style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="is_active" value="1" checked> Active
        </label>
        <button style="justify-self:start;background:#0a1733;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:800;">Enregistrer</button>
    </form>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:860px;">
            <thead>
                <tr style="background:#f8fafc;text-align:left;">
                    <th style="padding:12px;">Depart</th>
                    <th style="padding:12px;">Arrivee</th>
                    <th style="padding:12px;">Distance</th>
                    <th style="padding:12px;">Duree</th>
                    <th style="padding:12px;">Active</th>
                    <th style="padding:12px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($distances as $distance)
                    <tr style="border-top:1px solid #e5e7eb;">
                        <form method="POST" action="{{ route('admin.logistics.distance-matrix.update', $distance) }}">
                            @csrf
                            @method('PUT')
                            <td style="padding:12px;"><input name="origin_commune" value="{{ $distance->origin_commune }}" required></td>
                            <td style="padding:12px;"><input name="destination_commune" value="{{ $distance->destination_commune }}" required></td>
                            <td style="padding:12px;"><input type="number" step="0.01" min="0.01" name="distance_km" value="{{ $distance->distance_km }}" required></td>
                            <td style="padding:12px;"><input type="number" min="1" name="estimated_duration_minutes" value="{{ $distance->estimated_duration_minutes }}"></td>
                            <td style="padding:12px;"><input type="checkbox" name="is_active" value="1" @checked($distance->is_active)></td>
                            <td style="padding:12px;"><button style="background:#ff5a1f;color:#fff;border:0;padding:8px 10px;border-radius:8px;font-weight:800;">Sauver</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:16px;color:#64748b;">Aucune distance configuree.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $distances->links() }}
</div>
@endsection
