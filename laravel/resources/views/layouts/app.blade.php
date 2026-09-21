
@section('title', 'Accueil')

@section('content')
    {{-- TON CONTENU ACTUEL --}}
@endsection


<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                @yield('content')
            </main>

            <footer style="background:#0b1a3b; color:#fff; font-family:'Figtree',sans-serif; margin-top:0;">

                <!-- Corps principal -->
                <div style="max-width:1300px; margin:0 auto; padding:50px 40px 40px 40px; display:grid; grid-template-columns:1.4fr 1fr 1fr 1.2fr 1fr; gap:48px; align-items:start;">

                    <!-- COL 1 : Logo + description + contacts -->
                    <div>
                        <img src="/storage/logos/logo ovanie.png" alt="Ovanie" style="height:48px; margin-bottom:14px; display:block;" onerror="this.style.display='none'">
                        <p style="font-size:13px; color:#94a3b8; line-height:1.7; margin:0 0 22px 0;">Votre partenaire de confiance en mat&eacute;riaux BTP et &eacute;quipements en C&ocirc;te d'Ivoire.</p>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <span style="font-size:13px; color:#94a3b8; display:flex; align-items:center; gap:8px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                Abidjan, C&ocirc;te d'Ivoire
                            </span>
                            <span style="font-size:13px; color:#94a3b8; display:flex; align-items:center; gap:8px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                {{ config('public_contact.email', 'contact@ovanie.com') }}
                            </span>
                            <span style="font-size:13px; color:#94a3b8; display:flex; align-items:center; gap:8px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.44 2 2 0 0 1 3.59 1.25h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l.87-.87a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16.5z"/></svg>
                                {{ config('public_contact.phone_display', '01 61 78 00 00') }}
                            </span>
                        </div>
                    </div>

                    <!-- COL 2 : A propos -->
                    <div>
                        <h4 style="font-size:13px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:0.5px; padding-bottom:10px; border-bottom:2px solid #2563eb; display:inline-block; margin:0 0 18px 0;">&Agrave; Propos d'Ovanie</h4>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px;">
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px; transition:color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Qui sommes-nous ?</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Actualit&eacute;s Ovanie</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Carri&egrave;res / Recrutement</a></li>
                        </ul>
                    </div>

                    <!-- COL 3 : Gagner de l'argent -->
                    <div>
                        <h4 style="font-size:13px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 18px 0;">Gagner de l'Argent</h4>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px;">
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Vendre sur Ovanie</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Devenir Fournisseur BTP</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Vendre en OVANIE Pro</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Affiliation & Partenaires</a></li>
                        </ul>
                    </div>

                    <!-- COL 4 : Paiement & Livraison -->
                    <div>
                        <h4 style="font-size:13px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 18px 0;">Paiement & Livraison</h4>
                        <div style="margin-bottom:20px; display:flex; flex-direction:column; gap:14px;">
                            <div style="display:flex; align-items:flex-start; gap:10px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                <div>
                                    <p style="color:#fff; font-size:13px; font-weight:600; margin:0 0 2px 0;">Livraison Rapide</p>
                                    <p style="color:#94a3b8; font-size:12px; margin:0;">Sur Abidjan & int&eacute;rieur</p>
                                </div>
                            </div>
                            <div style="display:flex; align-items:flex-start; gap:10px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <div>
                                    <p style="color:#fff; font-size:13px; font-weight:600; margin:0 0 2px 0;">Produits 100% v&eacute;rifi&eacute;s</p>
                                    <p style="color:#94a3b8; font-size:12px; margin:0;">Et certifi&eacute;s</p>
                                </div>
                            </div>
                        </div>
                        <p style="font-size:12px; font-weight:700; color:#fff; text-transform:uppercase; margin:0 0 10px 0;">Paiement S&eacute;curis&eacute;</p>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                            <div style="background:#fff; border-radius:4px; padding:3px 6px; height:28px; display:flex; align-items:center;">
                                <img src="/storage/logos/moov.png" alt="Moov" style="height:20px; display:block;" onerror="this.parentElement.innerHTML='<span style=font-size:10px;color:#333;font-weight:700>Moov</span>'">
                            </div>
                            <div style="background:#fff; border-radius:4px; padding:3px 6px; height:28px; display:flex; align-items:center;">
                                <img src="/storage/logos/mtn.png" alt="MTN" style="height:20px; display:block;" onerror="this.parentElement.innerHTML='<span style=font-size:10px;color:#333;font-weight:700>MTN</span>'">
                            </div>
                            <div style="background:#fff; border-radius:4px; padding:3px 6px; height:28px; display:flex; align-items:center;">
                                <img src="/storage/logos/wave.png" alt="Wave" style="height:20px; display:block;" onerror="this.parentElement.innerHTML='<span style=font-size:10px;color:#333;font-weight:700>Wave</span>'">
                            </div>
                            <div style="background:#fff; border-radius:4px; padding:3px 6px; height:28px; display:flex; align-items:center;">
                                <img src="/storage/logos/orange-money.png" alt="Orange" style="height:20px; display:block;" onerror="this.parentElement.innerHTML='<span style=font-size:10px;color:#333;font-weight:700>Orange</span>'">
                            </div>
                            <div style="background:#fff; border-radius:4px; padding:3px 8px; height:28px; display:flex; align-items:center;">
                                <img src="/storage/logos/visa.png" alt="Visa" style="height:18px; display:block;" onerror="this.parentElement.innerHTML='<span style=font-size:10px;color:#1a1f71;font-weight:900>VISA</span>'">
                            </div>
                            <div style="background:#fff; border-radius:4px; padding:3px 6px; height:28px; display:flex; align-items:center;">
                                <img src="/storage/logos/mastercard.png" alt="Mastercard" style="height:20px; display:block;" onerror="this.parentElement.innerHTML='<span style=font-size:10px;color:#eb001b;font-weight:700>MC</span>'">
                            </div>
                        </div>
                    </div>

                    <!-- COL 5 : Besoin d'aide -->
                    <div>
                        <h4 style="font-size:13px; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 18px 0;">Besoin d'Aide ?</h4>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px;">
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Assistance client</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">FAQ & Aide en ligne</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Suivre ma commande</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Garantie acheteur Ovanie</a></li>
                            <li><a href="#" style="color:#94a3b8; text-decoration:none; font-size:13px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Faire une r&eacute;clamation</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Separateur + liens legaux -->
                <div style="border-top:1px solid #1e3a5f; margin:0 40px;">
                    <div style="max-width:1300px; margin:0 auto; padding:18px 0; display:flex; justify-content:center; gap:32px; flex-wrap:wrap;">
                        <a href="#" style="color:#64748b; font-size:12px; text-decoration:none;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#64748b'">Conditions de vente</a>
                        <a href="#" style="color:#64748b; font-size:12px; text-decoration:none;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#64748b'">Conditions d'utilisation</a>
                        <a href="#" style="color:#64748b; font-size:12px; text-decoration:none;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#64748b'">Politique de confidentialit&eacute;</a>
                    </div>
                </div>

                <!-- Copyright -->
                <div style="background:#060f22; padding:14px 40px; text-align:center;">
                    <p style="color:#475569; font-size:12px; margin:0;">&copy; 2026 Ovanie C&ocirc;te d'Ivoire. Tous droits r&eacute;serv&eacute;s. Plateforme 100% s&eacute;curis&eacute;e.</p>
                </div>
            </footer>

        </div>
    </body>
</html>
