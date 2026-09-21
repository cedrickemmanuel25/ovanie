{{-- resources/views/orders/index.blade.php --}}
@extends('layouts.guest')

@section('title', 'Mes commandes')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/commande.css') }}">
@endsection

@section('content')
<main class="container">
    <header class="page-header">
        <h1>Mes commandes</h1>
        <p class="muted">Historique et statut de vos commandes</p>
    </header>

    @if($orders->isEmpty())
        <div class="empty">
            <p>Vous n'avez aucune commande pour le moment.</p>
            <a href="{{ url('/') }}" class="btn">Continuer vos achats</a>
        </div>
    @else
        <div class="orders-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Réf commande</th>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Méthode</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $order->payment_method ?? '—' }}</td>
                        <td>
                            <span class="status status-{{ \Illuminate\Support\Str::slug($order->status) }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                        <td>
                            {{-- Ajuste le nom de la route show si nécessaire
                           <!-- <a href="{{ route('order.show', $order->id) }}" class="btn-sm">Détails</a>-->--}}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $orders->links() }}
            </div>
        </div>
    @endif
</main>
@endsection

