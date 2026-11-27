@extends('layouts.public')

@section('title', $post->title . ' · ' . config('app.name'))

@section('header-progress')
    {{-- Reading progress (JYU-535): width follows how far the article has been scrolled. --}}
    <div x-data="readingProgress()" class="h-[3px]" role="progressbar" aria-label="{{ __('public.post_reading_progress') }}"
        aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent">
        <div class="h-full bg-accent origin-left" :style="`width: ${percent}%`" style="width: 0"></div>
    </div>
@endsection

@section('content')
@php
    $loc = app()->getLocale();
    $category = $post->categories->first();
    $catTrans = $category?->translations->firstWhere('locale', $loc);
    $rendered = app(\App\Support\MarkdownRenderer::class)->render($post->body);
    $toc = \App\Support\TocBuilder::build($rendered);
    $bodyHtml = $toc['html'];
    $headings = $toc['headings'];
    $otherLanguages = $translations
        ->filter(fn ($p) => $p->status === 'published' && $p->locale !== $loc);
    $wasUpdated = $post->last_modified_at && $post->published_at
        && $post->last_modified_at->toDateString() > $post->published_at->toDateString();
@endphp

<div x-data="postReader({ headings: @js(array_column($headings, 'id')) })"
    class="max-w-[1200px] mx-auto px-5 md:px-8 pt-6 md:pt-12 pb-28 lg:pb-24 flex flex-wrap gap-16 items-start">
    <article class="grow-[999] basis-[560px] min-w-0 max-w-[720px]" id="post-top">
        <nav aria-label="{{ __('public.post_breadcrumb') }}" class="flex flex-wrap items-center gap-2 mb-4 md:mb-5 text-sm text-ink-3">
            <a href="{{ route('public.posts.index', $loc) }}" class="inline-flex items-center min-h-11 md:min-h-0 text-ink-3 hover:text-accent">{{ __('nav.posts') }}</a>
            @if($catTrans)
                <span aria-hidden="true">/</span>
                <a href="{{ route('public.categories.show', [$loc, $catTrans->slug]) }}" class="inline-flex items-center min-h-11 md:min-h-0 text-accent hover:text-accent-ink">{{ $catTrans->name }}</a>
            @endif
        </nav>

        <h1 class="m-0 mb-4 md:mb-5 font-serif text-[34px] md:text-5xl leading-tight font-medium tracking-tight">{{ $post->title }}</h1>

        <div class="flex flex-wrap items-center gap-x-[18px] gap-y-2 pb-5 text-sm text-ink-3">
            @if($post->published_at)
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat(__('public.post_date_long')) }}</time>
            @endif
            @if($wasUpdated)
                <time datetime="{{ $post->last_modified_at->toIso8601String() }}">{{ __('public.post_updated_on', ['date' => $post->last_modified_at->format('Y/m/d')]) }}</time>
            @endif
            <span>{{ __('public.post_reading_about', ['n' => $post->reading_time]) }}</span>
            <span>{{ __('public.post_views_n', ['n' => number_format($post->views_count)]) }}</span>
            @if($post->author)
                <span>{{ $post->author->name }}</span>
            @endif
        </div>

        @if($otherLanguages->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2.5 px-4 py-3.5 mb-8 md:mb-10 rounded-[var(--radius-card)] bg-paper-2 text-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="text-ink-2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                <span class="text-ink-2">{{ __('public.post_also_in') }}</span>
                @foreach($otherLanguages as $tr)
                    <a href="{{ route('public.posts.show', [$tr->locale, $tr->slug]) }}" hreflang="{{ $tr->locale }}" lang="{{ $tr->locale }}" class="inline-flex items-center min-h-9 px-3 rounded-full bg-card font-semibold text-ink hover:text-accent">{{ \App\Support\PublicNav::LOCALE_NAMES[$tr->locale] ?? $tr->locale }}</a>
                @endforeach
            </div>
        @endif

        @if($post->cover_image_path)
            <img src="{{ media_url($post->cover_image_path) }}" alt="" class="w-full rounded-[var(--radius-card)] mb-10 h-auto max-h-[400px] object-cover">
        @endif

        <div class="prose-blog post-body" data-post-body
            data-copy-label="{{ __('public.post_copy') }}" data-copied-label="{{ __('public.post_copied') }}" data-copy-code-label="{{ __('public.post_copy_code') }}">
            {!! $bodyHtml !!}
        </div>
        {{-- Apply the saved text size before first paint (see postReader in resources/js/post-reading.js). --}}
        <script>(function(){try{var s=parseInt(localStorage.getItem('post-font-step'),10);var sizes=[16,17,19,21,23];if(sizes[s]){document.querySelector('[data-post-body]').style.setProperty('--post-font-size',sizes[s]+'px');}}catch(e){}})();</script>

        {{-- Tags --}}
        @if($post->tags->isNotEmpty())
            <div class="mt-10 pt-6 border-t border-line flex flex-wrap items-center gap-2">
                @foreach($post->tags as $tag)
                    <x-ui.tag :tag="$tag" />
                @endforeach
            </div>
        @endif

        <div class="{{ $post->tags->isNotEmpty() ? 'mt-4' : 'mt-10 pt-6 border-t border-line' }} flex justify-end">
            <x-ui.button size="sm" pill data-copy-link @click="copyLink($el)" data-done-label="{{ __('public.post_link_copied') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>
                <span data-label>{{ __('public.post_copy_link') }}</span>
            </x-ui.button>
        </div>

        {{-- Backlinks：被以下文章 / 推文提及 --}}
        @include('public.partials.backlinks', ['backlinks' => $backlinks])

        {{-- Series navigation --}}
        @if(($seriesNav['previous'] ?? null) || ($seriesNav['next'] ?? null))
            <nav class="mt-12 pt-6 border-t border-line grid grid-cols-2 gap-3">
                @if($seriesNav['previous'])
                    <x-ui.card :href="route('public.posts.show', [$loc, $seriesNav['previous']->slug])" class="text-left">
                        <div class="text-[13px] text-ink-3 mb-1">← {{ __('public.prev_post') }}</div>
                        <div class="font-serif text-lg font-medium line-clamp-2">{{ $seriesNav['previous']->title }}</div>
                    </x-ui.card>
                @else
                    <span></span>
                @endif
                @if($seriesNav['next'])
                    <x-ui.card :href="route('public.posts.show', [$loc, $seriesNav['next']->slug])" class="text-right">
                        <div class="text-[13px] text-ink-3 mb-1">{{ __('public.next_post') }} →</div>
                        <div class="font-serif text-lg font-medium line-clamp-2">{{ $seriesNav['next']->title }}</div>
                    </x-ui.card>
                @endif
            </nav>
        @endif
    </article>

    {{-- Desktop sidebar: sticky table of contents + reading tools --}}
    <aside class="hidden lg:flex grow basis-[240px] max-w-[300px] sticky top-24 flex-col gap-7" aria-label="{{ __('public.post_tools') }}">
        @if(! empty($headings))
            <nav data-toc="desktop" aria-labelledby="toc-h" class="sticky">
                <x-ui.section-heading id="toc-h" size="label" class="mb-3">{{ __('public.post_toc_title') }}</x-ui.section-heading>
                <ol class="list-none m-0 p-0 border-l-2 border-paper-3 text-sm max-h-[60vh] overflow-y-auto">
                    @foreach($headings as $h)
                        <li>
                            <a href="#{{ $h['id'] }}" data-toc-id="{{ $h['id'] }}"
                                @click.prevent="scrollTo('{{ $h['id'] }}')"
                                :aria-current="active === '{{ $h['id'] }}' ? 'true' : null"
                                :class="active === '{{ $h['id'] }}' ? 'border-accent text-accent font-semibold' : 'border-transparent text-ink-2 hover:text-accent'"
                                class="block -ml-0.5 border-l-2 border-transparent py-1.5 {{ $h['level'] === 3 ? 'pl-7' : 'pl-3.5' }} pr-3 leading-snug text-ink-2">{{ $h['text'] }}</a>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <div class="flex gap-2">
            <x-ui.button icon aria-label="{{ __('public.post_back_to_top') }}" @click="toTop()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </x-ui.button>
            <div role="group" aria-label="{{ __('public.post_font_size') }}" class="flex flex-1">
                <x-ui.button class="flex-1 rounded-r-none" aria-label="{{ __('public.post_font_smaller') }}" @click="fontStep(-1)" ::disabled="step <= 0">A−</x-ui.button>
                <x-ui.button class="flex-1 rounded-l-none -ml-px" aria-label="{{ __('public.post_font_larger') }}" @click="fontStep(1)" ::disabled="step >= 4">A+</x-ui.button>
            </div>
        </div>
    </aside>

    {{-- Mobile: bottom toolbar + table-of-contents sheet --}}
    <div class="lg:hidden fixed inset-x-0 bottom-0 z-30 border-t border-line bg-card/95 backdrop-blur-sm pb-[env(safe-area-inset-bottom)]">
        <nav aria-label="{{ __('public.post_tools') }}" class="grid grid-flow-col auto-cols-fr px-2">
            @if(! empty($headings))
                <button type="button" aria-label="{{ __('public.post_open_toc') }}" @click="sheet = true" :aria-expanded="sheet"
                    class="flex flex-col items-center justify-center gap-0.5 min-h-13 text-xs text-ink-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    <span x-text="progressLabel">{{ __('public.post_toc_title') }}</span>
                </button>
            @endif
            <button type="button" aria-label="{{ __('public.post_font_smaller') }}" @click="fontStep(-1)" :disabled="step <= 0"
                class="flex flex-col items-center justify-center gap-0.5 min-h-13 text-xs text-ink-2 disabled:opacity-40"><span class="font-serif text-base leading-5">A−</span>{{ __('public.post_font_size') }}</button>
            <button type="button" aria-label="{{ __('public.post_font_larger') }}" @click="fontStep(1)" :disabled="step >= 4"
                class="flex flex-col items-center justify-center gap-0.5 min-h-13 text-xs text-ink-2 disabled:opacity-40"><span class="font-serif text-lg leading-5">A+</span>{{ __('public.post_font_size') }}</button>
            <button type="button" aria-label="{{ __('public.post_back_to_top') }}" @click="toTop()"
                class="flex flex-col items-center justify-center gap-0.5 min-h-13 text-xs text-ink-2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                <span>{{ __('public.post_back_to_top') }}</span>
            </button>
        </nav>
    </div>

    @if(! empty($headings))
        <div x-show="sheet" x-cloak class="lg:hidden fixed inset-0 z-40 bg-ink/30" @click="sheet = false" x-transition.opacity></div>
        <div x-show="sheet" x-cloak role="dialog" aria-modal="true" aria-labelledby="toc-sheet-h" data-toc="mobile"
            @keydown.escape.window="sheet = false" x-transition:enter-start="translate-y-full" x-transition:leave-end="translate-y-full"
            class="lg:hidden fixed inset-x-0 bottom-0 z-50 max-h-[75vh] overflow-y-auto rounded-t-[18px] bg-card shadow-[0_-12px_40px_rgba(42,34,28,0.22)] px-5 pt-2.5 pb-[calc(1rem+env(safe-area-inset-bottom))] transition-transform">
            <div class="w-10 h-1 rounded-full bg-paper-3 mx-auto mb-3.5" aria-hidden="true"></div>
            <div class="flex items-center justify-between mb-1.5">
                <h2 id="toc-sheet-h" class="m-0 font-sans text-base font-bold tracking-normal">{{ __('public.post_toc_title') }}</h2>
                <div class="flex items-center gap-1">
                    <span class="text-[13px] text-ink-3" x-text="progressLabel" data-progress-template="{{ __('public.post_toc_progress') }}">{{ __('public.post_toc_progress', ['n' => 1, 'm' => count($headings)]) }}</span>
                    <x-ui.button variant="ghost" icon aria-label="{{ __('public.post_close') }}" @click="sheet = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </x-ui.button>
                </div>
            </div>
            <ol class="list-none m-0 p-0 text-[15px]">
                @foreach($headings as $h)
                    <li>
                        <a href="#{{ $h['id'] }}" data-toc-id="{{ $h['id'] }}"
                            @click.prevent="sheet = false; scrollTo('{{ $h['id'] }}')"
                            :aria-current="active === '{{ $h['id'] }}' ? 'true' : null"
                            :class="active === '{{ $h['id'] }}' ? 'text-accent font-bold' : 'text-ink'"
                            class="flex items-center min-h-11 {{ $h['level'] === 3 ? 'pl-4' : '' }}">{{ $h['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
@endsection
