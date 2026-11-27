{{--
    Public header: logo · main menu · search · language · theme.
    Desktop (md+): one row. Mobile: icon row + horizontally scrollable pill menu.
    Expects $navItems (App\Support\PublicNav::items) and $availableLocales.
--}}
@php
    $loc = app()->getLocale();
    $localeNames = \App\Support\PublicNav::LOCALE_NAMES;
    $searchUrl = route('public.search', $loc);
    $homeActive = request()->routeIs('public.home');
@endphp
<header class="sticky top-0 z-30 border-b border-line bg-paper/95 backdrop-blur-sm">
    <div class="max-w-[1200px] mx-auto pl-4 pr-2 md:px-8 h-15 md:h-auto md:py-3 flex items-center gap-1 md:gap-7">
        <a href="{{ route('public.home', $loc) }}" class="flex items-center min-h-11 mr-auto md:mr-0 font-serif text-2xl md:text-[26px] font-semibold tracking-tight text-ink hover:text-ink">
            JYu<span class="text-accent">.</span>
        </a>

        <nav data-nav="main" aria-label="{{ __('nav.main_menu') }}" class="hidden md:flex items-center gap-1 text-[15px]">
            @foreach($navItems as $item)
                <a href="{{ $item['url'] }}"
                    @if($item['active']) aria-current="page" @endif
                    class="inline-flex items-center h-11 px-3 rounded-lg transition-colors {{ $item['active'] ? 'bg-paper-2 text-ink font-semibold' : 'text-ink-2 hover:text-accent' }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="hidden md:block flex-1"></div>

        {{-- Search: full box on desktop, icon on mobile. ⌘K / Ctrl+K handled in resources/js/app.js. --}}
        <a href="{{ $searchUrl }}" data-search-trigger
            class="hidden md:flex items-center gap-2.5 min-w-60 h-11 pl-3.5 pr-3 rounded-[var(--radius-control)] border border-line-2 bg-card text-sm text-ink-3 hover:border-accent transition-colors">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <span class="flex-1">{{ __('nav.search_placeholder') }}</span>
            <kbd data-shortcut-label class="font-mono text-xs px-1.5 py-0.5 border border-line-2 rounded-[5px]">⌘K</kbd>
        </a>
        <x-ui.button :href="$searchUrl" variant="ghost" icon class="md:hidden" aria-label="{{ __('nav.open_search') }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        </x-ui.button>

        {{-- Language switcher --}}
        <div x-data="{ open: false }" class="relative" @keydown.escape="open = false">
            <x-ui.button size="md" class="px-3 max-md:border-transparent" @click="open = !open" ::aria-expanded="open"
                aria-haspopup="true"
                aria-label="{{ __('nav.switch_language', ['lang' => $localeNames[$loc] ?? $loc]) }}">
                <svg class="hidden md:block" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                <span class="max-md:font-semibold max-md:text-ink">{{ __('nav.locale_short') }}</span>
            </x-ui.button>
            <div x-show="open" @click.outside="open = false" x-cloak
                class="absolute right-0 mt-2 w-48 rounded-[var(--radius-card)] border border-line bg-card shadow-md text-sm overflow-hidden z-40 py-1">
                @foreach($localeNames as $code => $label)
                    @if(in_array($code, $availableLocales, true))
                        <form method="POST" action="{{ route('public.locale.switch', $code) }}" class="contents">
                            @csrf
                            <button type="submit" lang="{{ $code }}"
                                @if($loc === $code) aria-current="true" @endif
                                class="w-full min-h-11 text-left px-4 hover:bg-paper-2 flex items-center justify-between {{ $loc === $code ? 'text-accent font-semibold' : 'text-ink-2' }}">
                                <span>{{ $label }}</span>
                                <span class="text-xs font-mono text-ink-3 uppercase">{{ $code }}</span>
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Theme toggle. Icons follow <html data-theme> via CSS (see JYU-532). --}}
        <x-ui.button icon class="max-md:border-transparent" onclick="window.toggleTheme()" aria-label="{{ __('nav.toggle_theme') }}">
            <svg class="theme-icon-when-dark" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
            <svg class="theme-icon-when-light" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
            </svg>
        </x-ui.button>
    </div>

    {{-- Mobile menu: horizontally scrollable pills --}}
    <nav data-nav="mobile" aria-label="{{ __('nav.main_menu') }}"
        class="md:hidden flex gap-2 px-4 py-2.5 overflow-x-auto whitespace-nowrap border-t border-line scrollbar-none">
        @foreach([['label' => __('nav.home'), 'url' => route('public.home', $loc), 'active' => $homeActive], ...$navItems] as $item)
            <a href="{{ $item['url'] }}"
                @if($item['active']) aria-current="page" @endif
                class="flex-none inline-flex items-center h-11 px-4 rounded-full text-sm {{ $item['active'] ? 'bg-ink text-paper font-semibold' : 'bg-paper-2 text-ink-2' }}">{{ $item['label'] }}</a>
        @endforeach
    </nav>
</header>
