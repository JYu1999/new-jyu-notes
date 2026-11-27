@extends('layouts.public')

@section('title', __('public.tweets') . ' · ' . config('app.name'))

@section('content')
@php
    $loc = app()->getLocale();
    // Build a /tweets URL that keeps the other filters.
    $filterUrl = function (array $change) use ($loc, $filters) {
        $params = array_filter([...$filters, ...$change], fn ($v) => $v !== null && $v !== false && $v !== '');

        return route('public.tweets.index', ['locale' => $loc, ...$params]);
    };
    $monthLabel = fn (string $ym) => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $ym)->translatedFormat(__('public.tweets_month_format'));
@endphp
<div class="max-w-[1200px] mx-auto px-4 md:px-8 pt-8 md:pt-12 pb-24 flex flex-col md:flex-row gap-8 md:gap-14 items-start">

    {{-- Filters: above the list on phones, sticky sidebar on desktop --}}
    <aside data-tweet-filters class="w-full md:w-auto md:basis-[260px] md:max-w-[280px] md:shrink-0 md:sticky md:top-24 flex flex-col gap-6 md:gap-7">
        <div>
            <h1 class="m-0 mb-1.5 font-serif text-[34px] md:text-[40px] font-medium tracking-tight">{{ __('nav.tweets') }}</h1>
            <p class="m-0 text-[15px] text-ink-2">{{ __('public.tweets_intro') }}</p>
        </div>

        @if($tags->isNotEmpty())
            <nav aria-label="{{ __('public.tweets_filter_by_tag') }}">
                <x-ui.section-heading as="h2" size="label" class="mb-2.5">{{ __('public.tags') }}</x-ui.section-heading>
                <div class="flex md:flex-wrap gap-2 overflow-x-auto scrollbar-none -mx-4 px-4 md:mx-0 md:px-0">
                    <a href="{{ $filterUrl(['tag' => null]) }}" @if(! $filters['tag']) aria-current="true" @endif
                        class="ui-chip ui-chip-md flex-none min-h-11 md:min-h-9 {{ $filters['tag'] ? 'ui-chip-neutral' : 'ui-chip-pressed' }}">{{ __('public.tweets_all') }}</a>
                    @foreach($tags as $tag)
                        @php $slug = $tag->slug($loc); $active = $filters['tag'] === $slug; @endphp
                        <a href="{{ $filterUrl(['tag' => $slug]) }}" @if($active) aria-current="true" @endif
                            @if(! $active && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $tag->color)) style="--tag-color: {{ $tag->color }}" @endif
                            class="ui-chip ui-chip-md flex-none min-h-11 md:min-h-9 {{ $active ? 'ui-chip-pressed' : ($tag->color ? 'tag-chip' : 'ui-chip-neutral') }}">#{{ $tag->name($loc) }}</a>
                    @endforeach
                </div>
            </nav>
        @endif

        <form method="GET" action="{{ route('public.tweets.index', $loc) }}">
            @foreach(['tag', 'month'] as $keep)
                @if($filters[$keep])<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
            @endforeach
            <label class="flex items-center gap-2.5 min-h-11 text-sm text-ink-2 cursor-pointer">
                <input type="checkbox" name="media" value="1" @checked($filters['media']) onchange="this.form.submit()"
                    class="size-[18px] accent-accent">
                {{ __('public.tweets_media_only') }}
            </label>
            <noscript><x-ui.button type="submit" size="sm">OK</x-ui.button></noscript>
        </form>

        @if($months->isNotEmpty())
            {{-- Archive: list on desktop, a compact picker on phones --}}
            <nav aria-label="{{ __('public.tweets_by_month') }}" class="hidden md:block">
                <x-ui.section-heading as="h2" size="label" class="mb-1.5">{{ __('public.tweets_archive') }}</x-ui.section-heading>
                @foreach($months as $m)
                    @php $active = $filters['month'] === $m->month; @endphp
                    <a href="{{ $filterUrl(['month' => $active ? null : $m->month]) }}" @if($active) aria-current="true" @endif
                        class="flex justify-between py-1.5 text-sm {{ $active ? 'text-ink font-semibold' : 'text-ink-2 hover:text-accent' }}">
                        <span>{{ $monthLabel($m->month) }}</span><span class="font-mono text-ink-3">{{ $m->count }}</span>
                    </a>
                @endforeach
            </nav>
            <label class="md:hidden flex items-center gap-3 text-sm text-ink-2">
                <span class="font-bold text-[13px] tracking-[0.06em] text-ink-3">{{ __('public.tweets_archive') }}</span>
                <select aria-label="{{ __('public.tweets_pick_month') }}" onchange="window.location.href = this.value"
                    class="flex-1 min-h-11 px-3 rounded-[var(--radius-control)] border border-line-2 bg-card text-ink">
                    <option value="{{ $filterUrl(['month' => null]) }}">{{ __('public.tweets_all_months') }}</option>
                    @foreach($months as $m)
                        <option value="{{ $filterUrl(['month' => $m->month]) }}" @selected($filters['month'] === $m->month)>{{ $monthLabel($m->month) }} ({{ $m->count }})</option>
                    @endforeach
                </select>
            </label>
        @endif
    </aside>

    {{-- List --}}
    <section data-tweet-list aria-label="{{ __('nav.tweets') }}" class="w-full md:flex-1 min-w-0 max-w-[680px]"
        x-data="tweetList()">
        @if($tweets->isEmpty())
            <div class="py-10 text-center text-ink-3">
                <p class="m-0 mb-3">{{ $filters['tag'] || $filters['month'] || $filters['media'] ? __('public.tweets_empty_filtered') : __('public.no_tweets') }}</p>
                @if($filters['tag'] || $filters['month'] || $filters['media'])
                    <a href="{{ route('public.tweets.index', $loc) }}" class="text-accent hover:text-accent-ink">{{ __('public.tweets_clear_filters') }}</a>
                @endif
            </div>
        @else
            <div x-ref="list">
                @include('public.tweets._items', ['tweets' => $tweets])
            </div>

            <p x-show="error" x-cloak role="alert" class="py-3 text-center text-sm text-danger">{{ __('public.feed_error') }}</p>

            @if($tweets->hasMorePages())
                <x-ui.button :href="$tweets->nextPageUrl()" size="lg" class="w-full mt-6" data-load-older
                    x-ref="more" @click.prevent="loadOlder($el)" ::aria-disabled="loading">
                    {{ __('public.tweets_load_older') }}
                </x-ui.button>
            @endif
        @endif
    </section>
</div>
@endsection
