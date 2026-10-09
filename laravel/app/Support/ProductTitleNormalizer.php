<?php

namespace App\Support;

/**
 * Met les titres de produits au même format : espaces, majuscules intégrales, dimensions, fautes connues.
 * Appliqué à chaque enregistrement d'un produit et par la commande ovanie:normalize-product-titles.
 */
class ProductTitleNormalizer
{
    /** Sigles conservés en majuscules quand un titre entièrement en capitales est remis en minuscules. */
    private const ACRONYMS = ['PVC', 'LED', 'IPN', 'IPE', 'HEA', 'HEB', 'UPN', 'MDF', 'PPR', 'PER', 'PEHD', 'CPJ', 'CEM', 'TMT', 'HA', 'BA', 'OVANIE', 'KVA', 'USB', 'TV'];

    /** Fautes d'orthographe relevées dans le catalogue (clé en minuscules sans accent). */
    private const TYPOS = [
        'escabo' => 'escabeau',
        'faitire' => 'faîtière',
        'faitiere' => 'faîtière',
    ];

    public static function normalize(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if ($title === '') {
            return $title;
        }

        // Titre entièrement en capitales : phrase normale, sigles et références chiffrées conservés.
        $letters = preg_replace('/[^\p{L}]/u', '', $title) ?? '';
        if (mb_strlen($letters) >= 4 && $letters === mb_strtoupper($letters)) {
            $words = array_map(static function (string $word): string {
                $bare = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '';

                return in_array($bare, self::ACRONYMS, true) || preg_match('/\d/', $word) ? $word : mb_strtolower($word);
            }, explode(' ', $title));
            $title = implode(' ', $words);
        }

        // Fautes connues, mot par mot, sans toucher à la casse du reste.
        $title = preg_replace_callback('/\p{L}+/u', static function (array $match): string {
            $key = strtr(mb_strtolower($match[0]), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'î' => 'i', 'ï' => 'i']);
            if (! isset(self::TYPOS[$key]) || mb_strtolower($match[0]) === self::TYPOS[$key]) {
                return $match[0];
            }

            return self::TYPOS[$key];
        }, $title) ?? $title;

        // Dimensions : « 15x20x40cm » devient « 15 × 20 × 40 cm ».
        $title = preg_replace('/(\d)\s*[xX*]\s*(?=\d)/u', '$1 × ', $title) ?? $title;
        $title = preg_replace_callback('/(\d)\s?(mm|cm|kg|ml)\b/iu', static fn (array $m): string => $m[1] . ' ' . mb_strtolower($m[2]), $title) ?? $title;

        // Première lettre en capitale.
        return mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1);
    }
}
