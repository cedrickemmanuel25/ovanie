@extends('admin.layouts.app')

@section('title', 'Vérifications paiements vendeurs')
@section('page-title', 'Vérifications paiements vendeurs')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payment-verifications.index') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <label for="status" class="fw-bold">Statut</label>
                <select name="status" id="status" class="form-select" style="max-width:220px" onchange="this.form.submit()">
                    <option value="pending" @selected($status === 'pending')>En attente</option>
                    <option value="approved" @selected($status === 'approved')>Approuvées</option>
                    <option value="rejected" @selected($status === 'rejected')>Rejetées</option>
                    <option value="" @selected($status === '')>Toutes</option>
                </select>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Commande</th>
                        <th>Boutique</th>
                        <th>Vendeur</th>
                        <th>Moyen</th>
                        <th>Montant déclaré</th>
                        <th>Référence</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($verifications as $verification)
                        <tr>
                            <td>{{ optional($verification->requested_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($verification->order)
                                    <a href="{{ route('admin.orders.show', $verification->order) }}">
                                        {{ $verification->order->order_number ?? '#'.$verification->order->id }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $verification->shop->name ?? 'N/A' }}</td>
                            <td>{{ $verification->vendor->name ?? $verification->requester->name ?? 'N/A' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $verification->payment_method)) }}</td>
                            <td>{{ number_format((float) $verification->amount_claimed, 0, ',', ' ') }} FCFA</td>
                            <td>{{ $verification->payment_reference ?: '—' }}</td>
                            <td>
                                @php
                                    $badge = match($verification->status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($verification->status) }}</span>
                            </td>
                            <td style="min-width:260px">
                                @if($verification->vendor_note)
                                    <div class="small text-muted mb-2">
                                        <strong>Note vendeur :</strong> {{ $verification->vendor_note }}
                                    </div>
                                @endif

                                @if($verification->status === 'pending')
                                    <form method="POST" action="{{ route('admin.payment-verifications.approve', $verification) }}" class="mb-2">
                                        @csrf
                                        <input type="text" name="admin_note" class="form-control form-control-sm mb-1" placeholder="Note admin optionnelle">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approuver cette vérification paiement ?')">
                                            Approuver
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.payment-verifications.reject', $verification) }}">
                                        @csrf
                                        <input type="text" name="admin_note" class="form-control form-control-sm mb-1" placeholder="Raison du rejet" required>
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Rejeter cette vérification paiement ?')">
                                            Rejeter
                                        </button>
                                    </form>
                                @else
                                    <div class="small text-muted">
                                        <strong>Note admin :</strong> {{ $verification->admin_note ?: '—' }}
                                    </div>
                                    <div class="small text-muted">
                                        Traité le {{ optional($verification->reviewed_at)->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Aucune demande de vérification paiement trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $verifications->links() }}
        </div>
    </div>
</div>
@endsection
