@extends('layouts.vendor')

@section('title', 'Demandes de retour')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/vendor_base.css') }}">
<link rel="stylesheet" href="{{ asset('css/returns.css') }}">
@endsection

@section('content')
<main class="main">
  <h1>Demandes de retour</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <table>
    <thead>
      <tr>
        <th>Commande</th>
        <th>Produit</th>
        <th>Motif</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse($returns as $return)
      <tr>
        <td>{{ $return->order_reference }}</td>
        <td>{{ $return->product_name }}</td>
        <td>{{ $return->reason }}</td>
        <td>{{ $return->request_date->format('d/m/Y') }}</td>
        <td>
          @if($return->status === 'pending')
            <form action="{{ route('vendor.returns.accept', $return->id) }}" method="POST" style="display:inline;">
              @csrf
              <button type="submit">Accepter</button>
            </form>
            <form action="{{ route('vendor.returns.reject', $return->id) }}" method="POST" style="display:inline;">
              @csrf
              <button type="submit" class="danger">Refuser</button>
            </form>
          @else
            <span>
              @if($return->status === 'accepted') Acceptée
              @elseif($return->status === 'rejected') Refusée
              @endif
            </span>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center">Aucune demande de retour pour le moment.</td></tr>
      @endforelse
    </tbody>
  </table>
</main>
@endsection

@section('scripts')
<script src="{{ asset('js/auth-bootstrap.js') }}"></script>
<script src="{{ asset('js/vendor_auth.js') }}"></script>
<script src="{{ asset('js/vendor_navigation.js') }}"></script>
<script src="{{ asset('js/returns.js') }}"></script>
@endsection
