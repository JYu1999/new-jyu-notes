<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
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

            <div class="flex items-center gap-3">
                {{-- Language switcher --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-2.5 py-1 text-xs uppercase tracking-wide border border-line rounded hover:border-accent hover:text-accent">
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-32 rounded-md border border-line bg-card shadow-md text-sm overflow-hidden">
                        @foreach(['zh' => '繁體中文', 'en' => 'English', 'ja' => '日本語', 'vi' => 'Tiếng Việt', 'id' => 'Bahasa Indonesia'] as $code => $label)
                            <form method="POST" action="{{ route('public.locale.switch', $code) }}" class="contents">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-1.5 hover:bg-paper-2 {{ app()->getLocale() === $code ? 'text-accent font-medium' : 'text-ink-2' }}">
                                    {{ $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- Theme toggle --}}
                <button onclick="toggleTheme()" class="w-8 h-8 rounded-full border border-line hover:border-accent flex items-center justify-center text-ink-2 hover:text-accent" aria-label="Toggle theme">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
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
