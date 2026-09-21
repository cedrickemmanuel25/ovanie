@extends('admin.layouts.app')

@section('title', 'Validation boutiques - Admin IMOo')
@section('page-title', 'Validation des boutiques vendeurs')

@section('content')

@if(session('success'))
  <div class="alert-success">{{ session('success') }}</div>
@endif

<table>
  <thead>
    <tr>
      <th>Boutique</th>
      <th>Vendeur</th>
      <th>Pièce d’identité</th>
      <th>Statut</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    @forelse($vendors as $vendor)
    <tr>
      <td>{{ $vendor->shop_name }}</td>
      <td>{{ $vendor->owner_name }}</td>
      <td>
        @if($vendor->identity_document_url)
          <a href="{{ $vendor->identity_document_url }}" target="_blank">Voir document</a>
        @else
          -
        @endif
      </td>
      <td>
        @if($vendor->status === 'pending')
          <span class="pending">En attente</span>
        @elseif($vendor->status === 'approved')
          <span class="approved">Validée</span>
        @elseif($vendor->status === 'rejected')
          <span class="rejected">Refusée</span>
        @else
          {{ $vendor->status }}
        @endif
      </td>
      <td>
        @if($vendor->status === 'pending')
          <form method="POST" action="{{ route('admin.vendor_validation.approve', $vendor->id) }}" style="display:inline">
            @csrf
            <button type="submit">Valider</button>
          </form>

          <form method="POST" action="{{ route('admin.vendor_validation.reject', $vendor->id) }}" style="display:inline">
            @csrf
            <button type="submit" class="danger">Refuser</button>
          </form>
        @else
          <em>Aucune action disponible</em>
        @endif
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="5">Aucune boutique en attente de validation.</td>
    </tr>
    @endforelse
  </tbody>
</table>

@endsection

@push('scripts')
<script src="{{ asset('admin/js/vendor_validation.js') }}"></script>
@endpush
