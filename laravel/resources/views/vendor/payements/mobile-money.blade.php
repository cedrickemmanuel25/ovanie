<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Paiement Mobile Money</title>

  <!-- CSRF Laravel obligatoire -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <link rel="stylesheet" href="{{ asset('css/vendor_base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/mobile_money.css') }}">
</head>
<body>

<main class="payment">
  <h1>Paiement abonnement</h1>

  <form id="mobileMoneyForm">
    @csrf

    <label for="operator">Opérateur</label>
    <select id="operator" name="operator" required>
      <option value="">-- Sélectionner --</option>
      <option value="orange">Orange Money</option>
      <option value="mtn">MTN MoMo</option>
      <option value="wave">Wave</option>
    </select>

    <label for="number">Numéro Mobile Money</label>
    <input
      type="tel"
      id="number"
      name="number"
      required
      placeholder="0700000000"
    >

    <label for="amount">Montant</label>
    <input
      type="number"
      id="amount"
      name="amount"
      value="15000"
      readonly
    >

    <button type="submit">Payer</button>
  </form>

  <p id="result"></p>
</main>

<script src="{{ asset('js/auth-bootstrap.js') }}"></script>
<script src="{{ asset('js/vendor_auth.js') }}"></script>
<script src="{{ asset('js/vendor_navigation.js') }}"></script>
<script src="{{ asset('js/mobile-money.js') }}"></script>

</body>
</html>
