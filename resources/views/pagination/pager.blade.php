{{-- The paginator, built from this app's own primitives. Laravel ships a
     Tailwind-classed default, and there is no Tailwind here — it rendered as
     unstyled markup in English. Previous / position / next is all a personal
     library needs. --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="{{ __('common.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="btn btn-s is-disabled" aria-disabled="true">‹ {{ __('common.previous') }}</span>
        @else
            <a class="btn btn-s" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ {{ __('common.previous') }}</a>
        @endif

        <span class="pager__pos">
            {{ __('common.page_of', ['current' => $paginator->currentPage(), 'total' => $paginator->lastPage()]) }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-s" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('common.next') }} ›</a>
        @else
            <span class="btn btn-s is-disabled" aria-disabled="true">{{ __('common.next') }} ›</span>
        @endif
    </nav>
@endif
