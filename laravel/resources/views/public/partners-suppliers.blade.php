@extends('layouts.guest')

@section('title', 'Partenaires & Fournisseurs OVANIE')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/business-pages.css') }}">
@endpush

@section('content')
@php
    $contactUrl = route('contact.index', ['sujet' => 'partenariat']);
    $types = [
        ['factory', 'Fabricants', 'Producteurs de matériaux et équipements de qualité pour le BTP.'],
        ['warehouse', 'Distributeurs', 'Grossistes et revendeurs offrant disponibilité et expertise.'],
        ['badge-check', 'Marques', 'Marques reconnues souhaitant développer leur présence.'],
        ['building-2', 'Entreprises BTP', 'Entreprises de construction et prestataires de services.'],
        ['truck', 'Logistique', 'Acteurs du transport et de la distribution en Côte d’Ivoire.'],
        ['landmark', 'Partenaires institutionnels', 'Organismes et associations de l’écosystème BTP.'],
    ];
    $why = [
        ['target', 'Visibilité qualifiée', 'Exposez vos offres à une audience B2B ciblée et active.'],
        ['chart-no-axes-combined', 'Opportunités commerciales', 'Générez de nouvelles demandes et développez vos ventes.'],
        ['globe-2', 'Accès au marché BTP', 'Intégrez une marketplace structurée pour la Côte d’Ivoire.'],
        ['handshake', 'Accompagnement personnalisé', 'Bénéficiez d’un interlocuteur dédié à votre projet.'],
        ['shield-check', 'Collaboration long terme', 'Construisons des partenariats solides et durables.'],
    ];
@endphp
<main class="bp-page">
    <div class="bp-shell">
        <nav class="bp-breadcrumb"><a href="{{ route('home') }}">Accueil</a><span>›</span><strong>Partenaires &amp; Fournisseurs</strong></nav>

        <section class="bp-hero bp-partner-hero" style="background-image:url('{{ asset('images/business-pages/partners-hero.png') }}')">
            <div class="bp-hero__content"><h1>Partenaires &amp;<br>Fournisseurs <span class="bp-orange">OVANIE</span></h1><p class="bp-hero__lead">OVANIE collabore avec fabricants, distributeurs, marques, entreprises BTP, acteurs logistiques et partenaires institutionnels pour mieux servir les projets en Côte d’Ivoire.</p><div class="bp-actions"><a class="bp-btn bp-btn--orange" href="{{ $contactUrl }}">Devenir partenaire →</a><a class="bp-btn bp-btn--outline" href="{{ $contactUrl }}">Parler à notre équipe →</a></div></div>
        </section>

        <h2 class="bp-title">Nos types de partenaires</h2>
        <section class="bp-grid bp-grid--6 bp-partner-type">
            @foreach($types as [$icon,$title,$text])<article class="bp-card"><div class="bp-card__icon"><i data-lucide="{{ $icon }}"></i></div><h3>{{ $title }}</h3><p>{{ $text }}</p></article>@endforeach
        </section>

        <h2 class="bp-title">Pourquoi devenir partenaire OVANIE ?</h2>
        <section class="bp-grid bp-grid--5">
            @foreach($why as [$icon,$title,$text])<article class="bp-card"><div class="bp-card__icon"><i data-lucide="{{ $icon }}"></i></div><h3>{{ $title }}</h3><p>{{ $text }}</p></article>@endforeach
        </section>

        <h2 class="bp-title">Comment ça fonctionne ?</h2>
        <section class="bp-process bp-process--4">
            @foreach([['users','Prise de contact','Présentez votre entreprise et votre intérêt.'],['file-search','Étude du partenariat','Nous analysons votre profil et définissons les modalités.'],['settings','Mise en place','Nous configurons votre collaboration et préparons le lancement.'],['rocket','Déploiement & suivi','Votre activité est lancée et ses performances sont accompagnées.']] as $index => [$icon,$title,$text])
                <article class="bp-step"><div class="bp-step__icon"><span class="bp-step__number">0{{ $index + 1 }}</span><i data-lucide="{{ $icon }}"></i></div><h3>{{ $title }}</h3><p>{{ $text }}</p></article>
            @endforeach
        </section>

        <section class="bp-cta-photo" style="background-image:url('{{ asset('images/business-pages/partners-handshake.png') }}')"><div class="bp-cta-photo__content"><h2>Construisons ensemble<br>l’écosystème BTP de demain</h2><p>Rejoignez un réseau de partenaires engagés pour la croissance du secteur de la construction en Côte d’Ivoire.</p><a class="bp-btn bp-btn--orange" href="{{ $contactUrl }}">Devenir partenaire →</a></div></section>

        <section class="bp-logos"><h2>Ils nous font confiance</h2><div class="bp-logos__row"><div class="bp-logo">BÂTI SOLUTIONS</div><div class="bp-logo">AFRIK MATÉRIAUX</div><div class="bp-logo">IVOIRE CIMENT</div><div class="bp-logo">SODEC CONSTRUCTION</div><div class="bp-logo">ENERGY SOLAR</div><div class="bp-logo">LOGI EXPRESS</div></div></section>

        <section class="bp-contact-row"><a class="bp-contact" href="{{ $contactUrl }}"><i data-lucide="headset"></i><div><strong>Contactez notre équipe</strong><span>Présentez-nous votre projet de partenariat.</span></div></a><a class="bp-contact" href="{{ config('public_contact.whatsapp_url') }}" target="_blank" rel="noopener"><i data-lucide="message-circle"></i><div><strong>WhatsApp</strong><span>{{ config('public_contact.whatsapp_label', 'Discutez avec un conseiller') }}</span></div></a><a class="bp-contact" href="{{ route('help.center') }}"><i data-lucide="circle-help"></i><div><strong>Centre d’aide</strong><span>Consultez nos guides et réponses.</span></div></a></section>
    </div>
</main>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded',()=>window.lucide?.createIcons());</script>
@endpush
