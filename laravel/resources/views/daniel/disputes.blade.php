@extends('layouts.vendor')

@section('title', 'Litiges')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/vendor_base.css') }}">
<link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
@endsection

@section('content')
<main class="main">
  <h1>Litiges clients</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @forelse($disputes as $dispute)
  <div class="dispute-card">
    <h3>Commande {{ $dispute->order_reference }}</h3>
    <p><strong>Client :</strong> {{ $dispute->client_name }}</p>
    <p><strong>Motif :</strong> {{ $dispute->reason }}</p>

    <form action="{{ route('vendor.disputes.respond', $dispute->id) }}" method="POST">
      @csrf
      <textarea name="response" placeholder="Votre réponse au client...">{{ old('response', $dispute->response) }}</textarea>
      @error('response')
        <div class="error">{{ $message }}</div>
      @enderror

      <div class="actions">
        <button type="submit">Répondre</button>
      </div>
    </form>

    <form action="{{ route('vendor.disputes.escalate', $dispute->id) }}" method="POST" style="margin-top:10px;">
      @csrf
      <button type="submit" class="danger" onclick="return confirm('Confirmez-vous l\'escalade du litige à l’administration ?')">Escalader à l’admin</button>
    </form>
  </div>
  @empty
  <p>Aucun litige pour le moment.</p>
  @endforelse
</main>
@endsection

@section('scripts')
<script src="{{ asset('js/auth-bootstrap.js') }}"></script>
<script src="{{ asset('js/vendor_auth.js') }}"></script>
<script src="{{ asset('js/vendor_navigation.js') }}"></script>
<script src="{{ asset('js/disputes.js') }}"></script>
@endsection
