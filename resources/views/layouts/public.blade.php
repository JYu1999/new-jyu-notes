<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink">
    {{-- Top navigation --}}
    <header class="border-b border-line bg-paper sticky top-0 z-30 backdrop-blur-sm bg-opacity-95">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between gap-4">
            <a href="{{ url('/' . app()->getLocale()) }}" class="text-2xl font-serif font-semibold tracking-tight text-ink hover:text-accent">
                JYu<span class="text-accent">.</span>
            </a>

            <nav class="hidden md:flex items-center gap-6 text-sm">
                <a href="{{ route('public.home', app()->getLocale()) }}" class="hover:text-accent {{ request()->routeIs('public.home') ? 'text-accent font-medium' : 'text-ink-2' }}">
                    @lang('nav.home')
                </a>
                <a href="{{ route('public.posts.index', app()->getLocale()) }}" class="hover:text-accent {{ request()->routeIs('public.posts.*') ? 'text-accent font-medium' : 'text-ink-2' }}">
                    @lang('nav.posts')
                </a>
                <a href="{{ route('public.tweets.index', app()->getLocale()) }}" class="hover:text-accent {{ request()->routeIs('public.tweets.*') ? 'text-accent font-medium' : 'text-ink-2' }}">
                    @lang('nav.tweets')
                </a>
                <a href="{{ route('public.search', app()->getLocale()) }}" class="hover:text-accent {{ request()->routeIs('public.search') ? 'text-accent font-medium' : 'text-ink-2' }}">
                    @lang('nav.search')
                </a>
            </nav>

            @php
                // Layout reads $availableLocales if a controller sets it (e.g. post show).
                // Default: all supported locales.
                $availableLocales = $availableLocales ?? \App\Models\Post::SUPPORTED_LOCALES;
                $localeLabels = ['zh' => '繁體中文', 'en' => 'English', 'ja' => '日本語', 'vi' => 'Tiếng Việt', 'id' => 'Bahasa Indonesia'];
            @endphp

            <div class="flex items-center gap-3">
                {{-- Language switcher --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                        class="w-9 h-9 rounded-full border border-line hover:border-accent flex items-center justify-center text-ink-2 hover:text-accent"
                        aria-label="Switch language">
                        {{-- globe icon --}}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M3 12h18"/>
                            <path d="M12 3a14 14 0 0 1 0 18"/>
                            <path d="M12 3a14 14 0 0 0 0 18"/>
                        </svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                        class="absolute right-0 mt-2 w-40 rounded-md border border-line bg-card shadow-md text-sm overflow-hidden z-40">
                        @foreach($localeLabels as $code => $label)
                            @if(in_array($code, $availableLocales, true))
                                <form method="POST" action="{{ route('public.locale.switch', $code) }}" class="contents">
                                    @csrf
                                    <button type="submit"
                                        class="w-full text-left px-3 py-1.5 hover:bg-paper-2 flex items-center justify-between {{ app()->getLocale() === $code ? 'text-accent font-medium' : 'text-ink-2' }}">
                                        <span>{{ $label }}</span>
                                        <span class="text-[10px] font-mono text-ink-3 uppercase">{{ $code }}</span>
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Theme toggle (icon flips based on current theme) --}}
                <button
                    x-data="{ dark: document.documentElement.getAttribute('data-theme') === 'dark' }"
                    @click="window.toggleTheme(); dark = !dark"
                    class="w-9 h-9 rounded-full border border-line hover:border-accent flex items-center justify-center text-ink-2 hover:text-accent"
                    aria-label="Toggle theme">
                    {{-- Sun when in dark mode (click to go light) --}}
                    <svg x-show="dark" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                    {{-- Moon when in light mode (click to go dark) --}}
                    <svg x-show="!dark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <main class="min-h-[calc(100vh-4rem-6rem)]">
        @yield('content')
    </main>

    <footer class="border-t border-line mt-24">
        <div class="max-w-6xl mx-auto px-6 py-12 text-sm text-ink-3 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="font-serif font-semibold text-ink-2">JYu<span class="text-accent">.</span></span>
                <span class="ml-2">&copy; {{ date('Y') }}</span>
            </div>
            <div class="font-mono text-xs text-ink-3">
                @lang('footer.tagline')
            </div>
        </div>
    </footer>
</body>
</html>
