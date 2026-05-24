<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · JYu's Blog</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink min-h-screen">
    <div class="flex min-h-screen" x-data="{ sidebar: window.innerWidth >= 768 }">
        {{-- Sidebar --}}
        <aside class="bg-card border-r border-line w-56 flex-shrink-0 hidden md:flex flex-col" :class="sidebar ? '' : 'md:hidden'">
            <div class="px-5 py-5 border-b border-line">
                <a href="{{ route('admin.dashboard') }}" class="text-xl font-serif font-semibold">
                    JYu<span class="text-accent">.</span> <span class="text-xs font-sans text-ink-3 ml-1">Admin</span>
                </a>
            </div>

            <nav class="flex-1 px-3 py-4 text-sm space-y-1">
                @php
                    $items = [
                        ['route' => 'admin.dashboard', 'label' => '儀表板'],
                        ['route' => 'admin.posts.index', 'label' => 'Posts', 'group' => 'posts'],
                        ['route' => 'admin.tweets.index', 'label' => 'Tweets', 'group' => 'tweets'],
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

        {{-- Mobile header --}}
        <div class="md:hidden fixed top-0 left-0 right-0 z-30 bg-card border-b border-line h-12 flex items-center px-4">
            <button @click="sidebar = !sidebar" class="text-ink-2"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
            <span class="ml-3 font-serif font-semibold">JYu. Admin</span>
        </div>
        <div x-show="sidebar && window.innerWidth < 768" x-cloak class="fixed inset-0 bg-black/40 z-20 md:hidden" @click="sidebar = false"></div>

        {{-- Main content --}}
        <main class="flex-1 min-w-0 pt-12 md:pt-0">
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
