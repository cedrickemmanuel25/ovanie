@extends('layouts.guest')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/forgot-password.css') }}">
@endsection

@section('content')
<div class="auth-forgot-container">

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Mot de passe oublié ? Pas de problème. Indiquez simplement votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe et en choisir un nouveau.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input 
                id="email" 
                class="block mt-1 w-full" 
                type="email" 
                name="email" 
                :value="old('email')" 
                required 
                autofocus 
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Envoyer le lien de réinitialisation
            </x-primary-button>
        </div>
    </form>

</div>
@endsection