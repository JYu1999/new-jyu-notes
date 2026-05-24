@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<header class="mb-8">
    <h1 class="font-serif text-2xl font-semibold">儀表板</h1>
    <p class="text-sm text-ink-3 mt-1">歡迎回來，{{ auth()->user()->name }}</p>
</header>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    @php
        $cards = [
            ['label' => 'Posts (Published)', 'value' => $stats['posts_published'], 'href' => route('admin.posts.index', ['status' => 'published'])],
            ['label' => 'Posts (Draft)', 'value' => $stats['posts_draft'], 'href' => route('admin.posts.index', ['status' => 'draft'])],
            ['label' => 'Tweets (Published)', 'value' => $stats['tweets_published'], 'href' => route('admin.tweets.index', ['status' => 'published'])],
            ['label' => 'Tweets (Draft)', 'value' => $stats['tweets_draft'], 'href' => route('admin.tweets.index', ['status' => 'draft'])],
        ];
    @endphp
    @foreach($cards as $c)
        <a href="{{ $c['href'] }}" class="bg-card border border-line rounded-md p-5 hover:border-accent">
            <div class="text-xs uppercase tracking-widest text-ink-3 font-mono mb-2">{{ $c['label'] }}</div>
            <div class="font-serif text-3xl font-semibold">{{ $c['value'] }}</div>
        </a>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-8">
    <section>
        <div class="flex items-baseline justify-between mb-4">
            <h2 class="font-serif text-lg font-semibold">最近文章</h2>
            <a href="{{ route('admin.posts.index') }}" class="text-xs text-accent font-mono hover:text-accent-ink">全部 →</a>
        </div>
        <div class="bg-card border border-line rounded-md divide-y divide-line">
            @forelse($recentPosts as $p)
                <a href="{{ route('admin.posts.edit', $p) }}" class="block p-3 hover:bg-paper-2">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-medium truncate">{{ $p->title ?: '(no title)' }}</span>
                        <span class="text-xs text-ink-3 font-mono whitespace-nowrap">{{ $p->locale }} · {{ $p->status }}</span>
                    </div>
                    <div class="text-xs text-ink-3 mt-1 font-mono">{{ $p->updated_at->diffForHumans() }}</div>
                </a>
            @empty
                <p class="p-4 text-sm text-ink-3">尚無資料</p>
            @endforelse
        </div>
    </section>

    <section>
        <div class="flex items-baseline justify-between mb-4">
            <h2 class="font-serif text-lg font-semibold">最近 Tweets</h2>
            <a href="{{ route('admin.tweets.index') }}" class="text-xs text-accent font-mono hover:text-accent-ink">全部 →</a>
        </div>
        <div class="bg-card border border-line rounded-md divide-y divide-line">
            @forelse($recentTweets as $t)
                <a href="{{ route('admin.tweets.edit', $t) }}" class="block p-3 hover:bg-paper-2">
                    <div class="text-sm line-clamp-2">{{ $t->body }}</div>
                    <div class="text-xs text-ink-3 mt-1 font-mono">{{ $t->locale }} · {{ $t->status }} · {{ $t->updated_at->diffForHumans() }}</div>
                </a>
            @empty
                <p class="p-4 text-sm text-ink-3">尚無資料</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
