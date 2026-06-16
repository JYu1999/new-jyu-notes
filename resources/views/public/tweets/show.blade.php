@extends('layouts.public')

@section('title', 'Tweet · ' . config('app.name'))

@section('content')
<div class="max-w-2xl mx-auto px-6 py-12">
    <a href="{{ route('public.tweets.index', app()->getLocale()) }}" class="text-sm text-accent hover:text-accent-ink font-mono mb-6 inline-block">{{ __('public.back_to_tweets') }}</a>
    <x-tweet-card :tweet="$tweet" />
    @include('public.partials.backlinks', ['backlinks' => $backlinks])
</div>
@endsection
