{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    @foreach($staticPages as $page)
        <url>
            <loc>{{ $page }}</loc>
            <changefreq>weekly</changefreq>
            <priority>1.0</priority>
        </url>
    @endforeach

    @foreach($categories as $category)
        <url>
            <loc>{{ url('/categories/' . $category->slug) }}</loc>
            <lastmod>{{ optional($category->updated_at)->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach

    @foreach($products as $product)
        <url>
            <loc>{{ url('/produits/' . $product->slug) }}</loc>
            <lastmod>{{ optional($product->updated_at)->toAtomString() }}</lastmod>
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach

</urlset>