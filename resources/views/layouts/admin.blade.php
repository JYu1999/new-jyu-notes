<!DOCTYPE html>
@php $theme = \App\Support\ThemePreference::fromRequest(request()); @endphp
<html lang="zh" @if($theme) data-theme="{{ $theme }}" data-theme-choice="{{ $theme }}" @endif>
<head>
    <meta charset="UTF-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="media-base" content="{{ rtrim(media_url(''), '/') }}">
    <title>@yield('title', 'Admin') · JYu's Blog</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink min-h-screen" x-data="{ sidebar: false }">
    {{-- Mobile header --}}
    <div class="md:hidden sticky top-0 z-30 bg-card border-b border-line h-14 flex items-center px-2">
        <button type="button" @click="sidebar = true" class="inline-flex items-center justify-center size-11 text-ink-2" aria-label="開啟選單" :aria-expanded="sidebar">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
        <span class="ml-1 font-serif font-semibold">JYu<span class="text-accent">.</span> <span class="text-xs font-sans text-ink-3 ml-1">後台</span></span>
    </div>

    {{-- Mobile backdrop --}}
    <div x-show="sidebar" x-cloak
        x-transition.opacity
        class="fixed inset-0 bg-black/40 z-30 md:hidden"
        @click="sidebar = false"></div>

    <div class="md:flex min-h-screen">
        @include('admin.partials.sidebar')

        {{-- Main content --}}
        <main class="flex-1 min-w-0">
            @if(session('success'))
                <div class="bg-accent-soft border-b border-accent/30 text-accent-ink px-6 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            {{-- JYU-566: once, right after a password login. --}}
            @if(session()->pull('passkeys.offer'))
                @include('admin.partials.passkey-offer')
            @endif
            <div class="px-6 py-8">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
