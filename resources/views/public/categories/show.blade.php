@extends('layouts.public')

@section('title', $category->name(app()->getLocale()) . ' · ' . config('app.name'))

@section('content')
<div class="max-w-5xl mx-auto px-6 py-12">
    <header class="mb-10">
        <div class="text-xs uppercase tracking-widest text-ink-3 font-mono mb-2">分類 / 系列</div>
        <h1 class="font-serif text-3xl md:text-4xl font-semibold">{{ $category->name(app()->getLocale()) }}</h1>
        @if($category->description(app()->getLocale()))
            <p class="text-ink-2 mt-3 max-w-2xl">{{ $category->description(app()->getLocale()) }}</p>
        @endif
        <p class="text-ink-3 text-sm mt-3 font-mono">共 {{ $posts->total() }} 篇</p>
    </header>

    @if($posts->isEmpty())
        <p class="text-ink-3">這個分類目前沒有文章。</p>
    @else
        <div class="space-y-2">
            @foreach($posts as $i => $post)
                <article class="flex items-baseline gap-4 py-4 border-b border-line group">
                    <span class="font-mono text-xs text-ink-3 w-10 text-right flex-shrink-0">
                        @if($category->sort_method === 'manual' && isset($post->pivot) && $post->pivot->order_in_category !== null)
                            #{{ $post->pivot->order_in_category }}
                        @else
                            {{ $post->published_at?->format('m/d') }}
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-serif text-lg font-medium">
                            <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}" class="text-ink hover:text-accent">
                                {{ $post->title }}
                            </a>
                        </h3>
                        @if($post->excerpt)
                            <p class="text-sm text-ink-3 mt-1 line-clamp-1">{{ $post->excerpt }}</p>
                        @endif
                    </div>
                    <span class="text-xs text-ink-3 font-mono">{{ $post->views_count }} v</span>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
