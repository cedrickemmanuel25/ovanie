@extends('layouts.vendor')

@section('title', 'Retours et remboursements | OVANIE')

@section('styles')
<style>
    :root{
        --vr-navy:#0b2d83;
        --vr-blue:#165dff;
        --vr-blue-soft:#eef4ff;
        --vr-orange:#f97316;
        --vr-green:#16a34a;
        --vr-red:#ef4444;
        --vr-amber:#f59e0b;
        --vr-text:#112b66;
        --vr-muted:#7182a8;
        --vr-line:#e6ebf5;
        --vr-bg:#f8faff;
        --vr-card:#ffffff;
        --vr-shadow:0 5px 18px rgba(16,43,102,.045);
    }

    .vr-page{width:100%;max-width:1580px;margin:0 auto;padding:2px 0 34px;color:var(--vr-text)}
    .vr-page *{box-sizing:border-box}
    .vr-page a{text-decoration:none}
    .vr-breadcrumb{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:2px 0 18px;font-size:12px;font-weight:750;color:#8a9abb}
    .vr-breadcrumb a{color:var(--vr-blue)}
    .vr-breadcrumb svg{width:13px;height:13px;color:#b3bfd5}

    .vr-heading-row{display:flex;justify-content:space-between;align-items:flex-start;gap:22px;margin-bottom:22px}
    .vr-heading h1{margin:0;color:var(--vr-navy);font-size:31px;line-height:1.15;letter-spacing:-.7px;font-weight:850}
    .vr-heading p{margin:7px 0 0;color:#7084b0;font-size:13px;font-weight:520}
    .vr-heading-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end}
    .vr-outline-btn,.vr-primary-btn{height:43px;padding:0 17px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;gap:9px;font-size:12px;font-weight:800;transition:.2s;white-space:nowrap}
    .vr-outline-btn{border:1px solid #9db4ee;background:#fff;color:var(--vr-navy)}
    .vr-outline-btn:hover{border-color:var(--vr-blue);color:var(--vr-blue);box-shadow:0 4px 14px rgba(22,93,255,.09)}
    .vr-primary-btn{border:1px solid var(--vr-blue);background:var(--vr-blue);color:#fff}
    .vr-outline-btn svg,.vr-primary-btn svg{width:16px;height:16px;stroke-width:1.9}

    .vr-top-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:15px}
    .vr-stat-card{min-height:112px;background:var(--vr-card);border:1px solid var(--vr-line);border-radius:10px;padding:18px 18px;display:flex;align-items:flex-start;gap:15px;box-shadow:var(--vr-shadow)}
    .vr-stat-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;flex:0 0 auto}
    .vr-stat-icon svg{width:24px;height:24px;stroke-width:1.8}
    .vr-stat-icon.blue{background:#edf3ff;color:#195eff}.vr-stat-icon.orange{background:#fff2e8;color:#ff7a1a}.vr-stat-icon.green{background:#eaf8ee;color:#1aaa4c}.vr-stat-icon.red{background:#fff0f1;color:#ff3b45}
    .vr-stat-copy small{display:block;color:#526b9f;font-size:12px;font-weight:700;margin:1px 0 4px}
    .vr-stat-copy strong{display:block;color:var(--vr-navy);font-size:25px;line-height:1.05;font-weight:850}
    .vr-stat-copy span{display:block;color:#7488b3;font-size:10.5px;font-weight:650;margin-top:7px}

    .vr-overview-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(310px,.95fr);gap:15px;margin-bottom:15px}
    .vr-panel{background:#fff;border:1px solid var(--vr-line);border-radius:10px;box-shadow:var(--vr-shadow);overflow:hidden}
    .vr-chart-panel{padding:17px 19px 13px;min-height:308px}
    .vr-panel-title{display:flex;align-items:center;gap:7px;color:var(--vr-navy);font-size:13px;font-weight:850;margin:0}
    .vr-panel-title svg{width:14px;height:14px;color:#5073be}
    .vr-chart-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:8px}
    .vr-chart-total-label{color:#6d80a8;font-size:10.5px;margin-top:11px;font-weight:650}
    .vr-chart-total-line{display:flex;align-items:center;gap:9px;margin-top:2px}
    .vr-chart-total-line strong{font-size:24px;color:var(--vr-navy);font-weight:850}
    .vr-change{font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#eaf8ee;color:#129346}
    .vr-change.negative{background:#fff0f1;color:#e0434c}
    .vr-compare{font-size:10px;color:#8a9aba;font-weight:650}
    .vr-period-select{height:37px;border:1px solid #dce4f1;background:#fff;border-radius:7px;padding:0 11px;color:#6175a1;font-size:11px;font-weight:700}
    .vr-chart-wrap{height:190px;margin-top:5px;position:relative}
    #returnEvolutionChart{width:100%;height:100%;display:block}

    .vr-policy{padding:18px 20px;min-height:308px}
    .vr-policy-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px}
    .vr-active-pill{padding:5px 10px;border-radius:999px;background:#e9f8ed;color:#179646;font-size:10px;font-weight:850}
    .vr-policy-list{display:grid;gap:0}
    .vr-policy-row{display:grid;grid-template-columns:minmax(105px,.8fr) minmax(130px,1fr);gap:12px;padding:13px 0;border-bottom:1px solid #edf1f7}
    .vr-policy-row span{color:#6d80a8;font-size:10.5px;font-weight:650}
    .vr-policy-row strong{color:#173875;font-size:11px;line-height:1.4;font-weight:750}
    .vr-policy-row strong.green{color:#16994a}
    .vr-policy-link{height:39px;border:1px solid #9cb6f0;border-radius:6px;display:flex;align-items:center;justify-content:center;gap:20px;color:var(--vr-blue);font-size:11px;font-weight:800;margin-top:15px}
    .vr-policy-link svg{width:15px;height:15px}

    .vr-mini-metrics{display:grid;grid-template-columns:1.25fr repeat(5,1fr);gap:9px;margin-bottom:15px}
    .vr-mini-card{min-height:70px;background:#fff;border:1px solid var(--vr-line);border-radius:9px;display:flex;align-items:center;gap:11px;padding:12px 14px;box-shadow:var(--vr-shadow);min-width:0}
    .vr-mini-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;flex:0 0 auto;background:#eef3ff;color:#2264ff}
    .vr-mini-icon.green{background:#ebf8ee;color:#1aaa4c}.vr-mini-icon.orange{background:#fff2e8;color:#ff7a1a}.vr-mini-icon.purple{background:#f3edff;color:#7c3aed}.vr-mini-icon.red{background:#fff0f1;color:#ff3b45}
    .vr-mini-icon svg{width:17px;height:17px}
    .vr-mini-copy{min-width:0}
    .vr-mini-copy small{display:block;color:#7084ad;font-size:9.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vr-mini-copy strong{display:block;color:var(--vr-navy);font-size:15px;font-weight:850;margin-top:3px;white-space:nowrap}

    .vr-list-panel{padding:0;margin-bottom:15px}
    .vr-filters{padding:17px 17px 14px;display:grid;grid-template-columns:minmax(230px,1.5fr) minmax(130px,.72fr) minmax(130px,.72fr) minmax(125px,.62fr) minmax(125px,.62fr) auto auto;gap:10px;align-items:end;border-bottom:1px solid var(--vr-line)}
    .vr-field{min-width:0}
    .vr-field label{display:block;color:#60759f;font-size:9.5px;font-weight:800;margin:0 0 5px 5px}
    .vr-input-wrap{height:41px;border:1px solid #d8e0ee;border-radius:7px;background:#fff;display:flex;align-items:center;padding:0 11px;gap:8px}
    .vr-input-wrap svg{width:16px;height:16px;color:#5e7dbd;flex:0 0 auto}
    .vr-input-wrap input,.vr-input-wrap select{border:0;outline:0;background:transparent;width:100%;min-width:0;color:#294678;font:inherit;font-size:10.5px;font-weight:650}
    .vr-input-wrap select{appearance:auto;padding:0}
    .vr-filter-btn{height:41px;border:0;border-radius:6px;background:#1461ff;color:#fff;padding:0 17px;display:inline-flex;align-items:center;justify-content:center;gap:7px;font-size:10.5px;font-weight:800;cursor:pointer}
    .vr-filter-btn svg{width:15px;height:15px}
    .vr-reset{height:41px;display:flex;align-items:center;color:#155dff;font-size:10.5px;font-weight:750;padding:0 4px}

    .vr-table-wrap{width:100%;overflow:hidden;padding:0 17px}
    .vr-table{width:100%;min-width:0;border-collapse:collapse;table-layout:fixed;font-size:10.3px}
    .vr-table th{height:43px;text-align:left;padding:0 7px;color:#50699b;font-size:8.8px;font-weight:850;text-transform:uppercase;letter-spacing:.01em;border-bottom:1px solid var(--vr-line);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vr-table td{height:48px;padding:6px 7px;border-bottom:1px solid #edf1f7;color:#2d4a7d;font-weight:620;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vr-table td a,.vr-table td span{max-width:100%}
    .vr-table .vr-action-btn{white-space:nowrap}
    .vr-table .vr-status{min-width:0;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .vr-table tbody tr:hover{background:#fafcff}
    .vr-ref,.vr-order-link{color:#135fff;font-weight:800}
    .vr-client{font-weight:750;color:#21427a}
    .vr-reason{max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
    .vr-money{font-weight:800;color:#22447e}
    .vr-status{display:inline-flex;align-items:center;justify-content:center;min-width:62px;padding:5px 7px;border-radius:5px;font-size:9px;font-weight:800}
    .vr-status.pending{background:#fff1dc;color:#f47b13}.vr-status.accepted{background:#e9f8ee;color:#16994a}.vr-status.refunded{background:#e7f7ec;color:#159347}.vr-status.rejected{background:#fff0f1;color:#ec4450}.vr-status.closed,.vr-status.resolved{background:#edf3ff;color:#1e62ed}.vr-status.inspection{background:#edf3ff;color:#1e62ed}
    .vr-decision{font-size:9.5px;font-weight:700;color:#5c719c}
    .vr-action-btn{border:0;background:transparent;color:#1260ff;font-size:9.8px;font-weight:850;display:inline-flex;align-items:center;gap:4px;cursor:pointer;padding:4px}
    .vr-action-btn svg{width:13px;height:13px}
    .vr-empty{padding:56px 20px;text-align:center;color:#7a8daf}
    .vr-empty svg{display:block;margin:0 auto 10px;width:31px;height:31px;color:#a9b6cf}

    .vr-table-footer{min-height:58px;padding:10px 17px;display:flex;align-items:center;justify-content:space-between;gap:16px;color:#7084aa;font-size:10px;font-weight:650}
    .vr-pagination{display:flex;align-items:center;gap:7px}
    .vr-page-btn{width:34px;height:34px;border:1px solid #dce4f1;border-radius:6px;display:grid;place-items:center;color:#24457f;background:#fff;font-size:10px;font-weight:800}
    .vr-page-btn.active{border-color:#1461ff;color:#1461ff;box-shadow:inset 0 0 0 1px #1461ff}
    .vr-page-btn.disabled{opacity:.35;pointer-events:none}
    .vr-page-btn svg{width:14px;height:14px}
    .vr-per-page{height:34px;border:1px solid #dce4f1;border-radius:6px;color:#5f729a;background:#fff;font-size:10px;font-weight:700;padding:0 8px}

    .vr-bottom-grid{display:grid;grid-template-columns:1.18fr 1fr;gap:15px;margin-bottom:15px}
    .vr-history,.vr-documents{padding:18px 20px;min-height:305px}
    .vr-case-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
    .vr-case-meta{color:#6f82a8;font-size:9.8px;font-weight:650;margin-top:6px;line-height:1.5}
    .vr-timeline{position:relative;margin:2px 0 14px 8px}
    .vr-timeline::before{content:"";position:absolute;left:8px;top:12px;bottom:12px;width:1px;background:#9ed7ae}
    .vr-timeline-item{position:relative;display:grid;grid-template-columns:32px 80px 1fr;gap:8px;align-items:flex-start;min-height:48px}
    .vr-timeline-dot{position:relative;z-index:2;width:17px;height:17px;border-radius:50%;background:#fff;border:2px solid #20ae51;display:grid;place-items:center;color:#20ae51;margin-top:1px}
    .vr-timeline-dot.red{border-color:#ef4444;color:#ef4444}.vr-timeline-dot.blue{border-color:#2563eb;color:#2563eb}
    .vr-timeline-dot svg{width:10px;height:10px;stroke-width:3}
    .vr-time{font-size:9px;color:#7890bb;font-weight:700;line-height:1.5}
    .vr-event strong{display:block;color:#284779;font-size:10.5px;font-weight:800;line-height:1.3}
    .vr-event p{margin:3px 0 0;color:#778aaf;font-size:9.5px;line-height:1.45;font-weight:550}
    .vr-full-link{height:38px;border:1px solid #9cb6f0;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#1461ff;font-size:10.5px;font-weight:800;margin-top:8px}

    .vr-doc-list{display:grid;margin-top:13px;border:1px solid #e4eaf4;border-radius:7px;overflow:hidden}
    .vr-doc-item{min-height:49px;padding:7px 10px;display:grid;grid-template-columns:31px 1fr auto;gap:9px;align-items:center;border-bottom:1px solid #edf1f7}
    .vr-doc-item:last-child{border-bottom:0}
    .vr-doc-icon{width:28px;height:28px;border-radius:7px;background:#f1f5ff;color:#1b5cef;display:grid;place-items:center}
    .vr-doc-icon svg{width:16px;height:16px}
    .vr-doc-copy strong{display:block;color:#1856d2;font-size:10.5px;font-weight:750;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .vr-doc-copy span{display:block;color:#8192b2;font-size:9px;font-weight:650;margin-top:3px}
    .vr-doc-link{display:inline-flex;align-items:center;gap:5px;color:#145fff;font-size:9.5px;font-weight:750;padding:4px}
    .vr-doc-link svg{width:13px;height:13px}
    .vr-no-doc{padding:34px 15px;text-align:center;color:#8b9aba;font-size:10.5px}

    .vr-support{min-height:88px;border:1px solid #9ab7ff;background:linear-gradient(90deg,#f4f8ff 0%,#fff 52%,#f8fbff 100%);border-radius:8px;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:20px}
    .vr-support-copy{display:flex;align-items:center;gap:15px}
    .vr-support-icon{width:50px;height:50px;border-radius:50%;background:#e8f0ff;color:#1461ff;display:grid;place-items:center;flex:0 0 auto}
    .vr-support-icon svg{width:25px;height:25px}
    .vr-support h3{margin:0;color:var(--vr-navy);font-size:15px;font-weight:850}
    .vr-support p{margin:5px 0 0;color:#7285ac;font-size:10.5px;font-weight:600}

    .vr-modal-backdrop{position:fixed;inset:0;background:rgba(7,24,64,.46);backdrop-filter:blur(3px);z-index:9998;display:none;align-items:center;justify-content:center;padding:20px}
    .vr-modal-backdrop.open{display:flex}
    .vr-modal{width:min(620px,100%);background:#fff;border-radius:14px;box-shadow:0 24px 80px rgba(8,32,90,.26);overflow:hidden}
    .vr-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:20px 22px;border-bottom:1px solid var(--vr-line)}
    .vr-modal-head h2{margin:0;color:var(--vr-navy);font-size:20px}.vr-modal-head p{margin:5px 0 0;color:#7a8dad;font-size:11px}
    .vr-modal-close{width:34px;height:34px;border:1px solid #dfe6f2;border-radius:7px;background:#fff;display:grid;place-items:center;color:#60759f;cursor:pointer}
    .vr-modal-close svg{width:17px;height:17px}
    .vr-modal-body{padding:20px 22px}
    .vr-modal-summary{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
    .vr-modal-summary div{border:1px solid #e5ebf5;background:#fafcff;border-radius:8px;padding:11px}
    .vr-modal-summary small{display:block;color:#8191b0;font-size:9px;font-weight:700}.vr-modal-summary strong{display:block;color:#21457f;font-size:11px;margin-top:4px}
    .vr-modal-body label{display:block;color:#375486;font-size:11px;font-weight:800;margin-bottom:7px}
    .vr-modal-body textarea{width:100%;min-height:105px;resize:vertical;border:1px solid #d8e0ee;border-radius:8px;padding:12px;color:#284779;font:inherit;font-size:12px;outline:none}
    .vr-modal-body textarea:focus{border-color:#1461ff;box-shadow:0 0 0 3px rgba(20,97,255,.1)}
    .vr-modal-hint{color:#8595b2;font-size:9.5px;line-height:1.45;margin-top:6px}
    .vr-modal-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:15px 22px;border-top:1px solid var(--vr-line);background:#fbfcff}
    .vr-modal-action{height:40px;border-radius:7px;padding:0 16px;font-weight:800;font-size:11px;cursor:pointer}
    .vr-modal-action.accept{border:1px solid #14a24a;background:#14a24a;color:#fff}.vr-modal-action.reject{border:1px solid #ef4444;background:#fff;color:#e33e49}.vr-modal-action.cancel{border:1px solid #d8e0ee;background:#fff;color:#60759f}

    
    @media (max-width: 1280px) {
        .vr-table th{font-size:8.2px;padding-left:5px;padding-right:5px}
        .vr-table td{font-size:9.4px;padding-left:5px;padding-right:5px}
        .vr-status{font-size:8.2px;padding-left:5px;padding-right:5px}
        .vr-action-btn{font-size:9px}
    }
@media (max-width:1300px){
        .vr-mini-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}
        .vr-filters{grid-template-columns:repeat(4,minmax(0,1fr))}
        .vr-field.search{grid-column:span 2}
    }
    @media (max-width:1050px){
        .vr-top-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
        .vr-overview-grid,.vr-bottom-grid{grid-template-columns:1fr}
    }
    @media (max-width:760px){
        .vr-heading-row{flex-direction:column}.vr-heading-actions{justify-content:flex-start}
        .vr-heading h1{font-size:25px}
        .vr-top-stats{grid-template-columns:1fr}
        .vr-mini-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
        .vr-filters{grid-template-columns:1fr 1fr}.vr-field.search{grid-column:1/-1}
        .vr-table-footer,.vr-support{align-items:flex-start;flex-direction:column}
        .vr-timeline-item{grid-template-columns:28px 68px 1fr}
    }
    @media (max-width:500px){.vr-mini-metrics,.vr-filters{grid-template-columns:1fr}.vr-field.search{grid-column:auto}.vr-modal-summary{grid-template-columns:1fr}}
</style>
@endsection

@section('content')
@php
    $money = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' FCFA';
    $statusLabels = [
        'pending' => 'En attente',
        'accepted' => 'Accepté',
        'rejected' => 'Refusé',
        'closed' => 'Clôturé',
        'refunded' => 'Remboursé',
        'resolved' => 'Résolu',
    ];
    $typeLabels = [
        'return' => 'Retour produit',
        'claim' => 'Réclamation',
        'refund' => 'Remboursement',
    ];
    $returnRef = function ($return) {
        $date = \Carbon\Carbon::parse($return->request_date ?: $return->created_at ?: now())->format('Ymd');
        return 'RET-' . $date . '-' . str_pad((string) $return->id, 4, '0', STR_PAD_LEFT);
    };
    $policyRoute = \Illuminate\Support\Facades\Route::has('vendor.cgu') ? route('vendor.cgu') : '#';
    $exportUrl = route('vendor.returns.index', array_filter(array_merge(request()->except('page'), ['export' => 'csv']), fn ($v) => $v !== null && $v !== ''));
@endphp

<div class="vr-page">
    <nav class="vr-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route('vendor.orders') }}">Commandes</a>
        <i data-lucide="chevron-right"></i>
        <span>Retours et remboursements</span>
    </nav>

    <div class="vr-heading-row">
        <div class="vr-heading">
            <h1>Retours et remboursements</h1>
            <p>Suivez les demandes de retour, les remboursements et les actions à traiter.</p>
        </div>
        <div class="vr-heading-actions">
            <a href="{{ $exportUrl }}" class="vr-outline-btn">
                <i data-lucide="archive"></i>
                Exporter CSV
            </a>
            <a href="{{ $policyRoute }}" class="vr-outline-btn">
                <i data-lucide="shield-check"></i>
                Politique de retour
            </a>
        </div>
    </div>

    <section class="vr-top-stats" aria-label="Indicateurs principaux">
        <article class="vr-stat-card">
            <span class="vr-stat-icon blue"><i data-lucide="wallet-cards"></i></span>
            <div class="vr-stat-copy"><small>Demandes ouvertes</small><strong>{{ $stats['open'] }}</strong><span>En attente d’action</span></div>
        </article>
        <article class="vr-stat-card">
            <span class="vr-stat-icon orange"><i data-lucide="clock-3"></i></span>
            <div class="vr-stat-copy"><small>À valider</small><strong>{{ $stats['to_validate'] }}</strong><span>En attente de validation</span></div>
        </article>
        <article class="vr-stat-card">
            <span class="vr-stat-icon green"><i data-lucide="circle-check-big"></i></span>
            <div class="vr-stat-copy"><small>Remboursés</small><strong>{{ $stats['refunded'] }}</strong><span>Sur l’ensemble des demandes</span></div>
        </article>
        <article class="vr-stat-card">
            <span class="vr-stat-icon red"><i data-lucide="circle-x"></i></span>
            <div class="vr-stat-copy"><small>Refusés</small><strong>{{ $stats['rejected'] }}</strong><span>Demandes refusées</span></div>
        </article>
    </section>

    <section class="vr-overview-grid">
        <article class="vr-panel vr-chart-panel">
            <div class="vr-chart-head">
                <div>
                    <h2 class="vr-panel-title">Évolution des retours — 30 derniers jours <i data-lucide="info"></i></h2>
                    <div class="vr-chart-total-label">Total des demandes de retour</div>
                    <div class="vr-chart-total-line">
                        <strong>{{ $chart['total'] }}</strong>
                        <span class="vr-change {{ $chart['change_percent'] < 0 ? 'negative' : '' }}">
                            {{ $chart['change_percent'] >= 0 ? '+' : '' }}{{ number_format($chart['change_percent'], 1, ',', ' ') }}%
                        </span>
                        <span class="vr-compare">vs période précédente</span>
                    </div>
                </div>
                <select class="vr-period-select" aria-label="Période du graphique">
                    <option>30 derniers jours</option>
                </select>
            </div>
            <div class="vr-chart-wrap">
                <canvas id="returnEvolutionChart" aria-label="Évolution des demandes de retour sur 30 jours"></canvas>
            </div>
        </article>

        <article class="vr-panel vr-policy">
            <div class="vr-policy-head">
                <h2 class="vr-panel-title">Politique actuelle</h2>
                @if($policy['active'])<span class="vr-active-pill">Actif</span>@endif
            </div>
            <div class="vr-policy-list">
                <div class="vr-policy-row"><span>Délai de retour</span><strong>{{ $policy['window_days'] }} jours après réception</strong></div>
                <div class="vr-policy-row"><span>Type de remboursement</span><strong>{{ $policy['refund_method'] }}</strong></div>
                <div class="vr-policy-row"><span>Service après-vente</span><strong class="green">{{ $policy['after_sales'] ? 'Actif' : 'Inactif' }}</strong></div>
                <div class="vr-policy-row"><span>Frais de retour</span><strong>{{ $policy['return_fee_payer'] }}</strong></div>
            </div>
            <a href="{{ $policyRoute }}" class="vr-policy-link">Voir la politique complète <i data-lucide="arrow-right"></i></a>
        </article>
    </section>

    <section class="vr-mini-metrics" aria-label="Indicateurs secondaires">
        <article class="vr-mini-card"><span class="vr-mini-icon"><i data-lucide="chart-no-axes-combined"></i></span><div class="vr-mini-copy"><small>Montant remboursé</small><strong>{{ $money($stats['refunded_amount']) }}</strong></div></article>
        <article class="vr-mini-card"><span class="vr-mini-icon green"><i data-lucide="shopping-bag"></i></span><div class="vr-mini-copy"><small>Articles retournés</small><strong>{{ $stats['returned_items'] }}</strong></div></article>
        <article class="vr-mini-card"><span class="vr-mini-icon orange"><i data-lucide="scale"></i></span><div class="vr-mini-copy"><small>Litiges liés</small><strong>{{ $stats['linked_disputes'] }}</strong></div></article>
        <article class="vr-mini-card"><span class="vr-mini-icon purple"><i data-lucide="scan-search"></i></span><div class="vr-mini-copy"><small>En inspection</small><strong>{{ $stats['in_inspection'] }}</strong></div></article>
        <article class="vr-mini-card"><span class="vr-mini-icon green"><i data-lucide="circle-check"></i></span><div class="vr-mini-copy"><small>Retours acceptés</small><strong>{{ $stats['accepted'] }}</strong></div></article>
        <article class="vr-mini-card"><span class="vr-mini-icon red"><i data-lucide="circle-x"></i></span><div class="vr-mini-copy"><small>Retours refusés</small><strong>{{ $stats['rejected'] }}</strong></div></article>
    </section>

    <section class="vr-panel vr-list-panel">
        <form method="GET" action="{{ route('vendor.returns.index') }}" class="vr-filters">
            <div class="vr-field search">
                <label for="vr-q">Recherche</label>
                <div class="vr-input-wrap"><i data-lucide="search"></i><input id="vr-q" name="q" value="{{ $filters['q'] }}" placeholder="Rechercher par référence, commande, client..."></div>
            </div>
            <div class="vr-field">
                <label for="vr-status">Statut</label>
                <div class="vr-input-wrap"><select id="vr-status" name="status"><option value="">Tous les statuts</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="vr-field">
                <label for="vr-type">Motif</label>
                <div class="vr-input-wrap"><select id="vr-type" name="type"><option value="">Tous les motifs</option>@foreach($typeLabels as $value => $label)<option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="vr-field">
                <label for="vr-from">Du</label>
                <div class="vr-input-wrap"><input id="vr-from" type="date" name="from" value="{{ $filters['from'] }}"></div>
            </div>
            <div class="vr-field">
                <label for="vr-to">Au</label>
                <div class="vr-input-wrap"><input id="vr-to" type="date" name="to" value="{{ $filters['to'] }}"></div>
            </div>
            <button class="vr-filter-btn" type="submit"><i data-lucide="filter"></i> Filtrer</button>
            <a href="{{ route('vendor.returns.index') }}" class="vr-reset">Réinitialiser</a>
        </form>

        <div class="vr-table-wrap">
            <table class="vr-table">
                <colgroup>
                    <col style="width:7%">
                    <col style="width:11%">
                    <col style="width:13%">
                    <col style="width:10%">
                    <col style="width:13%">
                    <col style="width:13%">
                    <col style="width:9%">
                    <col style="width:9%">
                    <col style="width:7%">
                    <col style="width:8%">
                </colgroup>
                <thead><tr><th>Date</th><th>Référence</th><th>Commande</th><th>Client</th><th>Produit</th><th>Motif</th><th>Montant</th><th>Statut</th><th>Décision</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($returns as $return)
                    @php
                        $displayStatus = $return->status ?: 'pending';
                        if ($displayStatus === 'accepted' && in_array($return->logistics_status, ['return_received', 'claim_review', 'refund_review'], true)) $visualStatus = 'inspection'; else $visualStatus = $displayStatus;
                        $amount = (float) ($return->refund_amount ?? 0);
                        if ($amount <= 0 && $return->orderItem) $amount = (float) ($return->orderItem->price ?? 0) * max(1, (int) ($return->quantity ?? 1));
                        $vendorDecisionType = (string) data_get($return->meta, 'vendor_decision.type', '');
                        $decision = match($vendorDecisionType) {'refund' => 'Remboursement décidé', 'reject' => 'Refusée', 'accept' => 'Acceptée', default => match($displayStatus) {'accepted' => 'Acceptée', 'refunded' => 'Remboursée', 'rejected' => 'Refusée', 'closed', 'resolved' => 'Clôturée', default => '—'}};
                        $selectedUrl = route('vendor.returns.index', array_filter(array_merge(request()->except(['page','selected','export']), ['selected' => $return->id]), fn($v) => $v !== null && $v !== '')) . '#dossier-recent';
                    @endphp
                    <tr>
                        <td>{{ optional($return->request_date ?: $return->created_at)->format('d/m/Y') }}</td>
                        <td><span class="vr-ref">{{ $returnRef($return) }}</span></td>
                        <td>@if($return->order_id)<a class="vr-order-link" href="{{ route('vendor.orders.show', $return->order_id) }}">{{ $return->order?->order_number ?? $return->order_reference ?? ('CMD-'.$return->order_id) }}</a>@else<span>{{ $return->order_reference ?: '—' }}</span>@endif</td>
                        <td><span class="vr-client">{{ $return->client?->name ?? 'Client OVANIE' }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($return->orderItem?->product?->name ?? $return->product_name ?? 'Produit', 28) }}</td>
                        <td><span class="vr-reason" title="{{ $return->reason }}">{{ \Illuminate\Support\Str::limit($return->reason ?: ($typeLabels[$return->return_type] ?? 'Retour produit'), 28) }}</span></td>
                        <td><span class="vr-money">{{ $money($amount) }}</span></td>
                        <td><span class="vr-status {{ $visualStatus }}">{{ $visualStatus === 'inspection' ? 'En inspection' : ($statusLabels[$displayStatus] ?? ucfirst($displayStatus)) }}</span></td>
                        <td><span class="vr-decision">{{ $decision }}</span></td>
                        <td>
                            @if($displayStatus === 'pending')
                                <button type="button" class="vr-action-btn js-open-return-modal"
                                    data-id="{{ $return->id }}"
                                    data-ref="{{ $returnRef($return) }}"
                                    data-order="{{ $return->order?->order_number ?? $return->order_reference ?? '—' }}"
                                    data-product="{{ $return->orderItem?->product?->name ?? $return->product_name ?? 'Produit' }}"
                                    data-client="{{ $return->client?->name ?? 'Client OVANIE' }}"
                                    data-accept-url="{{ route('vendor.returns.accept', $return->id) }}"
                                    data-reject-url="{{ route('vendor.returns.reject', $return->id) }}"
                                    data-refund-url="{{ route('vendor.returns.refund', $return->id) }}">
                                    Traiter <i data-lucide="chevron-right"></i>
                                </button>
                            @else
                                <a href="{{ $selectedUrl }}" class="vr-action-btn">Voir <i data-lucide="chevron-right"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="vr-empty"><i data-lucide="inbox"></i><strong>Aucune demande trouvée.</strong><div>Aucun retour ne correspond aux critères sélectionnés.</div></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="vr-table-footer">
            <span>Affichage de {{ $returns->firstItem() ?? 0 }} à {{ $returns->lastItem() ?? 0 }} sur {{ $returns->total() }} demande{{ $returns->total() > 1 ? 's' : '' }}</span>
            <div style="display:flex;align-items:center;gap:14px">
                <div class="vr-pagination">
                    <a class="vr-page-btn {{ $returns->onFirstPage() ? 'disabled' : '' }}" href="{{ $returns->previousPageUrl() ?: '#' }}"><i data-lucide="chevron-left"></i></a>
                    @foreach($returns->getUrlRange(max(1,$returns->currentPage()-1), min($returns->lastPage(),$returns->currentPage()+1)) as $page => $url)
                        <a class="vr-page-btn {{ $page === $returns->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $page }}</a>
                    @endforeach
                    <a class="vr-page-btn {{ $returns->hasMorePages() ? '' : 'disabled' }}" href="{{ $returns->nextPageUrl() ?: '#' }}"><i data-lucide="chevron-right"></i></a>
                </div>
                <form method="GET" action="{{ route('vendor.returns.index') }}">
                    @foreach(request()->except(['page','per_page']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                    <select class="vr-per-page" name="per_page" onchange="this.form.submit()"><option value="10" @selected($returns->perPage()===10)>10 / page</option><option value="20" @selected($returns->perPage()===20)>20 / page</option><option value="50" @selected($returns->perPage()===50)>50 / page</option></select>
                </form>
            </div>
        </div>
    </section>

    <section class="vr-bottom-grid" id="dossier-recent">
        <article class="vr-panel vr-history">
            <div class="vr-case-head">
                <div>
                    <h2 class="vr-panel-title">Historique d’un dossier récent</h2>
                    @if($recentCase)<div class="vr-case-meta">Référence : {{ $returnRef($recentCase) }} &nbsp;•&nbsp; Commande : {{ $recentCase->order?->order_number ?? $recentCase->order_reference ?? '—' }}</div>@endif
                </div>
                @if($recentCase)<span class="vr-status {{ $recentCase->status }}">{{ $statusLabels[$recentCase->status] ?? ucfirst((string)$recentCase->status) }}</span>@endif
            </div>
            @if($recentCase && count($timeline))
                <div class="vr-timeline">
                    @foreach($timeline as $event)
                        <div class="vr-timeline-item">
                            <span class="vr-timeline-dot {{ $event['tone'] ?? 'green' }}"><i data-lucide="check"></i></span>
                            <span class="vr-time">{{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y') }}<br>à {{ \Carbon\Carbon::parse($event['date'])->format('H:i') }}</span>
                            <div class="vr-event"><strong>{{ $event['title'] }}</strong><p>{{ $event['message'] }}</p></div>
                        </div>
                    @endforeach
                </div>
                @if($recentCase->order_id)<a class="vr-full-link" href="{{ route('vendor.orders.show', $recentCase->order_id) }}">Voir le détail de la commande</a>@endif
            @else
                <div class="vr-no-doc">Aucun historique de retour disponible pour le moment.</div>
            @endif
        </article>

        <article class="vr-panel vr-documents">
            <div class="vr-case-head"><h2 class="vr-panel-title">Documents &amp; preuves</h2></div>
            @if(count($documents))
                <div class="vr-doc-list">
                    @foreach($documents as $documentIndex => $document)
                        <div class="vr-doc-item">
                            <span class="vr-doc-icon"><i data-lucide="{{ in_array(strtolower($document['type']), ['jpg','jpeg','png','webp','image'], true) ? 'image' : 'file-text' }}"></i></span>
                            <div class="vr-doc-copy"><strong>{{ $document['name'] }}</strong><span>{{ optional($document['date'] ? \Carbon\Carbon::parse($document['date']) : null)->format('d/m/Y') ?? '—' }} &nbsp;•&nbsp; {{ strtoupper($document['type']) }}</span></div>
                            <a class="vr-doc-link" href="{{ route('vendor.private-documents.return', [$return, $documentIndex]) }}" target="_blank" rel="noopener"><i data-lucide="eye"></i> Voir</a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="vr-doc-list"><div class="vr-no-doc">Aucun document ou preuve associé à ce dossier.</div></div>
            @endif
        </article>
    </section>

    <section class="vr-support">
        <div class="vr-support-copy"><span class="vr-support-icon"><i data-lucide="headphones"></i></span><div><h3>Besoin d’assistance ? Contactez le support OVANIE</h3><p>Notre équipe vous accompagne pour toute question liée aux retours ou remboursements.</p></div></div>
        <a href="tel:0161780000" class="vr-outline-btn"><i data-lucide="headphones"></i> Contacter le support</a>
    </section>
</div>

<div class="vr-modal-backdrop" id="returnActionModal" aria-hidden="true">
    <div class="vr-modal" role="dialog" aria-modal="true" aria-labelledby="returnModalTitle">
        <div class="vr-modal-head">
            <div><h2 id="returnModalTitle">Traiter la demande</h2><p id="returnModalReference">Sélectionnez une décision pour ce dossier.</p></div>
            <button type="button" class="vr-modal-close js-close-return-modal" aria-label="Fermer"><i data-lucide="x"></i></button>
        </div>
        <div class="vr-modal-body">
            <div class="vr-modal-summary"><div><small>Commande</small><strong id="returnModalOrder">—</strong></div><div><small>Client</small><strong id="returnModalClient">—</strong></div><div style="grid-column:1/-1"><small>Produit concerné</small><strong id="returnModalProduct">—</strong></div></div>
            <label for="returnResponse">Réponse de la boutique</label>
            <textarea id="returnResponse" maxlength="1000" placeholder="Ajoutez une réponse claire pour le client. Pour un refus, indiquez obligatoirement un motif d’au moins 10 caractères."></textarea>
            <label for="vendorRejectionProof">Preuve du rejet <small>(obligatoire uniquement si vous refusez : JPG, PNG, WEBP ou PDF, 5 Mo max.)</small></label>
            <input id="vendorRejectionProof" type="file" name="vendor_rejection_proof" accept="image/jpeg,image/png,image/webp,application/pdf" form="rejectReturnForm">
            <div class="vr-modal-hint">La décision est enregistrée dans le dossier puis transmise à OVANIE Logistics. Le client sera informé après confirmation logistique.</div>
        </div>
        <div class="vr-modal-actions">
            <button type="button" class="vr-modal-action cancel js-close-return-modal">Annuler</button>
            <form method="POST" id="rejectReturnForm" enctype="multipart/form-data">@csrf<input type="hidden" name="vendor_response" id="rejectResponse"><button class="vr-modal-action reject" type="submit">Refuser</button></form>
            <form method="POST" id="refundReturnForm">@csrf<input type="hidden" name="vendor_response" id="refundResponse"><button class="vr-modal-action" type="submit">Rembourser</button></form>
            <form method="POST" id="acceptReturnForm">@csrf<input type="hidden" name="vendor_response" id="acceptResponse"><button class="vr-modal-action accept" type="submit">Accepter le retour</button></form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) window.lucide.createIcons();

    const chartLabels = @json($chart['labels']);
    const chartValues = @json($chart['values']);
    const canvas = document.getElementById('returnEvolutionChart');

    function drawReturnChart() {
        if (!canvas) return;
        const parent = canvas.parentElement;
        const dpr = Math.max(1, window.devicePixelRatio || 1);
        const width = Math.max(320, parent.clientWidth);
        const height = Math.max(160, parent.clientHeight);
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const pad = {left: 36, right: 12, top: 12, bottom: 28};
        const graphW = width - pad.left - pad.right;
        const graphH = height - pad.top - pad.bottom;
        const rawMax = Math.max(1, ...chartValues);
        const maxVal = Math.max(4, Math.ceil(rawMax / 5) * 5);

        ctx.font = '10px Inter, Arial, sans-serif';
        ctx.lineWidth = 1;
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (let i = 0; i <= 4; i++) {
            const value = Math.round(maxVal * (1 - i / 4));
            const y = pad.top + graphH * i / 4;
            ctx.strokeStyle = '#e9eef7';
            ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(width - pad.right, y); ctx.stroke();
            ctx.fillStyle = '#7c8dad';
            ctx.fillText(String(value), pad.left - 8, y);
        }

        const points = chartValues.map((value, index) => ({
            x: pad.left + (chartValues.length === 1 ? graphW / 2 : graphW * index / (chartValues.length - 1)),
            y: pad.top + graphH - ((Number(value) || 0) / maxVal) * graphH,
        }));

        if (points.length) {
            const gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + graphH);
            gradient.addColorStop(0, 'rgba(22,93,255,.20)');
            gradient.addColorStop(1, 'rgba(22,93,255,.01)');
            ctx.beginPath(); ctx.moveTo(points[0].x, pad.top + graphH);
            points.forEach((p, i) => i === 0 ? ctx.lineTo(p.x,p.y) : ctx.lineTo(p.x,p.y));
            ctx.lineTo(points[points.length - 1].x, pad.top + graphH); ctx.closePath();
            ctx.fillStyle = gradient; ctx.fill();

            ctx.beginPath();
            points.forEach((p, i) => i === 0 ? ctx.moveTo(p.x,p.y) : ctx.lineTo(p.x,p.y));
            ctx.strokeStyle = '#1660ff'; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.lineCap = 'round'; ctx.stroke();

            points.forEach(p => {ctx.beginPath();ctx.arc(p.x,p.y,3.1,0,Math.PI*2);ctx.fillStyle='#1660ff';ctx.fill();ctx.beginPath();ctx.arc(p.x,p.y,1.45,0,Math.PI*2);ctx.fillStyle='#fff';ctx.fill();});
        }

        const ticks = [0, 7, 14, 21, 29].filter(i => i < chartLabels.length);
        ctx.textAlign = 'center'; ctx.textBaseline = 'top'; ctx.fillStyle = '#7c8dad';
        ticks.forEach(index => { const x = pad.left + graphW * index / Math.max(1, chartLabels.length - 1); ctx.fillText(chartLabels[index], x, pad.top + graphH + 10); });
    }

    drawReturnChart();
    let chartResizeTimer;
    window.addEventListener('resize', () => { clearTimeout(chartResizeTimer); chartResizeTimer = setTimeout(drawReturnChart, 100); });

    const modal = document.getElementById('returnActionModal');
    const response = document.getElementById('returnResponse');
    const acceptForm = document.getElementById('acceptReturnForm');
    const rejectForm = document.getElementById('rejectReturnForm');
    const refundForm = document.getElementById('refundReturnForm');
    const rejectionProof = document.getElementById('vendorRejectionProof');

    function openModal(button) {
        document.getElementById('returnModalReference').textContent = button.dataset.ref || 'Dossier retour';
        document.getElementById('returnModalOrder').textContent = button.dataset.order || '—';
        document.getElementById('returnModalClient').textContent = button.dataset.client || '—';
        document.getElementById('returnModalProduct').textContent = button.dataset.product || '—';
        acceptForm.action = button.dataset.acceptUrl;
        rejectForm.action = button.dataset.rejectUrl;
        refundForm.action = button.dataset.refundUrl;
        response.value = '';
        if (rejectionProof) rejectionProof.value = '';
        modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
        setTimeout(() => response.focus(), 60);
    }
    function closeModal() { modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }

    document.querySelectorAll('.js-open-return-modal').forEach(button => button.addEventListener('click', () => openModal(button)));
    document.querySelectorAll('.js-close-return-modal').forEach(button => button.addEventListener('click', closeModal));
    modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('open')) closeModal(); });

    acceptForm?.addEventListener('submit', function () { document.getElementById('acceptResponse').value = response.value.trim(); });
    refundForm?.addEventListener('submit', function () { document.getElementById('refundResponse').value = response.value.trim(); });
    rejectForm?.addEventListener('submit', function (event) {
        const value = response.value.trim();
        if (value.length < 10) {
            event.preventDefault();
            response.setCustomValidity('Indiquez un motif de refus d’au moins 10 caractères.');
            response.reportValidity();
            response.focus();
            setTimeout(() => response.setCustomValidity(''), 100);
            return;
        }
        if (!rejectionProof?.files?.length) {
            event.preventDefault();
            rejectionProof?.setCustomValidity('Ajoutez une preuve avant de refuser le retour.');
            rejectionProof?.reportValidity();
            setTimeout(() => rejectionProof?.setCustomValidity(''), 100);
            return;
        }
        document.getElementById('rejectResponse').value = value;
    });
});
</script>
@endsection
