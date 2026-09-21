<style>
    .seller-dashboard-page {
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .seller-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 2.08fr) minmax(330px, .92fr);
        gap: 18px;
        align-items: stretch;
        min-width: 0;
    }

    .seller-hero-card {
        min-height: 250px;
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        background:
            linear-gradient(90deg, rgba(5,18,45,.98) 0%, rgba(8,33,86,.92) 48%, rgba(22,83,189,.85) 100%),
            url('https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1600&q=80') center/cover;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .12);
        color: #fff;
    }

    .seller-hero-overlay {
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 92% 90%, rgba(59,130,246,.55), transparent 34%),
            linear-gradient(90deg, rgba(7,23,55,.95), rgba(7,23,55,.42));
    }

    .seller-hero-content {
        position: relative;
        z-index: 2;
        padding: 34px 40px;
        max-width: 850px;
    }

    .seller-chip {
        width: fit-content;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        border: 1px solid rgba(255,255,255,.22);
        background: rgba(255,255,255,.08);
        color: #dbeafe;
        border-radius: 999px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 900;
        letter-spacing: .13em;
    }

    .seller-chip svg { width: 20px; height: 20px; }

    .seller-hero-content h1 {
        margin: 28px 0 8px;
        font-size: clamp(34px, 4vw, 54px);
        line-height: .95;
        font-weight: 950;
        letter-spacing: -.05em;
    }

    .seller-hero-content p {
        margin: 0;
        max-width: 820px;
        color: #e2e8f0;
        font-size: 20px;
        line-height: 1.5;
        font-weight: 650;
    }

    .seller-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 30px;
    }

    .btn {
        min-height: 56px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border-radius: 12px;
        padding: 0 24px;
        font-size: 16px;
        font-weight: 950;
        transition: .2s ease;
        border: 1px solid transparent;
    }

    .btn svg { width: 23px; height: 23px; stroke-width: 2.4; }
    .btn-primary { background: #2563eb; color: #fff; box-shadow: 0 12px 28px rgba(37,99,235,.35); }
    .btn-light { background: #fff; color: #071737; }
    .btn-ghost { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.2); color: #fff; }
    .btn:hover { transform: translateY(-1px); }

    .seller-shop-card {
        padding: 26px;
        color: var(--ov-text);
    }

    .seller-shop-head {
        display: flex;
        gap: 16px;
        align-items: center;
        margin-bottom: 24px;
    }

    .seller-shop-icon {
        width: 62px;
        height: 62px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: #eff6ff;
        color: #2563eb;
    }

    .seller-shop-icon svg { width: 32px; height: 32px; }
    .seller-shop-head h2 { margin: 0; font-size: 23px; font-weight: 950; letter-spacing: -.03em; }
    .seller-shop-head p { margin: 5px 0 0; color: #64748b; font-weight: 700; }

    .seller-score-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .seller-score-bar {
        height: 12px;
        border-radius: 999px;
        background: #eaf0f8;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .seller-score-bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #2563eb, #22c55e);
    }

    .seller-shop-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .seller-shop-info-grid div {
        border: 1px solid var(--ov-border);
        border-radius: 14px;
        padding: 15px;
        background: #f8fafc;
        min-width: 0;
    }

    .seller-shop-info-grid small,
    .seller-stat small {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 6px;
    }

    .seller-shop-info-grid strong { display: block; font-size: 15px; line-height: 1.35; }
    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; margin-right: 7px; }

    .seller-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        min-width: 0;
    }

    .seller-stat {
        min-height: 86px;
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr);
        align-items: center;
        gap: 14px;
        padding: 18px 20px;
    }

    .seller-stat .icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
    }

    .seller-stat .icon svg { width: 26px; height: 26px; stroke-width: 2.4; }
    .seller-stat .icon.blue { background: #dbeafe; color: #2563eb; }
    .seller-stat .icon.orange { background: #ffedd5; color: #ea580c; }
    .seller-stat .icon.green { background: #dcfce7; color: #16a34a; }
    .seller-stat .icon.purple { background: #f3e8ff; color: #9333ea; }
    .seller-stat .icon.red { background: #fee2e2; color: #dc2626; }
    .seller-stat .icon.yellow { background: #fef3c7; color: #ca8a04; }

    .seller-stat strong {
        display: block;
        font-size: 27px;
        line-height: 1;
        font-weight: 950;
        letter-spacing: -.04em;
    }

    .seller-stat p { margin: 8px 0 0; color: #64748b; font-size: 14px; font-weight: 650; }

    .seller-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(360px, .95fr);
        gap: 18px;
        min-width: 0;
    }

    .chart-card,
    .finance-card,
    .list-card { padding: 22px 24px; min-width: 0; }

    .section-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 14px;
    }

    .section-head h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -.04em;
    }

    .section-head p { margin: 5px 0 0; color: #64748b; font-weight: 650; }
    .section-head a { color: #2563eb; font-weight: 950; font-size: 14px; }
    .section-head > strong { color: #16a34a; font-size: 18px; font-weight: 950; white-space: nowrap; }

    .sales-chart {
        height: 188px;
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 12px;
        align-items: end;
        padding-top: 14px;
    }

    .sales-day {
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        color: #475569;
    }

    .sales-amount { font-size: 12px; font-weight: 950; color: #071737; text-align: center; min-height: 32px; }
    .sales-bar { width: 100%; height: 96px; border-bottom: 2px solid #dbeafe; display: flex; align-items: end; justify-content: center; }
    .sales-bar span { width: 100%; max-width: 82px; min-height: 8px; border-radius: 999px 999px 0 0; background: linear-gradient(180deg, #2563eb, #93c5fd); }
    .sales-day strong { font-size: 13px; }

    .finance-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .finance-grid div {
        min-height: 74px;
        border: 1px solid var(--ov-border);
        border-radius: 15px;
        background: #f8fafc;
        padding: 14px;
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        column-gap: 12px;
        align-items: center;
    }

    .finance-grid span {
        grid-row: span 2;
        width: 40px;
        height: 40px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: #eff6ff;
        color: #2563eb;
        font-weight: 950;
    }

    .finance-grid strong { font-size: 14px; font-weight: 950; }
    .finance-grid p { margin: 3px 0 0; color: #64748b; font-weight: 750; }

    .seller-lists-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr) minmax(0, .85fr) minmax(0, .85fr);
        gap: 18px;
        min-width: 0;
    }

    .list-card.wide { grid-column: span 2; }

    .orders-table-wrap { width: 100%; min-width: 0; overflow-x: auto; }
    .orders-table-wrap table { width: 100%; border-collapse: collapse; min-width: 610px; }
    .orders-table-wrap th { text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .08em; padding: 12px 10px; border-bottom: 1px solid var(--ov-border); }
    .orders-table-wrap td { padding: 13px 10px; border-bottom: 1px solid #edf2f7; font-size: 13px; font-weight: 700; color: #334155; }
    .orders-table-wrap td a { color: #2563eb; font-weight: 950; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        border-radius: 999px;
        padding: 0 10px;
        font-size: 12px;
        font-weight: 950;
        font-style: normal;
        white-space: nowrap;
    }
    .status-pill.orange { background: #ffedd5; color: #ea580c; }
    .status-pill.blue { background: #dbeafe; color: #2563eb; }
    .status-pill.green { background: #dcfce7; color: #16a34a; }
    .status-pill.red { background: #fee2e2; color: #dc2626; }
    .status-pill.purple { background: #f3e8ff; color: #9333ea; }

    .empty-state {
        min-height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        border-radius: 14px;
        color: #64748b;
        font-weight: 800;
        text-align: center;
        padding: 18px;
    }

    .mini-row,
    .activity-row {
        min-height: 43px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border-bottom: 1px solid #edf2f7;
        font-size: 13px;
        font-weight: 800;
    }
    .mini-row:last-child,
    .activity-row:last-child { border-bottom: 0; }
    .mini-row span { color: #334155; }
    .mini-row strong { color: #2563eb; white-space: nowrap; }
    .mini-row .red-text { color: #ef4444; }

    .recent-products {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .recent-product {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        border: 1px solid #edf2f7;
        border-radius: 14px;
        padding: 10px;
        min-width: 0;
    }

    .recent-product img,
    .placeholder-img {
        width: 54px;
        height: 54px;
        border-radius: 12px;
        object-fit: cover;
        background: #eff6ff;
        display: grid;
        place-items: center;
    }

    .recent-product strong { display: block; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .recent-product small { display: block; margin-top: 4px; color: #64748b; }

    .activity-row small { color: #64748b; white-space: nowrap; }

    @media (max-width: 1380px) {
        .seller-hero-grid,
        .seller-main-grid { grid-template-columns: 1fr; }
        .seller-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .seller-lists-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
        .seller-hero-content { padding: 26px; }
        .seller-hero-content h1 { font-size: 36px; }
        .seller-hero-content p { font-size: 16px; }
        .seller-hero-actions .btn { width: 100%; }
        .seller-stats-grid,
        .seller-lists-grid,
        .seller-shop-info-grid,
        .finance-grid,
        .recent-products { grid-template-columns: 1fr; }
        .list-card.wide { grid-column: span 1; }
        .sales-chart { gap: 6px; }
        .sales-amount { font-size: 10px; }
    }
</style>
