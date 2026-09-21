@extends('admin.layouts.auth')

@section('title', 'Inscription Administrateur - MateZone')

@section('page-title')
Inscription Administrateur
@endsection

@section('content')
@if ($errors->any())
<div class="message message-error">
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST"
      action="{{ route('admin.adminregister.submit') }}"
      class="admin-register-form"
      id="registerForm">

    @csrf

    <label>Nom complet</label>
    <input type="text" name="name" value="{{ old('name') }}" required autofocus>

    <label>Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required>

    <label>Mot de passe</label>
    <div class="password-wrapper">
        <input type="password" name="password" required>
        <span class="toggle-password" style="cursor:pointer;">👁️</span>
    </div>

    <label>Confirmer le mot de passe</label>
    <div class="password-wrapper">
        <input type="password" name="password_confirmation" required>
        <span class="toggle-password" style="cursor:pointer;">👁️</span>
    </div>

    <button type="submit" class="btn-register">
        S’inscrire
    </button>
</form>

<p>
    Déjà un compte ?
    <a href="{{ route('admin.adminlogin') }}">Se connecter</a>
</p>
@endsection

@section('scripts')
<script>
    const adminLoginUrl = "{{ route('admin.adminlogin') }}";
    const adminRegisterUrl = "{{ route('admin.adminregister.submit') }}";
    const adminDashboardUrl = "{{ route('admin.dashboard') }}";
</script>

<script src="{{ asset('admin/js/admin_register.js') }}"></script>

<script>
// Toggle mot de passe
document.addEventListener("DOMContentLoaded", () => {
    const toggles = document.querySelectorAll(".toggle-password");
    toggles.forEach(toggle => {
        toggle.addEventListener("click", () => {
            const input = toggle.previousElementSibling;
            if (input.type === "password") {
                input.type = "text";
                toggle.textContent = "🙈"; // changement icône
            } else {
                input.type = "password";
                toggle.textContent = "👁️";
            }
        });
    });
});
</script>
@endsection
