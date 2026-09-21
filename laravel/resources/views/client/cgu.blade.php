@extends('layouts.client')

@section('title', 'CGU')

@push('styles')
<style>
    .client-cgu-page {
        padding: clamp(18px, 2.4vw, 34px);
    }

    .client-cgu-card {
        background: #ffffff;
        border: 1px solid #dbe4f0;
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(2, 11, 28, .08);
        overflow: hidden;
    }

    .client-cgu-text {
        margin: 0;
        padding: clamp(22px, 3vw, 44px);
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        word-break: normal;
        tab-size: 4;
        color: #06142b;
        font-family: Arial, Helvetica, sans-serif;
        font-size: clamp(14px, 1.04vw, 17px);
        line-height: 1.72;
        font-weight: 550;
    }

    @media (max-width: 720px) {
        .client-cgu-page { padding: 14px; }
        .client-cgu-card { border-radius: 18px; }
        .client-cgu-text { padding: 18px; font-size: 14px; line-height: 1.62; }
    }
</style>
@endpush

@section('content')
<section class="client-cgu-page">
    <article class="client-cgu-card">
        <pre class="client-cgu-text">@verbatim
CONDITIONS GÉNÉRALES D&#39;UTILISATION (CGU) OVANIE Version 2026
ARTICLE 2 : ÉDITEUR DE LA PLATEFORME
OVANIE est exploitée par :
OVANIE.com
Adresse : Abidjan
Téléphone : 01 61 78 18 18
Email : contact@ovanie.com
Site internet : OVANIE.com
ARTICLE 9 : PAIEMENT
OVANIE peut accepter notamment :
•	Mobile Money ;
•	Carte bancaire ;
•	Virement bancaire ;
•	Paiement à la livraison lorsque disponible.
Le paiement est sécurisé via les partenaires techniques de paiement de la plateforme.
ARTICLE 11 : PRODUITS SPÉCIAUX
Les produits suivants :
•	Ciment ;
•	Gravier ;
•	Sable ;
•	Latérite ;
•	Terre de remblai ;
•	Béton prêt à l'emploi ;
Font l'objet d'un contrôle contradictoire lors de la livraison.
Après validation de la livraison par le client :
•	Aucun retour ne pourra être accepté ;
•	Aucun remboursement ne pourra être exigé sauf erreur manifeste imputable au vendeur.
@endverbatim</pre>
    </article>
</section>
@endsection
