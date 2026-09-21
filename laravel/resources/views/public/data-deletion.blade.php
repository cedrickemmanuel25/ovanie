<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Suppression des données personnelles | OVANIE</title>

    <meta
        name="description"
        content="Instructions officielles pour demander la suppression de votre compte et de vos données personnelles OVANIE."
    >

    <meta name="robots" content="index, follow">

    <link
        rel="canonical"
        href="{{ url('/suppression-des-donnees') }}"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.7;
        }

        a {
            color: inherit;
        }

        .header {
            background: #071b3d;
            color: #fff;
        }

        .header-inner {
            max-width: 1180px;
            margin: auto;
            min-height: 72px;
            padding: 0 24px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            color: #fff;
            text-decoration: none;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .logo span {
            color: #1687e8;
        }

        .home-link {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid rgba(255,255,255,.25);
            padding: 9px 14px;
            border-radius: 8px;
        }

        .hero {
            background: linear-gradient(
                135deg,
                #071b3d 0%,
                #0b3470 65%,
                #0b5ba8 100%
            );

            color: #fff;
            padding: 65px 24px 80px;
        }

        .hero-inner {
            max-width: 920px;
            margin: auto;
        }

        .badge {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 30px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.18);
            font-size: 13px;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0 0 17px;
            font-size: 43px;
            line-height: 1.15;
        }

        .hero-description {
            margin: 0;
            max-width: 760px;
            font-size: 17px;
            color: rgba(255,255,255,.87);
        }

        .container {
            max-width: 920px;
            margin: -38px auto 60px;
            padding: 0 20px;
            position: relative;
        }

        .content {
            background: #fff;
            border-radius: 18px;
            padding: 42px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 15px 40px rgba(15, 23, 42, .08);
        }

        .important {
            padding: 20px 22px;
            background: #edf6ff;
            border: 1px solid #cce5ff;
            border-left: 5px solid #1476c9;
            border-radius: 10px;
            margin-bottom: 34px;
        }

        .important strong {
            display: block;
            color: #073c72;
            margin-bottom: 5px;
        }

        .important p {
            margin: 0;
        }

        h2 {
            color: #071b3d;
            font-size: 24px;
            line-height: 1.3;
            margin: 40px 0 13px;
        }

        h2:first-of-type {
            margin-top: 0;
        }

        p {
            margin: 0 0 15px;
        }

        ul,
        ol {
            padding-left: 25px;
            margin: 12px 0 22px;
        }

        li {
            margin-bottom: 9px;
        }

        .steps {
            list-style: none;
            padding: 0;
            margin-top: 22px;
            counter-reset: steps;
        }

        .steps li {
            position: relative;
            padding: 0 0 26px 60px;
            min-height: 48px;
            counter-increment: steps;
        }

        .steps li::before {
            content: counter(steps);

            position: absolute;
            left: 0;
            top: 0;

            width: 40px;
            height: 40px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #0b3470;
            color: #fff;

            border-radius: 50%;
            font-weight: bold;
        }

        .contact-box {
            margin-top: 22px;
            padding: 25px;

            background: #f8fafc;
            border: 1px solid #dce3eb;
            border-radius: 14px;
        }

        .contact-title {
            font-size: 20px;
            font-weight: 700;
            color: #071b3d;
            margin-bottom: 12px;
        }

        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;

            min-height: 47px;
            padding: 11px 20px;

            border-radius: 9px;

            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .button-primary {
            background: #0b3470;
            color: #fff;
        }

        .button-whatsapp {
            background: #1eaa59;
            color: #fff;
        }

        .security {
            background: #fff9e8;
            border: 1px solid #f4df9d;
            padding: 20px 22px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .security strong {
            color: #6f5000;
        }

        .legal-note {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
        }

        .updated {
            margin-top: 30px;
            color: #6b7280;
            font-size: 13px;
        }

        .footer {
            background: #071b3d;
            color: #c9d5e6;
            text-align: center;
            padding: 32px 20px;
            font-size: 14px;
        }

        .footer-links {
            margin-top: 8px;
        }

        .footer a {
            color: #fff;
            text-decoration: none;
            margin: 0 6px;
        }

        @media (max-width: 700px) {
            .header-inner {
                padding: 0 16px;
            }

            .hero {
                padding: 45px 18px 65px;
            }

            h1 {
                font-size: 32px;
            }

            .container {
                padding: 0 12px;
                margin-top: -25px;
            }

            .content {
                padding: 25px 20px;
                border-radius: 13px;
            }

            h2 {
                font-size: 21px;
            }

            .buttons {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }

            .home-link {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <div class="header-inner">

        <a
            href="{{ route('home') }}"
            class="logo"
        >
            OVA<span>NIE</span>
        </a>

        <a
            href="{{ route('home') }}"
            class="home-link"
        >
            Retour à OVANIE
        </a>

    </div>
</header>


<section class="hero">

    <div class="hero-inner">

        <div class="badge">
            Confidentialité et protection des données
        </div>

        <h1>
            Suppression des données personnelles
        </h1>

        <p class="hero-description">
            OVANIE vous permet de demander la suppression de votre
            compte et des données personnelles associées conformément
            aux règles applicables en matière de protection des données.
        </p>

    </div>

</section>


<main class="container">

    <article class="content">

        <div class="important">

            <strong>
                Demande officielle de suppression des données
            </strong>

            <p>
                Cette page explique comment demander à OVANIE la
                suppression de votre compte et de vos données
                personnelles. Certaines données peuvent néanmoins être
                conservées lorsqu'une obligation légale, réglementaire,
                comptable, fiscale ou de sécurité l'impose.
            </p>

        </div>


        <h2>
            1. Comment demander la suppression de vos données ?
        </h2>

        <p>
            Vous pouvez demander la suppression de votre compte OVANIE
            et des données personnelles qui lui sont associées.
        </p>

        <p>
            Pour effectuer votre demande, suivez les étapes suivantes :
        </p>


        <ol class="steps">

            <li>
                <strong>Contactez OVANIE.</strong><br>

                Utilisez notre page de contact ou contactez directement
                le support OVANIE via WhatsApp.
            </li>

            <li>
                <strong>
                    Indiquez que vous demandez la suppression de vos données.
                </strong><br>

                Précisez clairement que votre demande concerne la
                suppression de votre compte OVANIE et/ou de vos données
                personnelles.
            </li>

            <li>
                <strong>
                    Indiquez les informations permettant d'identifier votre compte.
                </strong><br>

                Communiquez votre nom ainsi que le numéro de téléphone ou
                l'adresse e-mail associé à votre compte OVANIE.
            </li>

            <li>
                <strong>
                    Confirmez votre identité si nécessaire.
                </strong><br>

                Pour éviter la suppression frauduleuse du compte d'une
                autre personne, OVANIE peut demander une vérification
                raisonnable de votre identité.
            </li>

            <li>
                <strong>
                    Votre demande est traitée par OVANIE.
                </strong><br>

                Après vérification, les données concernées sont supprimées
                ou anonymisées lorsqu'elles ne doivent pas être conservées
                pour des raisons légales ou opérationnelles légitimes.
            </li>

        </ol>


        <h2>
            2. Informations à fournir dans votre demande
        </h2>

        <p>
            Pour nous permettre de retrouver correctement votre compte,
            votre demande doit contenir suffisamment d'informations pour
            vous identifier.
        </p>

        <ul>
            <li>Votre nom et prénom ;</li>

            <li>
                le numéro de téléphone associé à votre compte OVANIE ;
            </li>

            <li>
                l'adresse e-mail associée à votre compte, si applicable ;
            </li>

            <li>
                la mention :
                <strong>
                    « Je demande la suppression de mon compte et de mes
                    données personnelles OVANIE ».
                </strong>
            </li>
        </ul>


        <h2>
            3. Données pouvant être supprimées
        </h2>

        <p>
            Selon votre utilisation de la plateforme, la suppression
            peut notamment concerner :
        </p>

        <ul>
            <li>les informations de votre profil OVANIE ;</li>

            <li>votre nom et vos coordonnées ;</li>

            <li>votre numéro de téléphone ;</li>

            <li>votre adresse e-mail ;</li>

            <li>vos adresses enregistrées ;</li>

            <li>certaines préférences de votre compte ;</li>

            <li>
                certaines données associées à vos interactions avec
                le support OVANIE ;
            </li>

            <li>
                certaines données techniques associées à votre compte,
                lorsqu'elles peuvent légalement être supprimées.
            </li>
        </ul>


        <h2>
            4. Données liées au support WhatsApp OVANIE
        </h2>

        <p>
            Lorsque vous contactez OVANIE via WhatsApp, certaines
            informations nécessaires au fonctionnement du service
            d'assistance peuvent être traitées par OVANIE.
        </p>

        <p>
            Cela peut notamment comprendre :
        </p>

        <ul>
            <li>votre numéro de téléphone WhatsApp ;</li>

            <li>le contenu des messages adressés à OVANIE ;</li>

            <li>la date et l'heure des échanges ;</li>

            <li>
                les informations nécessaires au traitement de votre
                demande ;
            </li>

            <li>
                les références d'une commande, d'un paiement ou d'une
                livraison lorsque vous les communiquez au support.
            </li>
        </ul>

        <p>
            Vous pouvez demander la suppression des données de support
            associées à votre numéro lorsque leur conservation n'est
            plus nécessaire et qu'aucune obligation légale ne s'y oppose.
        </p>


        <h2>
            5. Données pouvant être conservées
        </h2>

        <p>
            Certaines données peuvent devoir être conservées après la
            fermeture ou la suppression de votre compte.
        </p>

        <p>
            Cela peut être nécessaire notamment pour :
        </p>

        <ul>
            <li>respecter les obligations légales et réglementaires ;</li>

            <li>respecter les obligations comptables ou fiscales ;</li>

            <li>
                conserver la preuve de certaines transactions ;
            </li>

            <li>
                traiter une commande qui n'est pas encore terminée ;
            </li>

            <li>gérer un remboursement en cours ;</li>

            <li>gérer un litige ou une réclamation ;</li>

            <li>prévenir et détecter la fraude ;</li>

            <li>assurer la sécurité de la plateforme ;</li>

            <li>
                protéger les droits d'OVANIE, de ses clients, vendeurs
                et partenaires.
            </li>
        </ul>

        <p>
            Ces informations ne sont conservées que dans la mesure
            nécessaire aux finalités concernées et conformément aux
            obligations applicables.
        </p>


        <h2>
            6. Conséquences de la suppression
        </h2>

        <p>
            Lorsque votre compte est définitivement supprimé :
        </p>

        <ul>
            <li>
                vous pouvez perdre l'accès à votre espace client ;
            </li>

            <li>
                vos informations personnelles supprimables sont
                supprimées ou anonymisées ;
            </li>

            <li>
                vos préférences associées au compte peuvent être
                supprimées ;
            </li>

            <li>
                certaines données anonymisées peuvent continuer à être
                utilisées à des fins statistiques ;
            </li>

            <li>
                les informations soumises à une obligation de
                conservation restent conservées uniquement pendant la
                durée nécessaire.
            </li>
        </ul>


        <h2>
            7. Comment contacter OVANIE ?
        </h2>

        <div class="contact-box">

            <div class="contact-title">
                Support OVANIE
            </div>

            <p>
                Pour demander la suppression de vos données, vous pouvez
                utiliser notre formulaire de contact ou contacter
                directement le support OVANIE sur WhatsApp.
            </p>

            <p>
                <strong>WhatsApp :</strong><br>
                {{ config('public_contact.whatsapp_label', 'Discutez avec un conseiller') }}
            </p>

            <div class="buttons">

                <a
                    href="{{ route('contact.index') }}"
                    class="button button-primary"
                >
                    Formulaire de contact OVANIE
                </a>

                <a
                    href="{{ config('public_contact.whatsapp_url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="button button-whatsapp"
                >
                    {{ config('public_contact.whatsapp_label', 'Discutez avec un conseiller') }}
                </a>

            </div>

        </div>


        <h2>
            8. Sécurité de la demande
        </h2>

        <div class="security">

            <strong>
                Protection contre les demandes frauduleuses
            </strong>

            <p style="margin-top: 8px; margin-bottom: 0;">
                OVANIE peut vérifier que la personne qui demande la
                suppression est bien titulaire du compte concerné.
                OVANIE ne vous demandera jamais de communiquer votre mot
                de passe en clair dans une demande de suppression de
                données.
            </p>

        </div>


        <h2>
            9. Demande concernant uniquement les données WhatsApp
        </h2>

        <p>
            Si votre demande concerne uniquement les données issues de
            vos échanges avec le support WhatsApp OVANIE, indiquez-le
            clairement dans votre message.
        </p>

        <p>
            Vous pouvez par exemple écrire :
        </p>

        <div class="important">
            <p>
                « Bonjour OVANIE, je demande la suppression des données
                personnelles associées à mes échanges WhatsApp avec
                OVANIE. Mon numéro WhatsApp est le numéro depuis lequel
                j'envoie cette demande. »
            </p>
        </div>


        <h2>
            10. Traitement de la demande
        </h2>

        <p>
            OVANIE examine la demande, vérifie si nécessaire l'identité
            du demandeur et détermine les données pouvant être supprimées
            immédiatement ainsi que celles qui doivent éventuellement
            être conservées conformément aux obligations applicables.
        </p>

        <p>
            Une confirmation peut être adressée à l'utilisateur lorsque
            le traitement de sa demande est terminé.
        </p>


        <div class="legal-note">

            <strong>
                OVANIE – Protection des données personnelles
            </strong>

            <p style="margin-top: 10px;">
                Cette page constitue l'URL publique d'instructions
                permettant aux utilisateurs de demander la suppression
                de leurs données personnelles auprès d'OVANIE.
            </p>

            <p>
                Site officiel :
                <a href="{{ route('home') }}">
                    www.ovanie.com
                </a>
            </p>

        </div>


        <p class="updated">
            Dernière mise à jour : 24 août 2026
        </p>

    </article>

</main>


<footer class="footer">

    <div>
        © {{ date('Y') }} OVANIE — Tous droits réservés.
    </div>

    <div class="footer-links">

        <a href="{{ route('home') }}">
            Accueil
        </a>

        ·

        <a href="{{ route('cgu') }}">
            Conditions générales
        </a>

        ·

        <a href="{{ route('contact.index') }}">
            Contact
        </a>

    </div>

</footer>

</body>
</html>