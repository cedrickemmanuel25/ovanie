@extends('layouts.auth-clean')
@section('title', 'Créer votre compte Client')

@section('content')
<div class="bg-white rounded-2xl shadow-lg p-5 lg:p-6 w-full" 
     x-data="{ 
         loading: false, 
         showPassword: false, 
         showConfirmPassword: false,
         password: '',
         confirmPassword: '',
         terms: false,
         get passwordStrength() {
             let strength = 0;
             if (this.password.length >= 8) strength++;
             if (this.password.match(/[A-Z]/)) strength++;
             if (this.password.match(/[0-9]/)) strength++;
             if (this.password.match(/[^A-Za-z0-9]/)) strength++;
             return strength;
         },
         get passwordMatch() {
             return this.confirmPassword === '' || this.password === this.confirmPassword;
         }
     }">
    
    <!-- Logo -->
    <div class="text-center mb-4">
        <a href="{{ route('home') }}">
            <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE Logo" class="h-10 mx-auto mb-1">
        </a>
    </div>

    <!-- Titres -->
    <div class="text-center mb-5">
        <h1 class="font-bold text-2xl text-[#1E3A5F] mb-1 leading-tight">Créer votre compte Client OVANIE</h1>
        <p class="text-gray-500 text-sm">Ce formulaire crée uniquement un compte client pour vos achats et commandes.</p>
    </div>

    <form method="POST" action="{{ route('register.post') }}" @submit="loading = true">
        @csrf

        <!-- Prénom & Nom -->
        <div class="grid grid-cols-2 gap-4 mb-3">
            <div>
                <label for="first_name" class="block font-semibold text-gray-800 text-[13px] mb-1">Prénom</label>
                <div class="relative text-gray-400 focus-within:text-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-colors text-gray-800 focus:outline-none text-sm">
                </div>
                @error('first_name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="last_name" class="block font-semibold text-gray-800 text-[13px] mb-1">Nom</label>
                <div class="relative text-gray-400 focus-within:text-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-colors text-gray-800 focus:outline-none text-sm">
                </div>
                @error('last_name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="block font-semibold text-gray-800 text-[13px] mb-1">Adresse e-mail</label>
            <div class="relative text-gray-400 focus-within:text-blue-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-colors text-gray-800 focus:outline-none text-sm">
            </div>
            @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <!-- Téléphone -->
        <div class="mb-3">
            <label for="phone" class="block font-semibold text-gray-800 text-[13px] mb-1">Téléphone</label>
            <div class="flex rounded-xl border border-gray-300 focus-within:border-blue-600 focus-within:ring-2 focus-within:ring-blue-100 transition-colors overflow-visible relative z-20">
                <div class="relative border-r border-gray-300 bg-gray-50 flex-shrink-0 rounded-l-xl" x-data="{ open: false, selected: '+225', flag: '🇨🇮' }">
                    <input type="hidden" name="phone_country" :value="selected">
                    <button type="button" @click="open = !open" @click.away="open = false" class="flex items-center px-3 py-2 h-full text-gray-700 font-medium focus:outline-none rounded-l-xl text-sm">
                        <span class="mr-1" x-text="flag"></span>
                        <span x-text="selected"></span>
                        <svg class="w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" style="display:none;" class="absolute top-full left-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 w-40 py-1">
                        <template x-for="country in [
                            { code: '+225', flag: '🇨🇮', name: 'Côte d\'Ivoire' },
                            { code: '+221', flag: '🇸🇳', name: 'Sénégal' },
                            { code: '+223', flag: '🇲🇱', name: 'Mali' },
                            { code: '+226', flag: '🇧🇫', name: 'Burkina Faso' },
                            { code: '+233', flag: '🇬🇭', name: 'Ghana' },
                            { code: '+224', flag: '🇬🇳', name: 'Guinée' }
                        ]">
                            <button type="button" @click="selected = country.code; flag = country.flag; open = false" class="w-full text-left px-4 py-1 hover:bg-blue-50 flex items-center space-x-2 text-sm text-gray-700">
                                <span x-text="country.flag"></span>
                                <span x-text="country.code"></span>
                                <span class="text-xs text-gray-400" x-text="country.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
                    class="w-full px-3 py-2 border-none focus:ring-0 text-gray-800 rounded-r-xl focus:outline-none text-sm"
                    @input="$event.target.value = $event.target.value.replace(/\D/g, '').replace(/(\d{2})(?=\d)/g, '$1 ').trim()">
            </div>
            @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <!-- Mots de passe -->
        <div class="grid grid-cols-2 gap-4 mb-1">
            <div>
                <label for="password" class="block font-semibold text-gray-800 text-[13px] mb-1">Mot de passe</label>
                <div class="relative text-gray-400 focus-within:text-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                        x-model="password"
                        class="w-full pl-9 pr-9 py-2 rounded-xl border border-gray-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-colors text-gray-800 focus:outline-none text-sm">
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <svg x-show="showPassword" style="display:none;" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                    </button>
                </div>
                @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block font-semibold text-gray-800 text-[13px] mb-1">Confirmer</label>
                <div class="relative text-gray-400 focus-within:text-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <input id="password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" name="password_confirmation" required
                        x-model="confirmPassword"
                        :class="!passwordMatch ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : 'border-gray-300 focus:border-blue-600 focus:ring-blue-100'"
                        class="w-full pl-9 pr-9 py-2 rounded-xl border transition-colors text-gray-800 focus:ring-2 focus:outline-none text-sm">
                    <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg x-show="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <svg x-show="showConfirmPassword" style="display:none;" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Jauges + Error password -->
        <div class="mb-3">
            <div class="flex gap-1 h-1 w-1/2 mt-1.5 mb-1" x-show="password.length > 0">
                <div class="h-full flex-1 rounded-full transition-colors" :class="passwordStrength >= 1 ? (passwordStrength == 1 ? 'bg-red-500' : (passwordStrength == 2 ? 'bg-orange-400' : (passwordStrength == 3 ? 'bg-yellow-400' : 'bg-green-500'))) : 'bg-gray-200'"></div>
                <div class="h-full flex-1 rounded-full transition-colors" :class="passwordStrength >= 2 ? (passwordStrength == 2 ? 'bg-orange-400' : (passwordStrength == 3 ? 'bg-yellow-400' : 'bg-green-500')) : 'bg-gray-200'"></div>
                <div class="h-full flex-1 rounded-full transition-colors" :class="passwordStrength >= 3 ? (passwordStrength == 3 ? 'bg-yellow-400' : 'bg-green-500') : 'bg-gray-200'"></div>
                <div class="h-full flex-1 rounded-full transition-colors" :class="passwordStrength >= 4 ? 'bg-green-500' : 'bg-gray-200'"></div>
            </div>
            <p x-show="!passwordMatch && confirmPassword !== ''" class="text-xs text-red-500 mt-1">Les mots de passe ne correspondent pas</p>
        </div>

        <!-- CGU -->
        <div class="mb-4">
            <label class="flex items-start cursor-pointer group">
                <div class="flex items-center h-4">
                    <input type="checkbox" name="terms" x-model="terms" required class="w-4 h-4 text-[#2563EB] bg-white border-gray-300 rounded focus:ring-blue-500 mt-0">
                </div>
                <span class="ml-2 text-xs text-gray-600 leading-tight">
                    J'accepte les <a href="/terms" class="text-[#2563EB] hover:underline font-medium" target="_blank">Conditions d'utilisation</a> et la <a href="/privacy" class="text-[#2563EB] hover:underline font-medium" target="_blank">Politique de confidentialité</a>
                </span>
            </label>
        </div>

        <!-- Bouton Submit -->
        <button type="submit" :disabled="!terms || !passwordMatch || loading" class="w-full bg-[#2563EB] hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed">
            <template x-if="!loading">
                <div class="flex items-center">
                    <span>Créer mon compte</span>
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </div>
            </template>
            <template x-if="loading">
                <div class="flex items-center text-sm">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Création en cours…</span>
                </div>
            </template>
        </button>
    </form>

    <!-- Séparateur -->
    <div class="relative my-5">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
        <div class="relative flex justify-center text-xs"><span class="px-3 bg-white text-gray-400">Ou inscrivez-vous avec</span></div>
    </div>

    <!-- Boutons OAuth -->
    <div class="grid grid-cols-2 gap-4 mb-4">
        <a href="{{ route('login.google') }}" class="flex items-center justify-center py-2 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/><path fill="none" d="M1 1h22v22H1z"/></svg>
            <span class="text-gray-700 font-bold text-xs">Google</span>
        </a>
        <a href="{{ route('login.facebook') }}" class="flex items-center justify-center py-2 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            <span class="text-[#1877F2] font-bold text-xs">Facebook</span>
        </a>
    </div>

    <!-- Lien Connexion -->
    <div class="text-center mb-4">
        <p class="text-gray-500 text-xs">
            Déjà un compte ? 
            <a href="{{ route('login') }}" class="text-[#2563EB] font-bold hover:underline inline-flex items-center">
                Connectez-vous
                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </p>
    </div>

    <!-- Badges (1 ligne) -->
    <div class="flex justify-center space-x-4 items-center px-2 pt-4 border-t border-gray-100">
        <div class="flex items-center text-gray-400">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            <span class="text-[10px] font-medium">SSL Sécurisé</span>
        </div>
        <div class="flex items-center text-gray-400">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span class="text-[10px] font-medium">Données protégées</span>
        </div>
        <div class="flex items-center text-gray-400">
            <svg class="w-3 h-3 mr-1" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <rect x="0" y="16" width="21.33" height="32" fill="#F77F00"/>
                <rect x="21.33" y="16" width="21.33" height="32" fill="#FFFFFF"/>
                <rect x="42.66" y="16" width="21.33" height="32" fill="#009E60"/>
            </svg>
            <span class="text-[10px] font-medium">OVANIE Côte d’Ivoire</span>
        </div>
    </div>
</div>
@endsection
