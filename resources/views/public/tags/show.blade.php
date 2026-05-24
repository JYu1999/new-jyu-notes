@extends('layouts.public')

@section('title', '#' . $tag->name(app()->getLocale()) . ' · ' . config('app.name'))

@section('content')
<div class="max-w-6xl mx-auto px-6 py-12">
    <header class="mb-10">
        <div class="text-xs uppercase tracking-widest text-ink-3 font-mono mb-2">{{ __('public.tag_subtitle') }}</div>
        <h1 class="font-serif text-3xl md:text-4xl font-semibold">
            #{{ $tag->name(app()->getLocale()) }}
        </h1>
    </header>

    @if($posts->isNotEmpty())
        <section class="mb-14">
            <h2 class="font-serif text-xl font-semibold mb-6">{{ __('public.tag_posts') }} ({{ $posts->total() }})</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($posts as $post)
                    <x-post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-6">{{ $posts->withQueryString()->links() }}</div>
        </section>
    @endif

    @if($tweets->isNotEmpty())
        <section>
            <h2 class="font-serif text-xl font-semibold mb-6">{{ __('public.tag_tweets') }} ({{ $tweets->total() }})</h2>
            <div class="space-y-4 max-w-2xl">
                @foreach($tweets as $tweet)
                    <a href="{{ route('public.tweets.show', [app()->getLocale(), $tweet->id]) }}" class="block hover:opacity-80 transition-opacity">
                        <x-tweet-card :tweet="$tweet" preview />
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $tweets->withQueryString()->links() }}</div>
        </section>
    @endif

    @if($posts->isEmpty() && $tweets->isEmpty())
        <p class="text-ink-3">{{ __('public.no_tag_content') }}</p>
    @endif
</div>
@endsection
