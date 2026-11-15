@if ($paginator->hasPages())
<nav class="pager" aria-label="Pages">
  @if ($paginator->onFirstPage())<span class="disabled">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
  <span aria-current="page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
  @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="disabled">Next</span>@endif
</nav>
@endif
