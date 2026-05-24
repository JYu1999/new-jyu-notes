<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · JYu's Blog</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink min-h-screen" x-data="{ sidebar: false }">
    {{-- Mobile header --}}
    <div class="md:hidden sticky top-0 z-30 bg-card border-b border-line h-12 flex items-center px-4">
        <button @click="sidebar = true" class="text-ink-2" aria-label="Open menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
        <span class="ml-3 font-serif font-semibold">JYu. <span class="text-xs font-sans text-ink-3 ml-1">Admin</span></span>
    </div>

    {{-- Mobile backdrop --}}
    <div x-show="sidebar" x-cloak
        x-transition.opacity
        class="fixed inset-0 bg-black/40 z-30 md:hidden"
        @click="sidebar = false"></div>

    <div class="md:flex min-h-screen">
        {{-- Sidebar: fixed on mobile (slide-in), static on md+ --}}
        <aside
            class="bg-card border-r border-line w-64 md:w-56 flex-shrink-0 flex flex-col
                   fixed inset-y-0 left-0 z-40 transform transition-transform duration-200 ease-out
                   md:static md:translate-x-0"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
            <div class="px-5 py-5 border-b border-line flex items-center justify-between">
                <a href="{{ route('admin.dashboard') }}" class="text-xl font-serif font-semibold">
                    JYu<span class="text-accent">.</span> <span class="text-xs font-sans text-ink-3 ml-1">Admin</span>
                </a>
                <button @click="sidebar = false" class="md:hidden text-ink-3 hover:text-accent" aria-label="Close menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6l12 12M6 18L18 6"/>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 px-3 py-4 text-sm space-y-1 overflow-y-auto">
                @php
                    $items = [
                        ['route' => 'admin.dashboard', 'label' => '儀表板'],
                        ['route' => 'admin.posts.index', 'label' => 'Posts', 'group' => 'posts'],
                        ['route' => 'admin.tweets.index', 'label' => 'Tweets', 'group' => 'tweets'],
                        ['route' => 'admin.pages.index', 'label' => 'Pages', 'group' => 'pages'],
                        ['route' => 'admin.tags.index', 'label' => 'Tags', 'group' => 'tags'],
                        ['route' => 'admin.categories.index', 'label' => 'Categories', 'group' => 'categories'],
                        ['route' => 'admin.media.index', 'label' => 'Media', 'group' => 'media'],
                    ];
                @endphp
                @foreach($items as $i)
                    @php
                        $isActive = isset($i['group'])
                            ? request()->routeIs("admin.{$i['group']}.*")
                            : request()->routeIs($i['route']);
                    @endphp
                    <a href="{{ route($i['route']) }}" class="block px-3 py-2 rounded-md {{ $isActive ? 'bg-accent-soft text-accent font-medium' : 'text-ink-2 hover:bg-paper-2' }}">
                        {{ $i['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="px-3 py-4 border-t border-line text-sm">
                <div class="px-3 py-2 text-ink-3 text-xs uppercase tracking-wider font-mono">{{ auth()->user()?->email }}</div>
                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-ink-2 hover:bg-paper-2">登出</button>
                </form>
                <a href="{{ url('/' . app()->getLocale()) }}" target="_blank" class="block px-3 py-2 rounded-md text-ink-3 hover:bg-paper-2 text-xs">前往前台 ↗</a>
            </div>
        </aside>

        {{-- Main content --}}
        <main class="flex-1 min-w-0">
            @if(session('success'))
                <div class="bg-accent-soft border-b border-accent/30 text-accent-ink px-6 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            <div class="px-6 py-8">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
