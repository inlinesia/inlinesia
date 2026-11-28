@if ($paginator->hasPages())
    <nav class="compact-pager" aria-label="Pagination daftar peserta">
        @if ($paginator->onFirstPage())
            <span class="pager-arrow is-disabled" aria-disabled="true">← Prev</span>
        @else
            <a class="pager-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Prev</a>
        @endif

        <span class="pager-status">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="pager-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next">Next →</a>
        @else
            <span class="pager-arrow is-disabled" aria-disabled="true">Next →</span>
        @endif
    </nav>
@endif
