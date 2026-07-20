@if ($paginator->hasPages())
<div class="pagination">
    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span style="padding:6px 12px;color:#cbd5e1;font-size:13px;">←</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}">←</a>
    @endif

    {{-- Pages --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span style="padding:6px 4px;color:#94a3b8;">…</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span style="padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600;background:#2563eb;color:#fff;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="padding:6px 12px;border-radius:6px;font-size:13px;font-weight:500;background:#f1f5f9;color:#334155;text-decoration:none;">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}">→</a>
    @else
        <span style="padding:6px 12px;color:#cbd5e1;font-size:13px;">→</span>
    @endif
</div>
@endif
