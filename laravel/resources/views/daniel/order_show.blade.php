{{-- PARTIE MODIFIÉE / AJOUTÉE : Refonte du design (Boutons pro, Grid overflow fixé, Typographie Inter) --}}
@extends('layouts.vendor')

@section('title', 'Détails de la commande | OVANIE')

@section('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* =============================================
           DESIGN ULTRA PRO — Détails Commande
        ============================================= */

        /* Reset critique pour éviter le débordement */
        .vd-content { overflow-x: hidden; }
        *, *::before, *::after { box-sizing: border-box; }
        
        .main-content {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            padding: 28px;
            color: #0f172a;
            width: 100%;
            min-width: 0;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .vd-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .vd-breadcrumb a { color: #94a3b8; text-decoration: none; transition: color 0.15s; }
        .vd-breadcrumb a:hover { color: #0ea5e9; }
        .vd-breadcrumb .sep { color: #cbd5e1; }
        .vd-breadcrumb .active-crumb { color: #0ea5e9; }

        .header-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-validated { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .status-shipped { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .status-completed { background: #dcfce3; color: #166534; border: 1px solid #bbf7d0; }
        .status-cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* GRILLE PRINCIPALE — minmax(0,1fr) évite le débordement */
        .order-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1.1fr);
            gap: 20px;
            align-items: start;
            width: 100%;
        }

        .left-col, .right-col { min-width: 0; }

        @media(max-width: 1000px) {
            .order-grid { grid-template-columns: 1fr; }
        }

        /* Pro Cards */
        .vd-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
        }

        .vd-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }
        
        .vd-card-title i {
            color: #0ea5e9;
            stroke-width: 2.5px;
        }

        /* Summary Info */
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .summary-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .summary-label {
            color: #64748b;
            font-weight: 500;
        }
        .summary-value {
            font-weight: 600;
            color: #0f172a;
        }

        .total-row {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .total-label { font-size: 0.95rem; font-weight: 700; color: #334155; }
        .total-value { font-size: 1.4rem; font-weight: 800; color: #0ea5e9; letter-spacing: -0.02em; }

        /* Pro Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            text-align: left;
            padding: 12px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table td {
            padding: 16px 10px;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .items-table tr:last-child td { border-bottom: none; }
        
        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .product-img {
            width: 45px;
            height: 45px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
        .product-name {
            font-weight: 600;
            color: #1e293b;
            display: block;
        }

        /* Form Controls */
        .tracking-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #0f172a;
            background: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        
        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-control:focus {
            border-color: #38bdf8;
            outline: none;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        /* Custom File Upload Pro */
        .custom-file-upload {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .custom-file-upload:hover {
            border-color: #0ea5e9;
            background: #f0f9ff;
            color: #0ea5e9;
        }
        .custom-file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-name-display {
            display: block;
            margin-top: 4px;
            font-size: 0.75rem;
            color: #10b981;
            font-weight: 600;
            text-align: center;
        }

        /* Pro Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-validate { background: #0f172a; color: #fff; }
        .btn-payment { background: #0ea5e9; color: #fff; }
        .btn-ship { background: #0f172a; color: #fff; }

        .btn-location {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
        }
        .btn-location:hover { background: #f8fafc; border-color: #94a3b8; }

        /* OTP Pro Box */
        .otp-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        
        .otp-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: #10b981;
            border-radius: 12px 12px 0 0;
        }

        .otp-box .vd-card-title {
            color: #0f172a;
        }
        
        .otp-box .vd-card-title i {
            color: #10b981;
            background: #ecfdf5;
        }
        
        .otp-display {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .otp-display .label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .otp-display .code {
            letter-spacing: 4px;
            color: #0f172a;
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .btn-success {
            background: #10b981;
            color: #fff;
            width: 100%;
        }
        .btn-success:hover {
            background: #059669;
        }
    

/* OVANIE PREMIUM PLUS UI */
.vd-premium-hero,.page-premium-hero{background:linear-gradient(135deg,#111827,#1e3a8a);color:#fff;border-radius:24px;padding:24px;margin-bottom:24px;box-shadow:0 18px 45px rgba(15,23,42,.16)}
.page-header,.orders-card,.table-container,.add-option-card,.main-content,.vd-card{box-shadow:0 14px 35px rgba(15,23,42,.07)!important;border-radius:18px!important;border-color:#dbe4f0!important}.add-option-card:hover,.table-container:hover,.orders-card:hover{box-shadow:0 20px 50px rgba(15,23,42,.10)!important}.btn-import,.btn-add-product,.add-option-btn,.btn-view,.btn-print{border-radius:12px!important;font-weight:900!important}.btn-import,.add-option-btn{background:#ff9900!important;color:#111827!important}.status-pill.active,.tab.active{color:#ff9900!important}.status-pill.active{background:#fff7ed!important;border-color:#fed7aa!important}.products-table th,.orders-table th{background:#f8fafc!important;text-transform:uppercase;letter-spacing:.05em}.product-thumb,.product-img{border-radius:12px!important}.premium-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-size:.75rem;font-weight:900}.ov-progress{height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden}.ov-progress span{display:block;height:100%;background:#ff9900;border-radius:999px}

</style>
@endsection

@section('content')
    <main class="main-content">
        <div class="page-header">
            <div>
                <div class="vd-breadcrumb">
                    <a href="{{ route('daniel.dashboard') }}">Dashboard</a> 
                    <span style="margin: 0 6px;">/</span> 
                    <a href="{{ route('daniel.orders') }}">Commandes</a>
                    <span style="margin: 0 6px;">/</span> 
                    <span class="active-crumb">#{{ $order->order_number ?? $order->id }}</span>
                </div>
                <div class="header-title-row">
                    <h1>Commande #{{ $order->order_number ?? $order->id }}</h1>
                    <span class="status-badge status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px;">
                @if($order->status === 'pending')
                    <form action="{{ route('daniel.orders.validate', $order) }}" method="POST">
                        @csrf
                        <button class="btn btn-validate"><i data-lucide="check" style="width:16px;"></i> Valider la commande</button>
                    </form>
                @endif

                @if($order->payment_status !== 'paid')
                    <form action="{{ route('daniel.orders.confirmPayment', $order) }}" method="POST">
                        @csrf
                        <button class="btn btn-payment"><i data-lucide="credit-card" style="width:16px;"></i> Confirmer paiement</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="order-grid">
            
            <!-- COLONNE GAUCHE -->
            <div class="left-col">
                
                <div class="vd-card">
                    <div class="vd-card-title"><i data-lucide="package" style="width:20px;"></i> Articles commandés</div>
                    
                    <div style="overflow-x: auto;">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Prix unitaire</th>
                                    <th>Qté</th>
                                    <th>Sous-total</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $shopId = auth()->user()->shop->id;
                                    $shopItems = $order->items->where('shop_id', $shopId);
                                @endphp

                                @forelse($shopItems as $item)
                                    <tr>
                                        <td>
                                            <div class="product-cell">
                                                <img src="{{ $item->product->main_image_url }}" alt="Img" class="product-img">
                                                <span class="product-name">{{ $item->product->name }}</span>
                                            </div>
                                        </td>
                                        <td style="color:#64748b;">{{ number_format($item->price, 0, ',', ' ') }} XOF</td>
                                        <td style="font-weight:600;">{{ $item->quantity }}</td>
                                        <td style="font-weight:700; color:#0f172a;">{{ number_format($item->subtotal, 0, ',', ' ') }} XOF</td>
                                        <td>
                                            @php $movement = $item->vendor_delivery_status ?? 'pending'; @endphp
                                            @if($movement === 'pending')
                                                <span style="color:#d97706; font-size:0.75rem; font-weight:600; background:#fef3c7; padding:4px 8px; border-radius:4px;">En attente</span>
                                            @elseif($movement === 'in_delivery')
                                                <span style="color:#0284c7; font-size:0.75rem; font-weight:600; background:#e0f2fe; padding:4px 8px; border-radius:4px;">En livraison</span>
                                            @elseif($movement === 'delivered')
                                                <span style="color:#16a34a; font-size:0.75rem; font-weight:600; background:#dcfce3; padding:4px 8px; border-radius:4px;">Livré</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align:center; color:#94a3b8; padding: 30px;">Aucun produit pour votre boutique.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @php
                    $currentItem = $shopItems->first();
                    $currentVendorStatus = $currentItem->vendor_delivery_status ?? 'pending';
                @endphp

                <!-- SECTION SUIVI VENDEUR -->
                <div class="vd-card">
                    <div class="vd-card-title"><i data-lucide="truck" style="width:20px;"></i> Suivi Logistique</div>
                    
                    <form action="{{ route('daniel.orders.deliveryStatus', $order) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="tracking-grid">
                            <div class="form-group">
                                <label>Statut de livraison</label>
                                <select name="vendor_delivery_status" required class="form-control">
                                    <option value="pending" @selected($currentVendorStatus === 'pending')>En attente</option>
                                    <option value="in_delivery" @selected($currentVendorStatus === 'in_delivery')>En cours de livraison</option>
                                    <option value="delivered" @selected($currentVendorStatus === 'delivered')>Livré avec succès</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Nom du chauffeur</label>
                                <input type="text" name="driver_name" class="form-control" value="{{ $currentItem->driver_name ?? '' }}" placeholder="Ex: Jean Dupont">
                            </div>
                            <div class="form-group">
                                <label>Téléphone chauffeur</label>
                                <input type="text" name="driver_phone" class="form-control" value="{{ $currentItem->driver_phone ?? '' }}" placeholder="07 XX XX XX XX">
                            </div>
                            <div class="form-group">
                                <label>Immatriculation</label>
                                <input type="text" name="vehicle_plate" class="form-control" value="{{ $currentItem->vehicle_plate ?? '' }}" placeholder="Ex: 1234 AB 01">
                            </div>
                            <div class="form-group">
                                <label>Photo chargement</label>
                                <label class="custom-file-upload">
                                    <input type="file" name="pickup_photo" accept="image/*" class="file-input-pro">
                                    <i data-lucide="upload" style="width:16px;"></i> <span>Ajouter</span>
                                </label>
                                <span class="file-name-display pickup-name"></span>
                            </div>
                            <div class="form-group">
                                <label>Photo livraison</label>
                                <label class="custom-file-upload">
                                    <input type="file" name="delivery_photo" accept="image/*" class="file-input-pro">
                                    <i data-lucide="upload" style="width:16px;"></i> <span>Ajouter</span>
                                </label>
                                <span class="file-name-display delivery-name"></span>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Journal de bord</label>
                            <textarea name="vendor_delivery_note" rows="2" class="form-control" placeholder="Détails, horaires, remarques...">{{ $currentItem->vendor_delivery_note ?? '' }}</textarea>
                        </div>

                        <div style="display:flex; gap:12px; align-items:center;">
                            <button type="submit" class="btn btn-ship" style="flex:1;">
                                <i data-lucide="save" style="width:16px;"></i> Mettre à jour
                            </button>
                            <button type="button" id="shareLocationBtn" class="btn btn-location">
                                <i data-lucide="map-pin" style="width:16px;"></i> Position
                            </button>
                        </div>
                        <div id="gpsStatus" style="margin-top:10px; font-size:0.8rem; font-weight:600; text-align:right;"></div>

                    </form>
                </div>

            </div>

            <!-- COLONNE DROITE -->
            <div class="right-col">
                
                <div class="vd-card">
                    <div class="vd-card-title"><i data-lucide="user" style="width:20px;"></i> Client</div>
                    <div class="summary-item">
                        <span class="summary-label">Nom Complet</span>
                        <span class="summary-value">{{ $order->client->name ?? '—' }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Email</span>
                        <span class="summary-value" style="word-break: break-all; text-align: right; color:#0ea5e9;">{{ $order->client->email ?? '—' }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Téléphone</span>
                        <span class="summary-value">{{ $order->client->phone ?? '—' }}</span>
                    </div>
                </div>

                <div class="vd-card">
                    <div class="vd-card-title"><i data-lucide="file-text" style="width:20px;"></i> Facturation</div>
                    <div class="summary-item">
                        <span class="summary-label">Date</span>
                        <span class="summary-value">{{ $order->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Moyen de paiement</span>
                        <span class="summary-value">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Paiement</span>
                        <span class="summary-value" style="{{ $order->payment_status == 'paid' ? 'color:#10b981;' : 'color:#d97706;' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                    
                    @if($order->shipment_date)
                        <div class="summary-item">
                            <span class="summary-label">Expédié le</span>
                            <span class="summary-value">{{ $order->shipment_date }}</span>
                        </div>
                    @endif
                    @if($order->tracking_number)
                        <div class="summary-item">
                            <span class="summary-label">Suivi</span>
                            <span class="summary-value" style="font-family:monospace; color:#64748b;">{{ $order->tracking_number }}</span>
                        </div>
                    @endif

                    <div class="total-row">
                        <span class="total-label">Total Boutique</span>
                        <span class="total-value">{{ number_format($order->items->where('shop_id', auth()->user()->shop->id)->sum('subtotal'), 0, ',', ' ') }} <span style="font-size:1rem;">XOF</span></span>
                    </div>
                </div>

                <!-- OTP Validation Client -->
                <div class="vd-card otp-box">
                    <div class="vd-card-title"><i data-lucide="shield-check" style="width:20px;"></i> Code OTP Livraison</div>
                    <p style="font-size:0.8rem; color:#475569; line-height:1.5; margin-bottom:15px;">
                        À demander au client <strong>uniquement après</strong> remise de la commande en main propre.
                    </p>
                    
                    @if(!empty($currentItem->delivery_otp_code))
                        <div class="otp-display">
                            <div class="label">Code généré pour le client</div>
                            <div class="code">{{ $currentItem->delivery_otp_code }}</div>
                        </div>
                    @endif

                    <form action="{{ route('daniel.orders.verifyOtp', $order) }}" method="POST">
                        @csrf
                        <div class="form-group" style="margin-bottom:12px;">
                            <input type="text" name="delivery_otp_code" class="form-control" placeholder="Code client..." required style="text-align:center; font-weight:700; font-size:1rem; letter-spacing:2px;">
                        </div>
                        <button type="submit" class="btn btn-success">
                            Valider
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </main>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Custom File Inputs Logic
            const fileInputs = document.querySelectorAll('.file-input-pro');
            fileInputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    const display = this.parentElement.nextElementSibling;
                    if(this.files && this.files.length > 0) {
                        display.textContent = this.files[0].name;
                        display.style.display = 'block';
                    } else {
                        display.style.display = 'none';
                    }
                });
            });

            const btn = document.getElementById('shareLocationBtn');
            const gpsStatus = document.getElementById('gpsStatus');

            if (!btn) return;

            btn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    gpsStatus.innerHTML = '<span style="color:#ef4444;">GPS non supporté sur cet appareil.</span>';
                    return;
                }

                gpsStatus.innerHTML = '<span style="color:#0ea5e9;">Récupération... <i data-lucide="loader" class="lucide-spin" style="width:14px;vertical-align:-2px;"></i></span>';
                if (typeof lucide !== 'undefined') lucide.createIcons({root: gpsStatus});

                navigator.geolocation.getCurrentPosition(function (position) {
                    fetch("{{ route('daniel.orders.driverLocation', $order) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({
                            driver_latitude: position.coords.latitude,
                            driver_longitude: position.coords.longitude
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            gpsStatus.innerHTML = '<span style="color:#10b981;">📍 Env. avec succès.</span>';
                        } else {
                            gpsStatus.innerHTML = '<span style="color:#ef4444;">Erreur d\'envoi.</span>';
                        }
                    })
                    .catch(e => {
                        gpsStatus.innerHTML = '<span style="color:#ef4444;">Erreur réseau.</span>';
                    });
                }, function (error) {
                    gpsStatus.innerHTML = '<span style="color:#ef4444;">Accès GPS refusé.</span>';
                });
            });
        });
    </script>
@endsection