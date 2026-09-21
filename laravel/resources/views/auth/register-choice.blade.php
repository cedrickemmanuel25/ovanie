@extends('layouts.auth-clean')

@section('title', 'Choisir votre profil')

@section('styles')
<style>
    .auth-card-wrapper {
        max-width: 760px !important;
    }
</style>
@endsection

@section('content')
<div class="bg-white rounded-2xl shadow-lg p-5 lg:p-6 w-full">
    <div class="text-center mb-5">
        <a href="{{ route('home') }}">
            <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE Logo" class="h-10 mx-auto mb-2">
        </a>
        <h1 class="font-bold text-2xl text-[#1E3A5F] mb-1 leading-tight">Rejoindre OVANIE</h1>
        <p class="text-gray-500 text-sm">Choisissez un identifiant Client ou un identifiant Vendeur. Les deux comptes restent séparés.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('register.client') }}" class="group rounded-2xl border-2 border-blue-100 bg-blue-50/60 p-5 hover:border-[#2563EB] hover:bg-blue-50 transition-all">
            <div class="w-12 h-12 rounded-2xl bg-[#2563EB] text-white flex items-center justify-center mb-4 shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11c1.657 0 3-1.567 3-3.5S17.657 4 16 4s-3 1.567-3 3.5S14.343 11 16 11ZM8 11c1.657 0 3-1.567 3-3.5S9.657 4 8 4 5 5.567 5 7.5 6.343 11 8 11ZM2 20c.7-2.6 2.7-4 6-4s5.3 1.4 6 4M10 20c.7-2.6 2.7-4 6-4s5.3 1.4 6 4"/>
                </svg>
            </div>
            <h2 class="text-lg font-black text-slate-900 mb-1">Client / Acheteur</h2>
            <p class="text-sm text-slate-600 leading-relaxed">Créer uniquement un compte client pour acheter, payer et suivre vos commandes.</p>
            <span class="mt-4 inline-flex items-center text-sm font-black text-[#2563EB]">
                Creer mon compte
                <svg class="w-4 h-4 ml-1 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        </a>

        <a href="{{ route('open-shop') }}" class="group rounded-2xl border-2 border-orange-100 bg-orange-50/60 p-5 hover:border-[#E87020] hover:bg-orange-50 transition-all">
            <div class="w-12 h-12 rounded-2xl bg-[#E87020] text-white flex items-center justify-center mb-4 shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M4 9h16v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 13h8M8 17h5"/>
                </svg>
            </div>
            <h2 class="text-lg font-black text-slate-900 mb-1">Fournisseur / Vendeur</h2>
            <p class="text-sm text-slate-600 leading-relaxed">Créer uniquement un compte vendeur. Cet identifiant vendeur peut aussi acheter sans créer de compte client.</p>
            <span class="mt-4 inline-flex items-center text-sm font-black text-[#E87020]">
                Créer mon compte vendeur
                <svg class="w-4 h-4 ml-1 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        </a>
    </div>

    <div class="mt-5 text-center">
        <p class="text-gray-500 text-xs">
            Deja un compte ?
            <a href="{{ route('login') }}" class="text-[#2563EB] font-bold hover:underline">Connectez-vous</a>
        </p>
    </div>
</div>
@endsection
