@extends('layouts.public')

@section('title', $post->title . ' · ' . config('app.name'))

@section('content')
<article class="max-w-3xl mx-auto px-6 py-12">
    {{-- Translation switcher --}}
    @if($translations->count() > 1)
        <div class="mb-6 flex items-center gap-3 text-xs font-mono text-ink-3">
            <span>其他語言：</span>
            @foreach($translations as $loc => $t)
                @if($loc !== $post->locale && $t->status === 'published')
                    <a href="{{ route('public.posts.show', [$loc, $t->slug]) }}" class="text-accent hover:text-accent-ink uppercase">{{ $loc }}</a>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Category badge --}}
    @if($post->categories->isNotEmpty())
        @php $cat = $post->categories->first(); $catSlug = $cat->slug(app()->getLocale()); @endphp
        <div class="text-[11px] uppercase tracking-widest text-accent font-mono mb-4">
            @if($catSlug)
                <a href="{{ route('public.categories.show', [app()->getLocale(), $catSlug]) }}" class="hover:text-accent-ink">{{ $cat->name(app()->getLocale()) }}</a>
            @else
                {{ $cat->name(app()->getLocale()) }}
            @endif
        </div>
    @endif

    <h1 class="font-serif text-3xl md:text-4xl font-semibold leading-tight tracking-tight mb-5">{{ $post->title }}</h1>

    <div class="flex items-center gap-4 text-xs text-ink-3 font-mono mb-8">
        <span>{{ $post->published_at?->format('Y年 m月 d日') }}</span>
        <span>·</span>
        <span>{{ $post->views_count }} views</span>
        @if($post->author)
            <span>·</span>
            <span>{{ $post->author->name }}</span>
        @endif
    </div>

    @if($post->cover_image_path)
        <img src="{{ asset('storage/' . $post->cover_image_path) }}" alt="" class="w-full rounded-lg mb-10 h-auto max-h-[400px] object-cover">
    @endif

    <div class="prose-blog">
        {!! app(\App\Support\MarkdownRenderer::class)->render($post->body) !!}
    </div>

    {{-- Tags --}}
    @if($post->tags->isNotEmpty())
        <div class="mt-12 pt-6 border-t border-line flex flex-wrap gap-2">
            @foreach($post->tags as $tag)
                @php $tagSlug = $tag->slug(app()->getLocale()); @endphp
                @if($tagSlug)
                    <a href="{{ route('public.tags.show', [app()->getLocale(), $tagSlug]) }}" class="font-mono text-xs px-3 py-1.5 bg-card border border-line rounded-full text-ink-2 hover:text-accent hover:border-accent">
                        #{{ $tag->name(app()->getLocale()) }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Series navigation --}}
    @if(($seriesNav['previous'] ?? null) || ($seriesNav['next'] ?? null))
        <nav class="mt-12 pt-6 border-t border-line grid grid-cols-2 gap-4">
            @if($seriesNav['previous'])
                <a href="{{ route('public.posts.show', [app()->getLocale(), $seriesNav['previous']->slug]) }}" class="block p-4 border border-line rounded hover:border-accent text-left">
                    <div class="text-xs text-ink-3 font-mono mb-1">← 上一篇</div>
                    <div class="font-serif text-sm font-medium line-clamp-2">{{ $seriesNav['previous']->title }}</div>
                </a>
            @else
                <span></span>
            @endif
            @if($seriesNav['next'])
                <a href="{{ route('public.posts.show', [app()->getLocale(), $seriesNav['next']->slug]) }}" class="block p-4 border border-line rounded hover:border-accent text-right">
                    <div class="text-xs text-ink-3 font-mono mb-1">下一篇 →</div>
                    <div class="font-serif text-sm font-medium line-clamp-2">{{ $seriesNav['next']->title }}</div>
                </a>
            @endif
        </nav>
    @endif
</article>
@endsection
