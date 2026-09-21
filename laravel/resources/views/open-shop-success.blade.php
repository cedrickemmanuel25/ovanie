<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statut boutique - OVANIE</title>
    <link rel="stylesheet" href="{{ asset('css/open-shop.css') }}?v={{ file_exists(public_path('css/open-shop.css')) ? filemtime(public_path('css/open-shop.css')) : time() }}">
    <style>
        .success-card { max-width: 920px; }
        .success-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 26px;
        }
        .button-outline {
            background: #ffffff;
            color: #001450;
            border: 1px solid #d7e0ec;
        }
        .button-outline:hover {
            border-color: #ff6a00;
            color: #ff6a00;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 34px;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 18px;
        }
        .status-approved { background: #dcfce7; color: #15803d; }
        .status-pending { background: #fff7ed; color: #ea580c; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }
        .success-check.approved { background: #dcfce7; color: #16a34a; }
        .success-check.pending { background: #fff7ed; color: #f97316; }
        .success-check.rejected { background: #fee2e2; color: #dc2626; }
        .shop-summary {
            max-width: 560px;
            margin: 18px auto 0;
            padding: 16px 18px;
            border: 1px solid #e5edf7;
            border-radius: 16px;
            background: #f8fafc;
            text-align: left;
        }
        .shop-summary div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 7px 0;
            color: #475569;
            font-size: 14px;
        }
        .shop-summary strong { color: #001450; text-align: right; }
        .rejection-box {
            max-width: 620px;
            margin: 18px auto 0;
            padding: 15px 18px;
            border-radius: 14px;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #991b1b;
            text-align: left;
        }
        @media (max-width: 640px) {
            .success-actions .button { width: 100%; text-align: center; }
            .shop-summary div { flex-direction: column; gap: 4px; }
            .shop-summary strong { text-align: left; }
        }
    </style>
</head>
<body class="success-page">
    @php
        $status = strtolower((string) ($shop->status ?? 'pending'));
        $isActive = (bool) ($shop->is_active ?? false);
        $isApproved = $status === 'approved' && $isActive;
        $isRejected = $status === 'rejected';
        $dashboardUrl = Route::has('vendor.dashboard') ? route('vendor.dashboard') : url('/vendor/dashboard');
        $addProductUrl = Route::has('vendor.add_product') ? route('vendor.add_product') : url('/vendor/products/create?type=single');
    @endphp

    <header class="wizard-header">
        <a href="{{ route('vendor.dashboard') }}" class="wizard-brand">
            <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
        </a>
        <a class="help-link" href="{{ url('/contact') }}">
            <span>Besoin d'aide ?<strong>Centre d'assistance</strong></span>
            <span class="help-icon">?</span>
        </a>
    </header>

    <main class="success-card">
        @if ($isApproved)
            <div class="success-check approved">✓</div>
            <span class="status-pill status-approved">Boutique approuvée et active</span>

            <h1>Votre boutique est approuvée !</h1>
            <p>
                La boutique <strong>{{ $shop->name }}</strong> est maintenant active.
                Vous pouvez accéder à votre espace vendeur et publier vos produits.
            </p>

            <div class="shop-summary">
                <div><span>Boutique</span><strong>{{ $shop->name }}</strong></div>
                <div><span>Ville</span><strong>{{ $shop->city ?? 'Non renseignée' }}</strong></div>
                <div><span>Statut</span><strong>Approved · Active</strong></div>
            </div>

            <div class="success-actions">
                <a href="{{ $dashboardUrl }}" class="button button-primary">Accéder à mon espace vendeur</a>
                <a href="{{ $addProductUrl }}" class="button button-outline">Ajouter un produit</a>
                <a href="{{ $dashboardUrl }}" class="button button-outline">Gérer ma boutique</a>
            </div>
        @elseif ($isRejected)
            <div class="success-check rejected">!</div>
            <span class="status-pill status-rejected">Boutique rejetée</span>

            <h1>Votre demande nécessite une correction</h1>
            <p>
                La demande pour <strong>{{ $shop->name }}</strong> a été rejetée par l'administration OVANIE.
                Corrigez les informations demandées, puis soumettez à nouveau votre dossier.
            </p>

            @if (! empty($shop->rejection_reason))
                <div class="rejection-box">
                    <strong>Raison du rejet :</strong><br>
                    {{ $shop->rejection_reason }}
                </div>
            @endif

            <div class="success-actions">
                <a href="{{ Route::has('shops.edit') ? route('shops.edit', $shop) : route('open-shop') }}" class="button button-primary">Corriger ma demande</a>
                <a href="{{ route('vendor.dashboard') }}" class="button button-outline">Accéder au tableau de bord</a>
            </div>
        @else
            <div class="success-check pending">✓</div>
            <span class="status-pill status-pending">En attente de validation</span>

            <h1>Votre boutique est ouverte !</h1>
            <p>
                La demande pour <strong>{{ $shop->name }}</strong> est en cours d'examen.
                Vous recevrez une réponse sous 24 à 48 heures.
            </p>

            <div class="shop-summary">
                <div><span>Boutique</span><strong>{{ $shop->name }}</strong></div>
                <div><span>Ville</span><strong>{{ $shop->city ?? 'Non renseignée' }}</strong></div>
                <div><span>Statut</span><strong>En attente</strong></div>
            </div>

            <div class="success-actions">
                <a href="{{ Route::has('vendor.shop-status') ? route('vendor.shop-status') : url('/vendor/shop-status') }}" class="button button-primary">Suivre ma demande</a>
                <a href="{{ route('vendor.dashboard') }}" class="button button-outline">Accéder au tableau de bord</a>
            </div>
        @endif
    </main>
</body>
</html>
