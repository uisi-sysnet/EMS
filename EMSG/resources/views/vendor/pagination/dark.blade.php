@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-center gap-1 flex-wrap">

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="px-2.5 py-1.5 rounded-lg text-sm text-text-500 border border-border-700 bg-surface-800 cursor-not-allowed select-none">
                &lt;
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="px-2.5 py-1.5 rounded-lg text-sm text-text-300 border border-border-600 bg-surface-800 hover:bg-surface-700 hover:text-text-100 transition select-none">
                &lt;
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="px-2 py-1.5 text-sm text-text-500 select-none">{{ $element }}</span>
            @endif

            {{-- Array of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="px-2.5 py-1.5 rounded-lg text-sm font-semibold text-text-100 bg-radar-600 border border-radar-500/50 select-none min-w-[2rem] text-center">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}"
                           class="px-2.5 py-1.5 rounded-lg text-sm text-text-300 border border-border-600 bg-surface-800 hover:bg-surface-700 hover:text-text-100 transition select-none min-w-[2rem] text-center">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="px-2.5 py-1.5 rounded-lg text-sm text-text-300 border border-border-600 bg-surface-800 hover:bg-surface-700 hover:text-text-100 transition select-none">
                &gt;
            </a>
        @else
            <span class="px-2.5 py-1.5 rounded-lg text-sm text-text-500 border border-border-700 bg-surface-800 cursor-not-allowed select-none">
                &gt;
            </span>
        @endif
    </nav>
@endif