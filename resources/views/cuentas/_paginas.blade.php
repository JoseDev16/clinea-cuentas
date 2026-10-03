@if ($paginator->hasPages())
<nav style="display:flex;align-items:center;gap:10px" aria-label="Páginas">
  @if ($paginator->onFirstPage())
    <span class="btn" style="opacity:.5">← Anterior</span>
  @else
    <a class="btn" href="{{ $paginator->previousPageUrl() }}">← Anterior</a>
  @endif
  <span class="muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
  @if ($paginator->hasMorePages())
    <a class="btn" href="{{ $paginator->nextPageUrl() }}">Siguiente →</a>
  @else
    <span class="btn" style="opacity:.5">Siguiente →</span>
  @endif
</nav>
@endif
