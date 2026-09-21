        <section class="ov-panel ov-category-section">
            <div class="ov-section-head">
                <h2>Catégories BTP</h2>
                <a href="{{ $catalogUrl }}">Voir tout <span>→</span></a>
            </div>

            <div class="ov-category-grid">
                @foreach($categoryCards as $category)
                    <a href="{{ $categoryUrl((string) $category['slug']) }}" class="ov-category-card">
                        <span class="ov-category-visual">
                            @if(!empty($category['image']))
                                <img src="{{ $category['image'] }}" alt="{{ $category['name'] }}" loading="lazy" decoding="async">
                            @endif
                        </span>
                        <strong>{{ $category['name'] }}</strong>
                        <span class="ov-category-arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>
