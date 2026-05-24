@extends('layouts.public')

@section('title', __('public.search') . ' · ' . config('app.name'))

@section('content')
<div class="max-w-3xl mx-auto px-6 py-12"
    x-data="liveFilter({ url: '{{ route('public.search', app()->getLocale()) }}', target: '#search-results' })">
    <header class="mb-8">
        <h1 class="font-serif text-3xl md:text-4xl font-semibold mb-4">{{ __('public.search') }}</h1>

        <form @input.debounce.300ms="submit($event)" @change="submit($event)"
            class="flex flex-col sm:flex-row gap-3">
            <input
                type="search" name="q" value="{{ $q }}"
                placeholder="{{ __('public.search_placeholder') }}" autofocus
                class="flex-1 px-4 py-2.5 bg-card border border-line rounded-md focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft">
            <select name="type"
                class="bg-card border border-line rounded-md px-3 py-2.5 focus:border-accent focus:outline-none">
                <option value="all" {{ $type === 'all' ? 'selected' : '' }}>{{ __('public.search_type_all') }}</option>
                <option value="post" {{ $type === 'post' ? 'selected' : '' }}>{{ __('public.search_type_post') }}</option>
                <option value="tweet" {{ $type === 'tweet' ? 'selected' : '' }}>{{ __('public.search_type_tweet') }}</option>
            </select>
        </form>

        <div x-show="loading" x-cloak class="text-xs font-mono text-ink-3 mt-3 animate-pulse">{{ __('public.searching') }}</div>
    </header>

    <div id="search-results">
        @include('public._search-results')
    </div>
</div>
@endsection
