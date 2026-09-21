@if ($paginator->hasPages())
    <nav class="admin-pagination" role="navigation" aria-label="Pagination">
        <div class="admin-pagination-summary">
            Affichage de <strong>{{ $paginator->firstItem() }}</strong> à <strong>{{ $paginator->lastItem() }}</strong>
            sur <strong>{{ $paginator->total() }}</strong> résultat{{ $paginator->total() > 1 ? 's' : '' }}
        </div>
        <div class="admin-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="admin-page-link disabled">Précédent</span>
            @else
                <a class="admin-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="admin-page-ellipsis">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="admin-page-link active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="admin-page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="admin-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
            @else
                <span class="admin-page-link disabled">Suivant</span>
            @endif
        </div>
    </nav>
@endif
