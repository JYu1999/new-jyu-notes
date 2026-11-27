<!DOCTYPE html>
@php $theme = \App\Support\ThemePreference::fromRequest(request()); @endphp
<html lang="{{ app()->getLocale() }}" @if($theme) data-theme="{{ $theme }}" data-theme-choice="{{ $theme }}" @endif>
<head>
    <meta charset="UTF-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @include('partials.google-analytics')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink">
    @php
        // Layout reads $availableLocales if a controller sets it (e.g. post show).
        // Default: all supported locales.
        $availableLocales = $availableLocales ?? \App\Models\Post::SUPPORTED_LOCALES;
        $navItems = \App\Support\PublicNav::items(app()->getLocale(), request());
    @endphp
    @include('partials.public-header')

    <main class="min-h-[calc(100vh-4rem-6rem)]">
        @yield('content')
    </main>

    @include('partials.public-footer')

    {{-- Shared fullscreen image lightbox --}}
    <div x-data
        x-show="$store.lightbox.open"
        x-cloak
        @keydown.escape.window="$store.lightbox.close()"
        @click="$store.lightbox.close()"
        class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4 cursor-zoom-out"
        style="display: none;">
        <button type="button" @click.stop="$store.lightbox.close()"
            class="absolute top-4 right-4 text-white/80 hover:text-white text-3xl leading-none">&times;</button>
        <img :src="$store.lightbox.src" :alt="$store.lightbox.alt"
            @click.stop
            class="max-w-full max-h-full object-contain rounded">
    </div>
</body>
</html>
