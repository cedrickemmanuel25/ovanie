<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paiement OVANIE</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#071b48;font-family:Arial,sans-serif}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:min(560px,100%);background:#fff;border:1px solid #dce5f1;border-radius:22px;padding:32px;box-shadow:0 18px 45px rgba(7,27,72,.08);text-align:center}
        .icon{width:68px;height:68px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;font-size:34px;font-weight:900}
        .completed .icon{background:#e8f8ef;color:#0b9850}.pending .icon{background:#fff6e6;color:#d97706}.cancelled .icon,.invalid .icon{background:#fff0f0;color:#c62828}
        h1{font-size:28px;margin:0 0 10px}.msg{color:#61708c;line-height:1.55;margin:0 0 24px}
        .summary{background:#f8fafd;border:1px solid #e1e8f2;border-radius:14px;padding:16px;text-align:left;margin:20px 0}
        .row{display:flex;justify-content:space-between;gap:18px;padding:8px 0}.row strong{color:#071b48}.amount{color:#ff6200;font-weight:900}
        .actions{display:grid;gap:10px;margin-top:22px}.btn{display:block;text-decoration:none;border-radius:12px;padding:14px 16px;font-weight:800}
        .primary{background:#ff6200;color:white}.secondary{border:1px solid #071b48;color:#071b48;background:white}
        .hint{margin-top:18px;font-size:13px;color:#7b879d;line-height:1.5}
    </style>
</head>
<body>
<div class="wrap">
    <main class="card {{ $state }}">
        <div class="icon">{{ $state === 'completed' ? '✓' : ($state === 'pending' ? '…' : '!') }}</div>
        <h1>{{ $state === 'completed' ? 'Paiement confirmé' : ($state === 'pending' ? 'Paiement en vérification' : 'Paiement non confirmé') }}</h1>
        <p class="msg">{{ $message }}</p>

        @if($order)
            <div class="summary">
                <div class="row"><span>Commande</span><strong>#{{ $order->order_number }}</strong></div>
                <div class="row"><span>Montant</span><span class="amount">{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</span></div>
            </div>
        @endif

        <div class="actions">
            @if(($mobileReturn ?? false) && filled($appReturnUrl ?? null))
                <a class="btn primary" href="{{ $appReturnUrl }}">Retourner à l’application OVANIE</a>
            @else
                @auth
                    @if($order && (int) auth()->id() === (int) $order->client_id)
                        <a class="btn primary" href="{{ route('orders.index') }}">Voir mes commandes</a>
                    @endif
                @else
                    <a class="btn secondary" href="{{ route('login') }}">Se connecter à OVANIE</a>
                @endauth
            @endif
        </div>

        @if(($mobileReturn ?? false) && filled($appReturnUrl ?? null))
            <p class="hint">Touchez le bouton ci-dessus pour revenir dans OVANIE. L’application synchronisera automatiquement le paiement, le panier et le suivi de la commande.</p>
        @else
            <p class="hint">Votre commande reste enregistrée dans votre compte OVANIE et peut être consultée depuis Mes commandes.</p>
        @endif
    </main>
</div>
</body>
</html>
