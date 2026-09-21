<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#6d28d9">
    <title>OVANIE Livreur — {{ $driver->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple: #6d28d9;
            --purple-light: #7c3aed;
            --purple-dark: #5b21b6;
            --green: #16a34a;
            --blue: #2563eb;
            --amber: #d97706;
            --red: #dc2626;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-500: #64748b;
            --slate-700: #334155;
            --slate-900: #0f172a;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--slate-50);
            min-height: 100dvh;
            color: var(--slate-900);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 60%, #a78bfa 100%);
            padding: 20px 20px 28px;
            position: relative;
            overflow: hidden;
        }
        .header::after {
            content: '';
            position: absolute;
            bottom: -20px; left: 0; right: 0;
            height: 40px;
            background: var(--slate-50);
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        .header-logo svg { width: 28px; height: 28px; }
        .header-logo span { font-size: 18px; font-weight: 900; color: #fff; letter-spacing: -0.5px; }
        .header-driver {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .avatar {
            width: 50px; height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,.2);
            border: 2.5px solid rgba(255,255,255,.5);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .driver-info h1 { font-size: 17px; font-weight: 800; color: #fff; }
        .driver-info p { font-size: 12px; color: rgba(255,255,255,.75); margin-top: 2px; }
        .status-dot {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 20px;
            padding: 3px 10px 3px 7px;
            font-size: 11px; font-weight: 600; color: #fff;
            margin-top: 6px;
        }
        .status-dot-circle {
            width: 7px; height: 7px; border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 3px rgba(74,222,128,.3);
        }

        /* ── Body ── */
        .body { padding: 24px 16px 100px; }

        /* ── Alert ── */
        .alert-success {
            background: #d1fae5; border: 1px solid #6ee7b7;
            border-radius: 12px; padding: 12px 16px;
            font-size: 13px; font-weight: 600; color: #065f46;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-success::before { content: '✓'; font-size: 15px; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center; padding: 60px 20px;
        }
        .empty-state .icon {
            font-size: 56px; margin-bottom: 16px;
        }
        .empty-state h2 { font-size: 18px; font-weight: 800; color: var(--slate-700); }
        .empty-state p { font-size: 13px; color: var(--slate-500); margin-top: 6px; }

        /* ── Section title ── */
        .section-title {
            font-size: 11px; font-weight: 700; color: var(--slate-500);
            text-transform: uppercase; letter-spacing: .8px;
            margin-bottom: 12px;
        }

        /* ── Mission card ── */
        .mission-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07), 0 0 0 1px rgba(0,0,0,.04);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .mission-header {
            padding: 14px 16px 12px;
            border-bottom: 1px solid var(--slate-100);
            display: flex; align-items: center; justify-content: space-between;
        }
        .order-ref {
            font-size: 13px; font-weight: 800; color: var(--slate-900);
        }
        .status-badge {
            font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            white-space: nowrap;
        }

        /* ── Steps progress ── */
        .steps {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            gap: 0;
            border-bottom: 1px solid var(--slate-100);
        }
        .step {
            display: flex; flex-direction: column; align-items: center;
            flex: 1; position: relative;
        }
        .step-circle {
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 2.5px solid var(--slate-200);
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px;
            z-index: 1;
        }
        .step.done .step-circle {
            background: var(--green); border-color: var(--green); color: #fff;
        }
        .step.active .step-circle {
            border-color: var(--purple); background: var(--purple); color: #fff;
            box-shadow: 0 0 0 4px rgba(109,40,217,.15);
        }
        .step-label {
            font-size: 9px; font-weight: 600; color: var(--slate-500);
            text-align: center; margin-top: 4px; line-height: 1.2;
            max-width: 60px;
        }
        .step.done .step-label, .step.active .step-label { color: var(--slate-900); }
        .step-line {
            flex: 1; height: 2px; background: var(--slate-200);
            margin: 0 -2px; margin-bottom: 24px;
        }
        .step-line.done { background: var(--green); }

        /* ── Info rows ── */
        .info-block { padding: 14px 16px; }
        .info-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 12px;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-icon {
            width: 34px; height: 34px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 16px;
        }
        .info-icon.shop   { background: #ede9fe; }
        .info-icon.client { background: #dbeafe; }
        .info-icon.clock  { background: #fef3c7; }
        .info-text { flex: 1; min-width: 0; }
        .info-label { font-size: 10px; font-weight: 600; color: var(--slate-500); text-transform: uppercase; letter-spacing: .5px; }
        .info-value { font-size: 13px; font-weight: 700; color: var(--slate-900); margin-top: 1px; }
        .info-sub   { font-size: 12px; color: var(--slate-500); margin-top: 1px; }

        /* ── Phone button ── */
        .phone-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--slate-100); border: none; border-radius: 8px;
            padding: 5px 10px; font-size: 12px; font-weight: 700; color: var(--slate-700);
            text-decoration: none; margin-top: 5px; cursor: pointer;
        }
        .phone-btn:active { background: var(--slate-200); }

        /* ── Action button ── */
        .action-area {
            padding: 0 16px 16px;
        }
        .action-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; border: none; border-radius: 14px;
            padding: 17px 20px;
            font-size: 15px; font-weight: 800; color: #fff;
            cursor: pointer;
            text-decoration: none;
            transition: transform .12s, box-shadow .12s;
            box-shadow: 0 6px 20px rgba(0,0,0,.18);
            -webkit-tap-highlight-color: transparent;
        }
        .action-btn:active {
            transform: scale(.97);
            box-shadow: 0 3px 10px rgba(0,0,0,.15);
        }
        .action-btn .btn-icon { font-size: 20px; }

        .action-btn.amber   { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .action-btn.blue    { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
        .action-btn.purple  { background: linear-gradient(135deg, #5b21b6, #7c3aed); }
        .action-btn.green   { background: linear-gradient(135deg, #15803d, #16a34a); }

        /* Confirmation overlay */
        .confirm-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 100;
            background: rgba(0,0,0,.55);
            align-items: flex-end;
            justify-content: center;
        }
        .confirm-overlay.open { display: flex; }
        .confirm-sheet {
            background: #fff;
            border-radius: 24px 24px 0 0;
            padding: 28px 24px 40px;
            width: 100%; max-width: 480px;
            animation: slideUp .25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to   { transform: translateY(0); }
        }
        .confirm-sheet h2 { font-size: 18px; font-weight: 900; color: var(--slate-900); margin-bottom: 6px; }
        .confirm-sheet p  { font-size: 13px; color: var(--slate-500); margin-bottom: 24px; }
        .confirm-btns { display: flex; flex-direction: column; gap: 10px; }
        .btn-cancel {
            display: flex; align-items: center; justify-content: center;
            width: 100%; padding: 14px;
            background: var(--slate-100); border: none; border-radius: 12px;
            font-size: 14px; font-weight: 700; color: var(--slate-700);
            cursor: pointer;
        }

        /* GPS indicator */
        .gps-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #fff; border-top: 1px solid var(--slate-100);
            padding: 10px 20px;
            display: flex; align-items: center; justify-content: space-between;
            z-index: 50;
        }
        .gps-info { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: var(--slate-500); }
        .gps-dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; }
        .gps-dot.active { background: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.2); }
        .gps-toggle {
            font-size: 11px; font-weight: 700; color: var(--purple);
            background: none; border: none; cursor: pointer;
            padding: 6px 12px; border-radius: 8px; background: #ede9fe;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-logo">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="20" fill="rgba(255,255,255,.15)"/>
            <path d="M10 25l10-8 10 8" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13 22v7h14v-7" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="17" y="24" width="6" height="5" rx="1" fill="rgba(255,255,255,.8)"/>
        </svg>
        <span>OVANIE Livraison</span>
    </div>
    <div class="header-driver">
        <div class="avatar">{{ strtoupper(substr($driver->name, 0, 2)) }}</div>
        <div class="driver-info">
            <h1>{{ $driver->name }}</h1>
            <p>{{ $driver->phone }}</p>
            <div class="status-dot">
                <span class="status-dot-circle"></span>
                {{ $driver->zone ?: 'Toutes zones' }}
            </div>
        </div>
    </div>
</div>

<!-- BODY -->
<div class="body">

    @if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($assignments->isEmpty())
    <div class="empty-state">
        <div class="icon">🛵</div>
        <h2>Aucune mission en cours</h2>
        <p>Vous n'avez pas de livraison active pour le moment.<br>Le responsable vous assignera bientôt une mission.</p>
    </div>
    @else

    <p class="section-title">{{ $assignments->count() }} mission{{ $assignments->count() > 1 ? 's' : '' }} active{{ $assignments->count() > 1 ? 's' : '' }}</p>

    @foreach($assignments as $mission)
    @php
        $step = $mission['status_step'];
        $colors = [
            0 => ['bg' => '#ede9fe', 'text' => '#6d28d9'],
            1 => ['bg' => '#fef3c7', 'text' => '#b45309'],
            2 => ['bg' => '#dbeafe', 'text' => '#1d4ed8'],
            3 => ['bg' => '#f3e8ff', 'text' => '#7c3aed'],
            4 => ['bg' => '#dcfce7', 'text' => '#15803d'],
        ];
        $badgeColor = $colors[$step] ?? $colors[0];
        $stepLabels = ['Assigné', 'Vers magasin', 'Récupéré', 'Chez client', 'Livré'];
        $btnClass = match($mission['next_action']['color'] ?? '') {
            '#f59e0b' => 'amber',
            '#3b82f6' => 'blue',
            '#8b5cf6' => 'purple',
            '#16a34a' => 'green',
            default   => 'purple',
        };
    @endphp

    <div class="mission-card" id="mission-{{ $mission['id'] }}">

        <!-- Header commande -->
        <div class="mission-header">
            <div>
                <div class="order-ref">Commande {{ $mission['order_ref'] }}</div>
                @if($mission['scheduled_at'])
                <div style="font-size:11px;color:var(--slate-500);margin-top:2px;">📅 {{ $mission['scheduled_at'] }}</div>
                @endif
            </div>
            <span class="status-badge" style="background:{{ $badgeColor['bg'] }};color:{{ $badgeColor['text'] }};">
                {{ $mission['status_label'] }}
            </span>
        </div>

        <!-- Progression -->
        <div class="steps">
            @foreach($stepLabels as $i => $label)
                <div class="step {{ $step > $i ? 'done' : ($step === $i ? 'active' : '') }}">
                    <div class="step-circle">
                        @if($step > $i) ✓ @else {{ $i + 1 }} @endif
                    </div>
                    <div class="step-label">{{ $label }}</div>
                </div>
                @if($i < 4)
                <div class="step-line {{ $step > $i ? 'done' : '' }}"></div>
                @endif
            @endforeach
        </div>

        <!-- Infos -->
        <div class="info-block">
            <!-- Boutique -->
            <div class="info-row">
                <div class="info-icon shop">🏪</div>
                <div class="info-text">
                    <div class="info-label">Point de ramassage</div>
                    <div class="info-value">{{ $mission['shop_name'] }}</div>
                    <div class="info-sub">{{ $mission['pickup_address'] }}</div>
                    @if($mission['shop_phone'])
                    <a class="phone-btn" href="tel:{{ $mission['shop_phone'] }}">
                        📞 {{ $mission['shop_phone'] }}
                    </a>
                    @endif
                </div>
            </div>

            <!-- Client -->
            <div class="info-row">
                <div class="info-icon client">📍</div>
                <div class="info-text">
                    <div class="info-label">Livraison chez</div>
                    <div class="info-value">{{ $mission['client_name'] }}</div>
                    <div class="info-sub">{{ $mission['delivery_address'] }}</div>
                    @if($mission['client_phone'])
                    <a class="phone-btn" href="tel:{{ $mission['client_phone'] }}">
                        📞 {{ $mission['client_phone'] }}
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Bouton d'action -->
        @if($mission['next_action'])
        <div class="action-area">
            <button
                class="action-btn {{ $btnClass }}"
                onclick="confirmAction({{ $mission['id'] }}, '{{ $mission['next_action']['action'] }}', '{{ addslashes($mission['next_action']['label']) }}')">
                <span class="btn-icon">{{ $mission['next_action']['icon'] }}</span>
                {{ $mission['next_action']['label'] }}
            </button>
        </div>
        @else
        <div class="action-area">
            <div style="text-align:center;padding:10px 0;font-size:13px;color:var(--slate-500);font-weight:600;">
                ✅ Mission terminée — en attente de validation
            </div>
        </div>
        @endif

    </div>
    @endforeach

    @endif
</div>

<!-- GPS Bar -->
<div class="gps-bar">
    <div class="gps-info">
        <div class="gps-dot" id="gpsDot"></div>
        <span id="gpsLabel">GPS inactif</span>
    </div>
    <button class="gps-toggle" onclick="toggleGPS()" id="gpsBtn">Activer GPS</button>
</div>

<!-- Confirmation Sheet -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-sheet">
        <h2 id="confirmTitle">Confirmer</h2>
        <p id="confirmDesc">Êtes-vous sûr de vouloir continuer ?</p>
        <div class="confirm-btns">
            <form id="confirmForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" id="confirmAction">
                <button type="submit" class="action-btn green" style="margin-bottom:0;">
                    ✓ Confirmer
                </button>
            </form>
            <button class="btn-cancel" onclick="closeConfirm()">Annuler</button>
        </div>
    </div>
</div>

<script>
// ── Confirmation ──────────────────────────────────────────────────────────────
const actionLabels = {
    start_pickup:   "Vous confirmez que vous partez au magasin pour récupérer la commande ?",
    confirm_pickup: "Vous confirmez que vous avez bien récupéré les produits au magasin ?",
    at_client:      "Vous confirmez que vous êtes arrivé chez le client ?",
    delivered:      "Vous confirmez que la livraison a bien été effectuée chez le client ?",
};

function confirmAction(missionId, action, label) {
    document.getElementById('confirmTitle').textContent = label;
    document.getElementById('confirmDesc').textContent = actionLabels[action] || 'Confirmer cette action ?';
    document.getElementById('confirmAction').value = action;
    document.getElementById('confirmForm').action = `/livreur/{{ $token }}/statut/${missionId}`;
    document.getElementById('confirmOverlay').classList.add('open');
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('open');
}

document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

// ── GPS ───────────────────────────────────────────────────────────────────────
let gpsWatchId = null;
let gpsActive = false;
let lastGpsSent = 0;

function toggleGPS() {
    if (gpsActive) {
        stopGPS();
    } else {
        startGPS();
    }
}

function startGPS() {
    if (!navigator.geolocation) {
        document.getElementById('gpsLabel').textContent = 'GPS non disponible';
        return;
    }
    gpsWatchId = navigator.geolocation.watchPosition(onPosition, onGpsError, {
        enableHighAccuracy: true, timeout: 15000, maximumAge: 5000
    });
    gpsActive = true;
    document.getElementById('gpsDot').classList.add('active');
    document.getElementById('gpsLabel').textContent = 'GPS actif — localisation en cours...';
    document.getElementById('gpsBtn').textContent = 'Désactiver GPS';
}

function stopGPS() {
    if (gpsWatchId !== null) navigator.geolocation.clearWatch(gpsWatchId);
    gpsActive = false;
    document.getElementById('gpsDot').classList.remove('active');
    document.getElementById('gpsLabel').textContent = 'GPS inactif';
    document.getElementById('gpsBtn').textContent = 'Activer GPS';
}

function onPosition(pos) {
    const now = Date.now();
    document.getElementById('gpsLabel').textContent = '📡 Position partagée';

    // Envoyer au serveur max toutes les 15 secondes
    if (now - lastGpsSent < 15000) return;
    lastGpsSent = now;

    // Trouver le premier assignment actif
    const missionEl = document.querySelector('.mission-card');
    if (!missionEl) return;
    const missionId = missionEl.id.replace('mission-', '');

    fetch(`/livreur/{{ $token }}/position/${missionId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            latitude:  pos.coords.latitude,
            longitude: pos.coords.longitude
        })
    }).catch(() => {});
}

function onGpsError() {
    document.getElementById('gpsLabel').textContent = 'Autoriser la localisation';
}
</script>

</body>
</html>
