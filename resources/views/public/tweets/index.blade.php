@extends('layouts.public')

@section('title', __('public.tweets') . ' · ' . config('app.name'))

@section('content')
<div class="max-w-3xl mx-auto px-6 py-12">
    <header class="mb-10">
        <h1 class="font-serif text-3xl md:text-4xl font-semibold">@lang('nav.tweets')</h1>
        <p class="text-ink-3 text-sm mt-2 font-mono">{{ __('public.tweets_total', ['n' => $tweets->total()]) }}</p>
    </header>

    @if($tweets->isEmpty())
        <p class="text-ink-3">{{ __('public.no_tweets') }}</p>
    @else
        {{-- Group by year-month --}}
        @php
            $grouped = $tweets->getCollection()->groupBy(fn($t) => $t->published_at?->format('Y / m') ?? '—');
        @endphp

        <div class="space-y-10">
            @foreach($grouped as $month => $items)
                <section>
                    <h2 class="font-mono text-xs uppercase tracking-widest text-ink-3 mb-4 pb-2 border-b border-line">{{ $month }}</h2>
                    <div class="space-y-4 border-l border-line ml-3 pl-6 relative">
                        @foreach($items as $tweet)
                            <div class="relative">
                                <span class="absolute -left-[1.85rem] top-3 w-2 h-2 rounded-full bg-accent border-2 border-paper"></span>
                                <x-tweet-card :tweet="$tweet" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $tweets->links() }}
        </div>
    @endif
</div>
@endsection
