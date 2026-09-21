@extends('admin.layouts.app')
@section('title', 'Nouveau collaborateur | Admin OVANIE')
@section('page-title', 'Nouveau collaborateur')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_staff.css') }}">@endpush

@section('content')
<div class="staff-page">
    <section class="staff-hero">
        <div>
            <h2>Créer un compte du personnel interne</h2>
            <p>Le collaborateur se connectera uniquement depuis <strong>/administration/login</strong>, puis sera dirigé vers la Logistique, le Support ou le Commercial selon son rôle.</p>
        </div>
    </section>

    <form class="staff-form-card" method="POST" action="{{ route('admin.staff.store') }}">
        @csrf
        @include('admin.staff._form')
    </form>
</div>
@endsection

@push('scripts')
@include('admin.staff._form-script')
@endpush
