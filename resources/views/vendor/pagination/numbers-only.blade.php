@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex w-full justify-end">
        <div class="flex items-center justify-end">
            <span class="inline-flex rtl:flex-row-reverse shadow-sm rounded-md">

                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="inline-flex items-center px-2 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-300 cursor-not-allowed rounded-l-md leading-5 dark:bg-zinc-800 dark:border-zinc-600 dark:text-zinc-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-2 py-2 text-sm font-medium text-zinc-500 bg-white border border-zinc-300 rounded-l-md leading-5 transition duration-150 hover:text-zinc-600 focus:border-blue-300 focus:outline-none focus:ring focus:ring-blue-300 active:bg-zinc-100 active:text-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-300 dark:active:bg-zinc-700 dark:focus:border-blue-800" aria-label="{{ __('pagination.previous') }}">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true">
                            <span class="-ml-px inline-flex cursor-default items-center border border-zinc-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $element }}</span>
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="-ml-px inline-flex cursor-default items-center border border-zinc-300 bg-zinc-200 px-4 py-2 text-sm font-medium leading-5 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200">{{ $page }}</span>
                                </span>
                            @else
                                <a href="{{ $url }}" class="-ml-px inline-flex items-center border border-zinc-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-zinc-700 transition duration-150 hover:bg-zinc-50 hover:text-zinc-900 focus:border-blue-300 focus:outline-none focus:ring focus:ring-blue-300 active:bg-zinc-100 active:text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-200 dark:active:bg-zinc-700 dark:focus:border-blue-800" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="-ml-px inline-flex items-center rounded-r-md border border-zinc-300 bg-white px-2 py-2 text-sm font-medium leading-5 text-zinc-500 transition duration-150 hover:text-zinc-600 focus:border-blue-300 focus:outline-none focus:ring focus:ring-blue-300 active:bg-zinc-100 active:text-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-300 dark:active:bg-zinc-700 dark:focus:border-blue-800" aria-label="{{ __('pagination.next') }}">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="-ml-px inline-flex cursor-not-allowed items-center rounded-r-md border border-zinc-300 bg-white px-2 py-2 text-sm font-medium leading-5 text-zinc-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
