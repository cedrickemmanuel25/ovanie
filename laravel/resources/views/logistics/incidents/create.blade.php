@extends('layouts.logistics-operations')
@section('title','Déclarer un incident')
@section('page-class','ops-directory')
@include('logistics.directory.assets')
@section('content')<x-operations.directory-header title="Déclarer un incident" section="Incidents" :url="route('logistics.incidents.index')" subtitle="Créer un signalement pour cette mission"><button class="ops-button ops-button-primary" data-directory-open="incident-create">Déclarer un incident</button></x-operations.directory-header>
@include('logistics.incidents.form')@endsection
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('incident-create').showModal());</script>
@endpush
