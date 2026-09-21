@props([
    'product',
    'alt' => null,
    'class' => '',
    'loading' => 'lazy',
])

@php
    use Illuminate\Support\Facades\Storage;

    $url = static function (?string $path): ?string {
        if (blank($path)) return null;

        $path = trim((string) $path);

        if (preg_match('#^https?://#i', $path)) return $path;
        if (str_starts_with($path, '/storage/')) return url($path);
        if (str_starts_with($path, 'storage/')) return asset($path);

        $path = preg_replace('#^(public/|/public/)#i', '', $path) ?? $path;

        return Storage::disk((string) config('product-images.disk', 'public'))
            ->url(ltrim($path, '/'));
    };

    $image = $product?->relationLoaded('images')
        ? $product->images->sortByDesc(fn ($row) => (bool) ($row->is_main ?? false))->first()
        : ($product && method_exists($product, 'images')
            ? $product->images()->orderByDesc('is_main')->orderBy('id')->first()
            : null);

    $gallery = $product?->gallery;

    if (is_string($gallery)) {
        $decoded = json_decode($gallery, true);
        $gallery = is_array($decoded) ? $decoded : [$gallery];
    }

    $galleryOriginal = collect(is_array($gallery) ? $gallery : [])
        ->first(fn ($path) => is_string($path) && ! str_contains($path, '/normalized/'));

    /*
     * original_path/gallery servent au rendu adaptatif immédiat.
     * card_path reste le fallback optimisé.
     */
    $sourcePath = $image?->original_path
        ?: $galleryOriginal
        ?: $image?->card_path
        ?: $image?->path;

    $imageUrl = $url($sourcePath) ?: ($product?->main_image_url ?: null);
    $imageAlt = $alt ?: ($product?->name ?: 'Produit OVANIE');
    $version = optional($image?->normalized_at)->timestamp ?: optional($product?->updated_at)->timestamp;

    if ($imageUrl && $version) {
        $imageUrl .= str_contains($imageUrl, '?') ? '&v=' . $version : '?v=' . $version;
    }
@endphp

@once
<style>
    .ov-product-media {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        display: grid;
        place-items: center;
        overflow: hidden;
        border-radius: 12px;
        background: #f6f8fb;
        isolation: isolate;
    }

    .ov-product-media::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 2;
        border: 1px solid rgba(8, 39, 95, .06);
        border-radius: inherit;
        pointer-events: none;
    }

    .ov-product-media-backdrop,
    .ov-product-media-main {
        position: absolute;
        display: block;
    }

    .ov-product-media-backdrop {
        inset: -18px;
        z-index: 0;
        width: calc(100% + 36px);
        height: calc(100% + 36px);
        object-fit: cover;
        filter: blur(17px) saturate(.82);
        opacity: 0;
        transform: scale(1.08);
        transition: opacity .18s ease;
    }

    .ov-product-media::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 1;
        background: rgba(255, 255, 255, .58);
        opacity: 0;
        transition: opacity .18s ease;
    }

    .ov-product-media-main {
        inset: 0;
        z-index: 1;
        width: 100%;
        height: 100%;
        padding: 10px;
        object-fit: contain;
        object-position: center;
    }

    .ov-product-media.is-adaptive .ov-product-media-backdrop,
    .ov-product-media.is-adaptive::before {
        opacity: 1;
    }

    .ov-product-media.is-adaptive .ov-product-media-main {
        inset: 5%;
        width: 90%;
        height: 90%;
        padding: 0;
        border-radius: 7px;
        box-shadow: 0 7px 22px rgba(8, 39, 95, .16);
    }

    .ov-product-image-placeholder {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        color: #8191aa;
        background: linear-gradient(145deg, #f8fafc, #eef3f9);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ov-product-media]').forEach(function (frame) {
            const image = frame.querySelector('.ov-product-media-main');

            if (!image) return;

            const apply = function () {
                const width = image.naturalWidth || 0;
                const height = image.naturalHeight || 0;

                if (!width || !height) return;

                const ratio = width / height;
                frame.classList.toggle('is-adaptive', ratio < 0.82 || ratio > 1.22);
            };

            if (image.complete) apply();
            image.addEventListener('load', apply, { once: true });
        });
    });
</script>
@endonce

<div
    {{ $attributes->merge(['class' => trim('ov-product-media ' . $class)]) }}
    data-ov-product-media
>
    @if($imageUrl)
        <img
            class="ov-product-media-backdrop"
            src="{{ $imageUrl }}"
            alt=""
            aria-hidden="true"
            loading="{{ $loading }}"
            decoding="async"
        >

        <img
            class="ov-product-media-main"
            src="{{ $imageUrl }}"
            alt="{{ $imageAlt }}"
            loading="{{ $loading }}"
            decoding="async"
            width="800"
            height="800"
        >
    @else
        <span class="ov-product-image-placeholder" aria-label="Image indisponible">
            Image indisponible
        </span>
    @endif
</div>
