@extends('layouts.public')

@section('title', __('public.tweets') . ' · ' . config('app.name'))

@section('content')
<div class="max-w-3xl mx-auto px-6 py-12"
    x-data="infiniteScroll({
        url: '{{ route('public.tweets.index', app()->getLocale()) }}',
        startPage: {{ $tweets->currentPage() }},
        hasMore: {{ $tweets->hasMorePages() ? 'true' : 'false' }},
        listSelector: '#tweet-list',
    })">
    <header class="mb-10">
        <h1 class="font-serif text-3xl md:text-4xl font-semibold">@lang('nav.tweets')</h1>
        <p class="text-ink-3 text-sm mt-2 font-mono">{{ __('public.tweets_total', ['n' => $tweets->total()]) }}</p>
    </header>

    @if($tweets->isEmpty())
        <p class="text-ink-3">{{ __('public.no_tweets') }}</p>
    @else
        <div id="tweet-list" class="space-y-4 border-l border-line ml-3 pl-6 relative">
            @foreach($tweets as $tweet)
                <div class="relative">
                    <span class="absolute -left-[1.85rem] top-3 w-2 h-2 rounded-full bg-accent border-2 border-paper"></span>
                    <x-tweet-card :tweet="$tweet" />
                </div>
            @endforeach
        </div>

        {{-- Infinite-scroll sentinel --}}
        <div x-ref="sentinel" x-init="setupObserver($refs.sentinel)" class="py-8 text-center text-xs text-ink-3 font-mono">
            <span x-show="loading" x-cloak>{{ __('public.loading_more') }}</span>
            <span x-show="!loading && hasMore" x-cloak>↓</span>
            <span x-show="!loading && !hasMore" x-cloak>{{ __('public.no_more') }}</span>
        </div>
    @endif
</div>
@endsection
