@extends('layouts.public')

@section('title', '搜尋 · ' . config('app.name'))

@section('content')
<div class="max-w-3xl mx-auto px-6 py-12">
    <header class="mb-8">
        <h1 class="font-serif text-3xl md:text-4xl font-semibold mb-4">搜尋</h1>
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input
                type="search"
                name="q"
                value="{{ $q }}"
                placeholder="輸入關鍵字..."
                autofocus
                class="flex-1 px-4 py-2.5 bg-card border border-line rounded-md focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft"
            >
            <select name="type" class="bg-card border border-line rounded-md px-3 py-2.5 focus:border-accent focus:outline-none">
                <option value="all" {{ $type === 'all' ? 'selected' : '' }}>全部</option>
                <option value="post" {{ $type === 'post' ? 'selected' : '' }}>Posts</option>
                <option value="tweet" {{ $type === 'tweet' ? 'selected' : '' }}>Tweets</option>
            </select>
            <button class="bg-accent text-white px-5 py-2.5 rounded-md hover:bg-accent-ink font-medium">搜尋</button>
        </form>
    </header>

    @if($q)
        @php
            $postsCount = $results['posts']->count();
            $tweetsCount = $results['tweets']->count();
        @endphp
        <p class="text-sm text-ink-3 font-mono mb-6">
            找到 {{ $postsCount + $tweetsCount }} 個結果
            （文章 {{ $postsCount }}，tweets {{ $tweetsCount }}）
        </p>

        @if($results['posts']->isNotEmpty())
            <section class="mb-10">
                <h2 class="font-serif text-xl font-semibold mb-4">文章</h2>
                <div class="space-y-4">
                    @foreach($results['posts'] as $post)
                        <article class="border border-line rounded-md p-5 bg-card hover:border-accent transition-colors">
                            <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}" class="block">
                                <h3 class="font-serif text-lg font-medium text-ink hover:text-accent">{{ $post->title }}</h3>
                                @if($post->excerpt)
                                    <p class="text-sm text-ink-2 mt-2 line-clamp-2">{{ $post->excerpt }}</p>
                                @endif
                                <div class="mt-3 text-xs text-ink-3 font-mono">
                                    {{ $post->published_at?->format('Y/m/d') }} · {{ $post->views_count }} views
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($results['tweets']->isNotEmpty())
            <section>
                <h2 class="font-serif text-xl font-semibold mb-4">Tweets</h2>
                <div class="space-y-4">
                    @foreach($results['tweets'] as $tweet)
                        <a href="{{ route('public.tweets.show', [app()->getLocale(), $tweet->id]) }}" class="block">
                            <x-tweet-card :tweet="$tweet" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($postsCount === 0 && $tweetsCount === 0)
            <p class="text-ink-3 text-center py-12">沒有找到相關內容。</p>
        @endif
    @endif
</div>
@endsection
