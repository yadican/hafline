@if ($paginator->hasPages())
    <nav class="panel-pager" aria-label="Sayfalar">
        @if ($paginator->onFirstPage())
            <span class="panel-pager-btn is-disabled"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Geri</span>
        @else
            <a class="panel-pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Geri</a>
        @endif

        <span class="panel-pager-pages">
            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if ($page === $paginator->currentPage())
                    <span class="panel-pager-num is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="panel-pager-num" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        </span>

        @if ($paginator->hasMorePages())
            <a class="panel-pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">İleri <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        @else
            <span class="panel-pager-btn is-disabled">İleri <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
        @endif
    </nav>
@endif
