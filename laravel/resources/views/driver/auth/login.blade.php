@extends('layouts.auth-clean')

@section('title', 'Connexion Livreur')

@section('content')
<div
    class="w-full rounded-2xl bg-white p-5 shadow-xl ring-1 ring-slate-200/70 lg:p-6"
    
>
    {{-- Logo --}}
    <div class="mb-4 text-center">
        <img
            src="{{ asset('images/home/logo-ovanie.png') }}"
            alt="OVANIE"
            class="mx-auto h-12 w-auto object-contain"
        >
    </div>

    {{-- En-tête --}}
    <div class="mb-5 text-center">
        <h1 class="mb-1 text-2xl font-bold leading-tight text-[#1E3A5F]">
            Espace Livreur
        </h1>
        <p class="text-sm text-slate-500">
            Vos missions et vos gains se gèrent dans l’application OVANIE Livreur.
        </p>
    </div>

    {{-- Erreurs --}}
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-3 py-3 text-center text-xs font-medium text-red-600">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-center text-xs font-medium text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- La connexion des livreurs se fait uniquement dans l'application, par code SMS. --}}
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        <p class="mb-2 font-semibold text-[#1E3A5F]">Connectez-vous depuis l’application OVANIE Livreur</p>
        <ol class="list-decimal space-y-1 pl-5">
            <li>Ouvrez l’application OVANIE Livreur sur votre téléphone.</li>
            <li>Saisissez votre numéro de téléphone.</li>
            <li>Entrez le code reçu par SMS.</li>
        </ol>
        <p class="mt-3 text-xs text-slate-500">Vos missions, vos gains et votre suivi GPS se trouvent dans l’application.</p>
    </div>

    {{-- Aide --}}
    <div class="mt-5 border-t border-slate-100 pt-4 text-center">
        <p class="text-[11px] leading-relaxed text-slate-400">
            Accès réservé aux livreurs autorisés par OVANIE Logistics.
        </p>
    </div>

    {{-- Badges --}}
    <div class="mt-3 flex items-center justify-center gap-4 text-slate-400">
        <div class="flex items-center gap-1">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="text-[10px] font-medium">Accès sécurisé</span>
        </div>

        <div class="flex items-center gap-1">
            <svg class="h-3 w-3" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="0" y="16" width="21.33" height="32" fill="#F77F00"/>
                <rect x="21.33" y="16" width="21.33" height="32" fill="#FFFFFF"/>
                <rect x="42.66" y="16" width="21.33" height="32" fill="#009E60"/>
            </svg>
            <span class="text-[10px] font-medium">Plateforme ivoirienne</span>
        </div>
    </div>
</div>
@endsection
