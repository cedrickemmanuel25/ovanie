<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting($key, $default = null)
    {
        return Setting::getValue($key, $default);
    }
}

if (! function_exists('ovanie_whatsapp_number')) {
    /**
     * Retourne le numéro WhatsApp public OVANIE au format international
     * sans signe + ni espaces, par exemple 2250700000000.
     *
     * Le numéro vient exclusivement du .env afin d'éviter qu'un ancien
     * numéro non relié à l'IA reste codé en dur dans les vues.
     */
    function ovanie_whatsapp_number(): ?string
    {
        $number = (string) (
            config('support_ai.whatsapp_public_number')
            ?: config('whatsapp.public_number')
            ?: ''
        );

        $number = preg_replace('/\D+/', '', $number) ?: '';

        return $number !== '' ? $number : null;
    }
}

if (! function_exists('ovanie_whatsapp_url')) {
    /**
     * Construit le lien wa.me du WhatsApp Business intelligent OVANIE.
     * Retourne null si aucun numéro public n'est configuré.
     */
    function ovanie_whatsapp_url(?string $message = null): ?string
    {
        $number = ovanie_whatsapp_number();

        if (! $number) {
            return null;
        }

        $url = 'https://wa.me/'.$number;

        if ($message !== null && trim($message) !== '') {
            $url .= '?text='.rawurlencode(trim($message));
        }

        return $url;
    }
}

if (! function_exists('ovanie_whatsapp_available')) {
    function ovanie_whatsapp_available(): bool
    {
        return ovanie_whatsapp_number() !== null;
    }
}

if (! function_exists('ovanie_category_label')) {
    /**
     * Retourne le libellé public officiel d'une catégorie OVANIE.
     *
     * Les slugs restent ASCII et sont donc plus fiables que certaines anciennes
     * données importées qui peuvent contenir des caractères remplacés par "??".
     */
    function ovanie_category_label(?string $name, ?string $slug = null): string
    {
        $slug = \Illuminate\Support\Str::slug((string) ($slug ?: $name));

        $labels = [
            'materiaux-gros-oeuvre' => 'Matériaux gros œuvre',
            'materiaux-gros-oeuvres' => 'Matériaux gros œuvre',
            'materiaux-ecologique' => 'Matériaux écologiques',
            'materiaux-ecologiques' => 'Matériaux écologiques',
            'outillage-equipement' => 'Outillage & Équipement',
            'materiaux-de-finition' => 'Matériaux de finition',
            'energie-solaire' => 'Énergie solaire',
            'electricite-plomberie' => 'Électricité & Plomberie',
            'nos-reconditionnee' => 'Nos reconditionnés',
            'nos-reconditionnes' => 'Nos reconditionnés',
            'carte-cadeau-ovanie' => 'Carte cadeau OVANIE',
        ];

        if (isset($labels[$slug])) {
            return $labels[$slug];
        }

        return ovanie_public_text($name ?: 'Catégorie OVANIE', $slug);
    }
}

if (! function_exists('ovanie_public_text')) {
    /**
     * Nettoie un texte destiné à l'affichage public.
     *
     * - répare les principaux cas de mojibake (ex: "MatÃ©riaux") ;
     * - si des "??" ont déjà remplacé les caractères d'origine en base,
     *   reconstruit un libellé lisible à partir du slug ;
     * - ne modifie pas les données stockées en base.
     */
    function ovanie_public_text(?string $value, ?string $slug = null): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return $slug ? ovanie_humanize_slug($slug, '') : '';
        }

        // Réparations fréquentes lorsque de l'UTF-8 a été relu comme Windows-1252.
        $mojibake = [
            'Ã€' => 'À', 'Ã‚' => 'Â', 'Ã‡' => 'Ç', 'Ãˆ' => 'È', 'Ã‰' => 'É', 'ÃŠ' => 'Ê', 'Ã‹' => 'Ë',
            'ÃŽ' => 'Î', 'ÃÏ' => 'Ï', 'Ã”' => 'Ô', 'Ã™' => 'Ù', 'Ã›' => 'Û', 'Ãœ' => 'Ü',
            'Ã ' => 'à', 'Ã¢' => 'â', 'Ã§' => 'ç', 'Ã¨' => 'è', 'Ã©' => 'é', 'Ãª' => 'ê', 'Ã«' => 'ë',
            'Ã®' => 'î', 'Ã¯' => 'ï', 'Ã´' => 'ô', 'Ã¹' => 'ù', 'Ã»' => 'û', 'Ã¼' => 'ü',
            'Å“' => 'œ', 'Å’' => 'Œ', 'â€™' => '’', 'â€œ' => '“', 'â€' => '”',
            'â€“' => '–', 'â€”' => '—', 'Â°' => '°', 'Â²' => '²', 'Â³' => '³', 'Â' => '',
        ];
        $text = strtr($text, $mojibake);

        $isBroken = str_contains($text, '??')
            || str_contains($text, '�')
            || preg_match('/(?:Ã.|Â.|â.|Å.)/u', $text) === 1;

        if ($isBroken && $slug) {
            $rebuilt = ovanie_humanize_slug($slug, $text);
            if ($rebuilt !== '') {
                return $rebuilt;
            }
        }

        // Dernier filet de sécurité : ne jamais afficher les caractères de remplacement.
        // Les anciennes importations ont parfois remplacé les accents ou le signe × par des '?'.
        $text = preg_replace('/(\d)\s*\?{1,3}\s*(\d)/u', '$1 × $2', $text) ?: $text;
        $text = strtr($text, [
            's??curit??' => 'sécurité',
            'visibilit??' => 'visibilité',
            'B??lier' => 'Bélier',
            '??nergie' => 'Énergie',
            '??lectrique' => 'électrique',
            '??lectricit??' => 'électricité',
            '??vacuation' => 'évacuation',
            'c??ramique' => 'céramique',
            'r??servoir' => 'réservoir',
            '?? poser' => 'à poser',
            'mat??riaux' => 'matériaux',
            'reconditionn??s' => 'reconditionnés',
        ]);
        $text = str_replace('�', '', $text);
        $text = preg_replace('/\?{2,}/u', '', $text) ?: $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?: $text;

        return trim($text);
    }
}

if (! function_exists('ovanie_humanize_slug')) {
    /**
     * Reconstruit un titre lisible à partir d'un slug de produit/catégorie.
     * Utilisé seulement comme secours lorsqu'un libellé public est corrompu.
     */
    function ovanie_humanize_slug(?string $slug, string $original = ''): string
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return '';
        }

        $slug = trim($slug, " /\t\n\r\0\x0B-");
        $parts = array_values(array_filter(explode('-', $slug), fn ($part) => $part !== ''));

        if (count($parts) >= 4) {
            $last = (string) end($parts);
            $originalAscii = \Illuminate\Support\Str::lower(
                \Illuminate\Support\Str::ascii(str_replace(['?', '�'], '', $original))
            );

            // Les slugs produits OVANIE comportent souvent un suffixe aléatoire.
            // On le retire uniquement s'il n'existe pas dans le libellé d'origine.
            if (
                (
                    preg_match('/^[a-z0-9]{4,8}$/i', $last)
                    || preg_match('/^\d{1,3}$/', $last)
                )
                && ! preg_match('/(^|[^a-z0-9])'.preg_quote(strtolower($last), '/').'([^a-z0-9]|$)/', $originalAscii)
            ) {
                array_pop($parts);
            }
        }

        if ($parts === []) {
            return '';
        }

        $text = implode(' ', $parts);

        $replacements = [
            '/\bmateriaux\b/i' => 'matériaux',
            '/\belectrique\b/i' => 'électrique',
            '/\belectriques\b/i' => 'électriques',
            '/\belectricite\b/i' => 'électricité',
            '/\bequipement\b/i' => 'équipement',
            '/\bequipements\b/i' => 'équipements',
            '/\benergie\b/i' => 'énergie',
            '/\becologique\b/i' => 'écologique',
            '/\becologiques\b/i' => 'écologiques',
            '/\breconditionne\b/i' => 'reconditionné',
            '/\breconditionnes\b/i' => 'reconditionnés',
            '/\bceramique\b/i' => 'céramique',
            '/\breservoir\b/i' => 'réservoir',
            '/\breservoirs\b/i' => 'réservoirs',
            '/\bevacuation\b/i' => 'évacuation',
            '/\betancheite\b/i' => 'étanchéité',
            '/\bbeton\b/i' => 'béton',
            '/\bplatre\b/i' => 'plâtre',
            '/\bcable\b/i' => 'câble',
            '/\bcables\b/i' => 'câbles',
            '/\bpiece\b/i' => 'pièce',
            '/\bpieces\b/i' => 'pièces',
            '/\bunite\b/i' => 'unité',
            '/\bunites\b/i' => 'unités',
            '/\blave\b/i' => 'lavé',
            '/\blavee\b/i' => 'lavée',
            '/\binterieur\b/i' => 'intérieur',
            '/\bexterieur\b/i' => 'extérieur',
            '/\beclairage\b/i' => 'éclairage',
            '/\bsecurite\b/i' => 'sécurité',
            '/\bvisibilite\b/i' => 'visibilité',
            '/\bbelier\b/i' => 'Bélier',
            '/\bdeja\b/i' => 'déjà',
            '/\bprets\b/i' => 'prêts',
            '/\bcontrole\b/i' => 'contrôle',
            '/\bqualite\b/i' => 'qualité',
            '/\bmaconnerie\b/i' => 'maçonnerie',
            '/\boeuvre\b/i' => 'œuvre',
            '/\bfaience\b/i' => 'faïence',
            '/\btole\b/i' => 'tôle',
            '/\bregulateur\b/i' => 'régulateur',
            '/\bregulateurs\b/i' => 'régulateurs',
            '/\bprecommande\b/i' => 'précommande',
            '/\bpret a\b/i' => 'prêt à',
            '/\ba poser\b/i' => 'à poser',
            '/\ba encastrer\b/i' => 'à encastrer',
            '/\bm2\b/i' => 'm²',
            '/\bm3\b/i' => 'm³',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?: $text;
        }

        // Acronymes et unités courants BTP.
        $text = preg_replace_callback('/\b(wc|pvc|pehd|dn\d+|ip\d+|led|epi)\b/i', function ($matches) {
            return strtoupper($matches[1]);
        }, $text) ?: $text;

        $text = preg_replace('/\b(\d+)x(\d+)\b/u', '$1×$2', $text) ?: $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?: $text;

        return \Illuminate\Support\Str::ucfirst(trim($text));
    }
}
