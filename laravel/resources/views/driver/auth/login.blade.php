@extends('layouts.auth-clean')

@section('title', 'Connexion Livreur')

@section('content')
<div
    class="w-full rounded-2xl bg-white p-5 shadow-xl ring-1 ring-slate-200/70 lg:p-6"
    x-data="{ loading: false, showPin: false }"
>
    {{-- Logo --}}
    <div class="mb-4 text-center">
        <img
            src="{{ asset('storage/logos/officiel site.png') }}"
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
            Connectez-vous pour consulter et traiter vos missions.
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

    <form
        method="POST"
        action="{{ route('driver.login.submit') }}"
        @submit="loading = true"
        novalidate
    >
        @csrf

        {{-- Téléphone --}}
        <div class="mb-4">
            <label for="phone" class="mb-1 block text-[13px] font-semibold text-slate-800">
                Numéro de téléphone
            </label>

            <div class="relative text-slate-400 focus-within:text-blue-600">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.95.684l1.5 4.493a1 1 0 01-.502 1.21l-2.257 1.128a11.042 11.042 0 005.516 5.516l1.128-2.257a1 1 0 011.21-.502l4.493 1.5a1 1 0 01.684.95V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </div>

                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    required
                    autofocus
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="07 00 00 00 00"
                    class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm text-slate-800 outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                >
            </div>
        </div>

        {{-- Code d'accès --}}
        <div class="mb-4">
            <label for="pin" class="mb-1 block text-[13px] font-semibold text-slate-800">
                Code d'accès à 6 chiffres
            </label>

            <div class="relative text-slate-400 focus-within:text-blue-600">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>

                <input
                    id="pin"
                    :type="showPin ? 'text' : 'password'"
                    name="pin"
                    required
                    inputmode="numeric"
                    autocomplete="current-password"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="••••••"
                    class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-10 text-sm tracking-[0.28em] text-slate-800 outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                >

                <button
                    type="button"
                    @click="showPin = !showPin"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 transition hover:text-slate-600 focus:outline-none"
                    :aria-label="showPin ? 'Masquer le code' : 'Afficher le code'"
                >
                    <svg x-show="!showPin" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="showPin" style="display:none" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Session --}}
        <label class="mb-5 flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600">
            <input
                type="checkbox"
                name="remember"
                value="1"
                class="h-4 w-4 rounded border-slate-300 text-[#1E3A5F] focus:ring-blue-200"
            >
            <span>Rester connecté sur cet appareil</span>
        </label>

        {{-- Bouton --}}
        <button
            type="submit"
            :disabled="loading"
            class="flex w-full items-center justify-center rounded-xl bg-[#1E3A5F] py-3 text-sm font-semibold text-white transition hover:bg-[#152c4a] disabled:cursor-not-allowed disabled:opacity-70"
        >
            <template x-if="!loading">
                <span class="flex items-center">
                    <span>Accéder à l'espace</span>
                    <svg class="ml-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </span>
            </template>

            <template x-if="loading">
                <span class="flex items-center">
                    <svg class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <span>Connexion en cours…</span>
                </span>
            </template>
        </button>
    </form>

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
