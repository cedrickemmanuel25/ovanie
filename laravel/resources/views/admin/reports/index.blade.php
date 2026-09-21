@extends('admin.layouts.app')

@section('title', 'Rapports - Admin IMOo')

@section('content')
<main class="admin-main">

  <header class="admin-header">
    <h1>Rapports d'activité</h1>
    <p>Analyse et statistiques des performances du site</p>
  </header>

  <section class="reports-overview">

    <!-- Cartes résumé -->
    <div class="report-cards">
      <div class="card">
        <h3>Ventes totales</h3>
        <p class="value">15 425</p>
        <p class="description">Nombre total de commandes</p>
      </div>
      <div class="card">
        <h3>Chiffre d'affaires</h3>
        <p class="value">12 340 000 FCFA</p>
        <p class="description">Revenus générés</p>
      </div>
      <div class="card">
        <h3>Utilisateurs actifs</h3>
        <p class="value">4 120</p>
        <p class="description">Utilisateurs connectés ce mois</p>
      </div>
      <div class="card">
        <h3>Produits en stock</h3>
        <p class="value">2 580</p>
        <p class="description">Articles disponibles</p>
      </div>
    </div>

    <!-- Graphiques / analyses -->
    <div class="charts-section">
      <div class="chart-card">
        <h3>Ventes par catégorie</h3>
        <canvas id="salesCategoryChart"></canvas>
      </div>
      <div class="chart-card">
        <h3>Trafic mensuel</h3>
        <canvas id="monthlyTrafficChart"></canvas>
      </div>
    </div>

  </section>

</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_reports.css') }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('admin/js/reports.js') }}"></script>
@endpush
