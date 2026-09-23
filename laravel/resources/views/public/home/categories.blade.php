<section class="ov-panel ov-category-section">
    <div class="ov-section-head">
        <h2>Catégories BTP</h2>
        <a href="{{ $catalogUrl }}">Voir tout <span>→</span></a>
    </div>

    @php
        $categoryGroups = collect($categoryCards ?? [])->take(8)->chunk(4)->values();
    @endphp

    <div class="ov-category-groups" aria-label="Catégories BTP">
        @foreach($categoryGroups as $groupIndex => $group)
            <div class="ov-category-group" data-category-group="{{ $groupIndex + 1 }}">
                @foreach($group as $category)
                    <a href="{{ $categoryUrl((string) $category['slug']) }}" class="ov-category-card">
                        <span class="ov-category-visual">
                            <img
                                src="{{ $category['image'] }}"
                                alt="{{ $category['name'] }}"
                                loading="lazy"
                                decoding="async"
                            >
                        </span>
                        <strong>{{ $category['name'] }}</strong>
                        <span class="ov-category-arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
