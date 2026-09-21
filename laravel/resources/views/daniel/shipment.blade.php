@extends('layouts.vendor')

@section('title', 'Expédition commande')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/vendor_base.css') }}">
<link rel="stylesheet" href="{{ asset('css/shipment.css') }}">
@endsection

@section('content')
<main class="main">
  <h1>Expédition commande #{{ $order->reference }}</h1>

  <form id="shipmentForm" method="POST" action="{{ route('vendor.shipments.store', ['order' => $order->id]) }}">
    @csrf

    <label for="carrier">Transporteur</label>
    <select id="carrier" name="carrier" required>
      <option value="vendeur" {{ old('carrier') == 'vendeur' ? 'selected' : '' }}>Livraison prise en charge</option>
      <option value="partenaire" {{ old('carrier') == 'partenaire' ? 'selected' : '' }}>Transport partenaire</option>
    </select>
    @error('carrier')
      <div class="error">{{ $message }}</div>
    @enderror

    <label for="tracking_number">Numéro de suivi</label>
    <input type="text" id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}" required>
    @error('tracking_number')
      <div class="error">{{ $message }}</div>
    @enderror

    <label for="shipment_date">Date d’expédition</label>
    <input type="date" id="shipment_date" name="shipment_date" value="{{ old('shipment_date') ?? now()->format('Y-m-d') }}" required>
    @error('shipment_date')
      <div class="error">{{ $message }}</div>
    @enderror

    <button type="submit">Valider expédition</button>
    <button type="button" onclick="window.print()">Imprimer bon</button>
  </form>
</main>
@endsection

@section('scripts')
<script src="{{ asset('js/auth-bootstrap.js') }}"></script>
<script src="{{ asset('js/vendor_auth.js') }}"></script>
<script src="{{ asset('js/vendor_navigation.js') }}"></script>
<script src="{{ asset('js/shipment.js') }}"></script>
@endsection
