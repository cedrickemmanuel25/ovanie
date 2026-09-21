@extends('admin.layouts.app')

@section('title', 'Optimisations panier | Admin OVANIE')
@section('page-title', 'Optimisations panier')

@section('content')
<div style="display:grid;gap:18px;">
    <div>
        <h2 style="margin:0;font-size:20px;">Audit interne des optimisations</h2>
        <p style="margin:6px 0 0;color:#64748b;">Historique des remplacements stricts appliques au panier pour reduire les frais de livraison.</p>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:980px;">
            <thead>
                <tr style="background:#f8fafc;text-align:left;">
                    <th style="padding:12px;">Date</th>
                    <th style="padding:12px;">Commande</th>
                    <th style="padding:12px;">Produit original</th>
                    <th style="padding:12px;">Produit final</th>
                    <th style="padding:12px;">Point original</th>
                    <th style="padding:12px;">Point final</th>
                    <th style="padding:12px;">Avant</th>
                    <th style="padding:12px;">Apres</th>
                    <th style="padding:12px;">Economie</th>
                    <th style="padding:12px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($optimizations as $optimization)
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:12px;">{{ optional($optimization->created_at)->format('d/m/Y H:i') }}</td>
                        <td style="padding:12px;">{{ $optimization->order_id ? '#'.$optimization->order_id : 'Panier #'.$optimization->cart_id }}</td>
                        <td style="padding:12px;">{{ $optimization->originalProduct?->name ?: '-' }}</td>
                        <td style="padding:12px;">{{ $optimization->fulfillmentProduct?->name ?: '-' }}</td>
                        <td style="padding:12px;">{{ $optimization->originalShop?->name ?: '-' }}</td>
                        <td style="padding:12px;">{{ $optimization->fulfillmentShop?->name ?: '-' }}</td>
                        <td style="padding:12px;">{{ number_format((float) $optimization->original_delivery_fee, 0, ',', ' ') }} FCFA</td>
                        <td style="padding:12px;">{{ number_format((float) $optimization->optimized_delivery_fee, 0, ',', ' ') }} FCFA</td>
                        <td style="padding:12px;color:#166534;font-weight:800;">{{ number_format((float) $optimization->savings, 0, ',', ' ') }} FCFA</td>
                        <td style="padding:12px;"><a href="{{ route('admin.logistics.cart-optimizations.show', $optimization) }}">Voir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:16px;color:#64748b;">Aucune optimisation auditee pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $optimizations->links() }}
</div>
@endsection
