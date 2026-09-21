<?php

namespace App\Services\SupportAi;

class SupportPhoneNormalizer
{
    public function canonical(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '225')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+225'.$digits;
        }

        return '+'.$digits;
    }

    public function variants(?string $phone): array
    {
        $canonical = $this->canonical($phone);
        if (! $canonical) {
            return [];
        }

        $digits = ltrim($canonical, '+');
        $variants = [$canonical, $digits, '00'.$digits];

        if (str_starts_with($digits, '225')) {
            $local = substr($digits, 3);
            $variants[] = $local;
            $variants[] = '0'.ltrim($local, '0');
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function same(?string $left, ?string $right): bool
    {
        $leftCanonical = $this->canonical($left);
        $rightCanonical = $this->canonical($right);

        return $leftCanonical !== null && $leftCanonical === $rightCanonical;
    }
}
