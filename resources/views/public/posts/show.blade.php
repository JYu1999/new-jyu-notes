@extends('layouts.public')

@section('title', $post->title . ' · ' . config('app.name'))

@section('content')
@php
    $loc = app()->getLocale();
    $catTrans = $post->categories->first()?->translations->firstWhere('locale', $loc);
    $rendered = app(\App\Support\MarkdownRenderer::class)->render($post->body);
    $toc = \App\Support\TocBuilder::build($rendered);
    $bodyHtml = $toc['html'];
    $headings = $toc['headings'];
@endphp

<div class="max-w-6xl mx-auto px-6 py-12 grid lg:grid-cols-[1fr_220px] gap-12">
    {{-- Main article --}}
    <article class="max-w-3xl">
        @if($catTrans)
            <div class="text-[11px] uppercase tracking-widest text-accent font-mono mb-4">
                <a href="{{ route('public.categories.show', [$loc, $catTrans->slug]) }}" class="hover:text-accent-ink">{{ $catTrans->name }}</a>
            </div>
        @endif

        <h1 class="font-serif text-3xl md:text-4xl font-semibold leading-tight tracking-tight mb-5">{{ $post->title }}</h1>

        <div class="flex items-center gap-4 text-xs text-ink-3 font-mono mb-8">
            <span>{{ $post->published_at?->format('Y/m/d') }}</span>
            <span>·</span>
            <span>{{ $post->views_count }} {{ __('public.views') }}</span>
            @if($post->author)
                <span>·</span>
                <span>{{ $post->author->name }}</span>
            @endif
        </div>

        @if($post->cover_image_path)
            <img src="{{ media_url($post->cover_image_path) }}" alt="" class="w-full rounded-lg mb-10 h-auto max-h-[400px] object-cover">
        @endif

        <div class="prose-blog">
            {!! $bodyHtml !!}
        </div>

        {{-- Tags --}}
        @if($post->tags->isNotEmpty())
            <div class="mt-12 pt-6 border-t border-line flex flex-wrap gap-2">
                @foreach($post->tags as $tag)
                    @php $tt = $tag->translations->firstWhere('locale', $loc); @endphp
                    @if($tt)
                        <a href="{{ route('public.tags.show', [$loc, $tt->slug]) }}" class="font-mono text-xs px-3 py-1.5 bg-card border border-line rounded-full text-ink-2 hover:text-accent hover:border-accent">
                            #{{ $tt->name }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Series navigation --}}
        @if(($seriesNav['previous'] ?? null) || ($seriesNav['next'] ?? null))
            <nav class="mt-12 pt-6 border-t border-line grid grid-cols-2 gap-4">
                @if($seriesNav['previous'])
                    <a href="{{ route('public.posts.show', [$loc, $seriesNav['previous']->slug]) }}" class="block p-4 border border-line rounded hover:border-accent text-left">
                        <div class="text-xs text-ink-3 font-mono mb-1">← {{ __('public.prev_post') }}</div>
                        <div class="font-serif text-sm font-medium line-clamp-2">{{ $seriesNav['previous']->title }}</div>
                    </a>
                @else
                    <span></span>
                @endif
                @if($seriesNav['next'])
                    <a href="{{ route('public.posts.show', [$loc, $seriesNav['next']->slug]) }}" class="block p-4 border border-line rounded hover:border-accent text-right">
                        <div class="text-xs text-ink-3 font-mono mb-1">{{ __('public.next_post') }} →</div>
                        <div class="font-serif text-sm font-medium line-clamp-2">{{ $seriesNav['next']->title }}</div>
                    </a>
                @endif
            </nav>
        @endif
    </article>

    {{-- Table of Contents (sidebar, sticky, scrollspy) --}}
    @if(! empty($headings))
        <aside class="hidden lg:block">
            <nav x-data="postToc()" x-init="init()"
                class="sticky top-20 text-sm border-l border-line pl-4">
                <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-3">{{ __('public.toc') }}</div>
                <ul class="space-y-1.5">
                    @foreach($headings as $h)
                        <li class="{{ $h['level'] === 3 ? 'pl-3' : '' }}">
                            <a href="#{{ $h['id'] }}"
                                data-toc-id="{{ $h['id'] }}"
                                @click.prevent="scrollTo('{{ $h['id'] }}')"
                                :class="active === '{{ $h['id'] }}' ? 'text-accent font-medium' : 'text-ink-3 hover:text-accent'"
                                class="block text-xs leading-snug py-0.5 truncate transition-colors">
                                {{ $h['text'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>
    @endif
</div>
@endsection
