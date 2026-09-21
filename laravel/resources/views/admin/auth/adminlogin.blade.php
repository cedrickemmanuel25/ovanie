@extends('layouts.auth-clean')

@section('title', 'Connexion du personnel interne')

@section('content')
<div
    class="w-full overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_24px_70px_rgba(15,42,79,0.16)]"
    x-data="{ loading: false, showPassword: false }"
>
    <div class="h-1.5 w-full bg-gradient-to-r from-[#1E3A5F] via-[#245FA6] to-[#F47A20]"></div>

    <div class="px-6 py-6 sm:px-8 sm:py-7">
        {{-- Identité OVANIE --}}
        <div class="mb-5 text-center">
            <a href="{{ route('home') }}" class="inline-flex" aria-label="Retour à l'accueil OVANIE">
                <img
                    src="{{ asset('storage/logos/officiel site.png') }}"
                    alt="Logo OVANIE"
                    class="mx-auto h-12 w-auto object-contain sm:h-14"
                >
            </a>

        </div>

        {{-- En-tête --}}
        <div class="mb-6 text-center">
            <h1 class="text-[26px] font-extrabold leading-tight tracking-[-0.03em] text-[#1E3A5F] sm:text-[29px]">
                Espace Personnel OVANIE
            </h1>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">
                Connectez-vous avec le compte professionnel créé par l’administrateur.
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                Administration · Logistique · Support · Commercial
            </p>
        </div>

        {{-- Messages --}}
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Formulaire --}}
        <form method="POST" action="{{ route('admin.adminlogin.submit') }}" @submit="loading = true">
            @csrf

            <div class="mb-4">
                <label for="email" class="mb-1.5 block text-[13px] font-bold text-slate-700">
                    Adresse e-mail professionnelle
                </label>
                <div class="group relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 transition group-focus-within:text-[#245FA6]">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-3.5 7.1" />
                        </svg>
                    </span>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nom@ovanie.com"
                        class="h-12 w-full rounded-xl border border-slate-300 bg-white pl-11 pr-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#245FA6] focus:ring-4 focus:ring-blue-100"
                    >
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="mb-1.5 block text-[13px] font-bold text-slate-700">
                    Mot de passe
                </label>
                <div class="group relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 transition group-focus-within:text-[#245FA6]">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </span>

                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Votre mot de passe"
                        class="h-12 w-full rounded-xl border border-slate-300 bg-white pl-11 pr-12 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#245FA6] focus:ring-4 focus:ring-blue-100"
                    >

                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition hover:text-[#1E3A5F] focus:outline-none"
                        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                    >
                        <svg x-show="!showPassword" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" x-cloak class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411M3 3l18 18" />
                        </svg>
                    </button>
                </div>
            </div>

            <label class="mb-5 flex cursor-pointer items-center gap-2.5 text-[12px] text-slate-500">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                    class="h-4 w-4 rounded border-slate-300 text-[#245FA6] focus:ring-[#245FA6]"
                >
                <span>Rester connecté sur cet appareil</span>
            </label>

            <button
                type="submit"
                :disabled="loading"
                class="flex h-12 w-full items-center justify-center rounded-xl bg-[#1E3A5F] px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(30,58,95,0.22)] transition hover:bg-[#162D4A] focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-wait disabled:opacity-70"
            >
                <span x-show="!loading" class="flex items-center gap-2">
                    Accéder à mon espace
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </span>

                <span x-show="loading" x-cloak class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Connexion en cours…
                </span>
            </button>
        </form>

        {{-- Portail interne volontairement séparé du portail public --}}
        <div class="mt-5 text-center text-[12px] font-semibold text-slate-500">
            Portail interne réservé à Administration, Logistique, Support et Commercial.
        </div>

        <div class="mt-5 flex items-center justify-center gap-5 border-t border-slate-100 pt-4 text-[10px] font-semibold text-slate-400">
            <span class="flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Connexion sécurisée
            </span>

            <span class="flex items-center gap-1.5">
                <svg class="h-3.5 w-4" viewBox="0 0 64 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="21.34" height="40" fill="#F77F00" />
                    <rect x="21.33" width="21.34" height="40" fill="#FFFFFF" />
                    <rect x="42.66" width="21.34" height="40" fill="#009E60" />
                </svg>
                Plateforme ivoirienne
            </span>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
