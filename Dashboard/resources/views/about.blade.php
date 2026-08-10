@include('layouts.header')
@include('layouts.topbar')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css@5.5.1/github-markdown-dark.css">

<style>
    .thin-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .thin-scrollbar::-webkit-scrollbar-track {
        background: #1A1A1A;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb {
        background: #4B5563;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #6B7280;
    }
    .thin-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #4B5563 #1A1A1A;
    }

    /* Let the GitHub markdown theme sit inside our own dark card instead of
       bringing its own page background. */
    .markdown-body {
        background-color: transparent !important;
        font-family: inherit;
    }
    .markdown-body pre {
        background-color: #0D1117 !important;
    }
</style>

<main class="pt-20 pb-6 px-4 sm:px-6 max-w-6xl mx-auto w-full">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden">

        @php
            // about.md is rendered the same way GitHub renders a repo's
            // README.md — the file is the single source of truth for
            // this page's content (title included, as an H1).
            $aboutPath = resource_path('markdown/about.md');

            if (is_file($aboutPath)) {
                $aboutMarkdown = file_get_contents($aboutPath);
                $aboutHtml = \Illuminate\Support\Str::markdown($aboutMarkdown, [
                    'html_input' => 'strip',        // ignore raw HTML inside the .md file
                    'allow_unsafe_links' => false,
                ]);
            } else {
                $aboutHtml = '<p><em>about.md not found at resources/markdown/about.md</em></p>';
            }
        @endphp

        <article class="markdown-body thin-scrollbar overflow-x-auto p-5 sm:p-8 text-sm sm:text-base">
            {!! $aboutHtml !!}
        </article>

    </div>
</main>

@include('layouts.footer')