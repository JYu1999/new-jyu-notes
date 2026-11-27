{{--
    ⌘K search panel (JYU-538). Opened by ⌘K / Ctrl+K (the `open-search` event from app.js)
    or any [data-search-trigger] link; those links still go to /search without JavaScript.
    Desktop: centered dialog. Phones: full screen.
--}}
@php
    $loc = app()->getLocale();
    $navItems = $navItems ?? \App\Support\PublicNav::items($loc, request());
    $shortcuts = [
        ['label' => __('nav.tweets'), 'url' => route('public.tweets.index', $loc)],
        ['label' => __('nav.topics'), 'url' => route('public.topics', $loc)],
        ['label' => __('nav.about'), 'url' => collect($navItems)->firstWhere('key', 'about')['url']],
        ['label' => 'Changelog', 'url' => route('changelog')],
    ];
@endphp
<div x-data="searchPanel({
        url: @js(route('public.search.suggest', $loc)),
        pageUrl: @js(route('public.search', $loc)),
        noResults: @js(__('public.sp_no_results')),
        found: @js(__('public.sp_found')),
        labels: @js(['all' => __('public.sp_all'), 'post' => __('public.sp_posts'), 'tweet' => __('public.sp_tweets')]),
    })"
    @open-search.window.prevent="show()" @keydown.escape.window="open && close()">
    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[60] md:bg-ink/45 md:pt-[88px] md:px-6 md:pb-16"
        @click.self="close()">
        <div role="dialog" aria-modal="true" aria-label="{{ __('public.sp_title') }}" data-search-panel
            @keydown="onKey($event)"
            class="h-full md:h-auto md:max-h-full max-w-[720px] mx-auto flex flex-col bg-card md:rounded-2xl md:shadow-[0_24px_64px_rgba(42,34,28,0.35)] overflow-hidden">

            {{-- Query --}}
            <div class="flex items-center gap-2 md:gap-3 pl-4 md:pl-5 pr-2 md:pr-4 py-2.5 md:py-0 md:h-16 border-b border-line">
                <label class="flex-1 flex items-center gap-2.5 md:gap-3 h-11 px-3 md:px-0 rounded-[var(--radius-control)] md:rounded-none bg-paper-2 md:bg-transparent">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="text-ink-3 shrink-0" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <span class="sr-only">{{ __('public.sp_label') }}</span>
                    <input x-ref="input" type="search" x-model="q" @input.debounce.200ms="search()"
                        placeholder="{{ __('public.sp_placeholder') }}" autocomplete="off" spellcheck="false"
                        role="combobox" aria-autocomplete="list" aria-controls="search-panel-results" :aria-expanded="results.length > 0"
                        :aria-activedescendant="filtered.length ? 'sp-opt-' + active : null"
                        class="flex-1 min-w-0 bg-transparent border-0 text-[17px] md:text-[19px] text-ink placeholder:text-ink-3 focus:outline-none">
                </label>
                <button type="button" @click="close()" class="hidden md:inline-flex items-center min-h-8 px-2 font-mono text-xs text-ink-3 border border-line-2 rounded-md hover:text-accent" aria-label="{{ __('public.sp_close') }}">Esc</button>
                <button type="button" @click="close()" class="md:hidden inline-flex items-center min-h-11 px-1.5 text-[15px] font-semibold text-accent">{{ __('public.sp_cancel') }}</button>
            </div>

            {{-- Type tabs --}}
            <div role="group" aria-label="{{ __('public.sp_types') }}" class="flex flex-wrap items-center gap-1.5 md:gap-1.5 px-4 md:px-5 py-3 md:border-b md:border-line">
                <template x-for="tab in tabs" :key="tab.key">
                    <button type="button" @click="type = tab.key; active = 0" :aria-pressed="type === tab.key ? 'true' : 'false'"
                        :class="type === tab.key ? 'ui-chip-pressed' : 'ui-chip-neutral'"
                        class="ui-chip ui-chip-md ui-chip-toggle text-sm" x-text="q.trim() ? tab.label + ' ' + counts[tab.key] : tab.label"></button>
                </template>
                <span class="flex-1"></span>
                <span class="text-[13px] text-ink-3" x-show="q.trim() && !loading" x-text="found.replace(':n', counts.all)"></span>
                <span class="text-[13px] text-ink-3" x-show="loading" x-cloak>{{ __('public.sp_loading') }}</span>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto">
                {{-- Nothing typed yet: recent searches + shortcuts --}}
                <div x-show="!q.trim()" class="px-4 md:px-5 py-4">
                    <template x-if="recent.length">
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[13px] font-bold tracking-[0.06em] text-ink-3">{{ __('public.sp_recent') }}</span>
                                <button type="button" @click="clearRecent()" class="text-xs text-ink-3 hover:text-accent min-h-8">{{ __('public.sp_clear_recent') }}</button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="r in recent" :key="r">
                                    <button type="button" @click="q = r; search()" class="ui-btn ui-btn-secondary ui-btn-sm ui-btn-pill" x-text="r"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div class="text-[13px] font-bold tracking-[0.06em] text-ink-3 mb-2">{{ __('public.sp_goto') }}</div>
                    <div class="-mx-3">
                        @foreach($shortcuts as $s)
                            <a href="{{ $s['url'] }}" class="flex justify-between items-center min-h-11 px-3 rounded-[var(--radius-control)] text-[15px] text-ink hover:bg-paper-2 hover:text-ink">
                                <span>{{ $s['label'] }}</span><span aria-hidden="true" class="text-ink-3">→</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Results --}}
                <ul id="search-panel-results" role="listbox" x-show="q.trim() && filtered.length" class="list-none m-0 p-2">
                    <template x-for="(r, i) in filtered" :key="r.url">
                        <li role="option" :id="'sp-opt-' + i" :aria-selected="i === active ? 'true' : 'false'">
                            <a :href="r.url" @click="remember()" @mouseenter="active = i"
                                :class="i === active ? 'bg-paper-2 shadow-[inset_3px_0_0_var(--color-accent)]' : ''"
                                class="flex gap-3 p-3 rounded-[var(--radius-control)] text-ink hover:text-ink">
                                <span class="hidden md:block flex-none w-[52px] pt-0.5 font-mono text-xs text-ink-3" x-text="r.type === 'post' ? @js(__('public.sp_posts')) : @js(__('public.sp_tweets'))"></span>
                                <span class="flex-1 min-w-0 flex flex-col gap-1">
                                    <span class="md:hidden font-mono text-xs text-ink-3" x-text="(r.type === 'post' ? @js(__('public.sp_posts')) : @js(__('public.sp_tweets'))) + ' · ' + r.date"></span>
                                    <span class="font-semibold text-base leading-snug" x-text="r.title"></span>
                                    <span class="text-sm text-ink-2 [overflow-wrap:anywhere]"><span x-text="r.snippet.pre"></span><mark class="bg-accent/20 text-ink rounded-[3px] px-0.5" x-show="r.snippet.hit" x-text="r.snippet.hit"></mark><span x-text="r.snippet.post"></span></span>
                                    <span class="hidden md:block font-mono text-xs text-ink-3"
                                        x-text="[r.date, ...r.tags.map(t => '#' + t), scope === 'all' ? r.locale.toUpperCase() : null].filter(Boolean).join(' · ')"></span>
                                </span>
                            </a>
                        </li>
                    </template>
                </ul>

                {{-- No results --}}
                <div x-show="q.trim() && searched && !loading && !filtered.length" x-cloak class="px-5 py-10 text-center text-[15px] text-ink-2"
                    x-text="noResults.replace(':q', q.trim())"></div>

                {{-- Phones: scope switch below results --}}
                <div class="md:hidden px-4 py-5">
                    <button type="button" @click="toggleScope()"
                        class="flex w-full justify-between items-center min-h-12 px-3.5 rounded-[var(--radius-control)] bg-paper-2 text-[15px] text-ink">
                        <span x-text="scope === 'all' ? @js(__('public.sp_search_locale')) : @js(__('public.sp_search_all'))"></span><span aria-hidden="true">→</span>
                    </button>
                </div>
            </div>

            {{-- Desktop footer: keyboard hints + scope --}}
            <div class="hidden md:flex flex-wrap items-center gap-[18px] px-5 py-3 border-t border-line bg-paper text-xs text-ink-3">
                <span><kbd class="font-mono px-1.5 border border-line-2 rounded">↑↓</kbd> {{ __('public.sp_select') }}</span>
                <span><kbd class="font-mono px-1.5 border border-line-2 rounded">↵</kbd> {{ __('public.sp_open') }}</span>
                <span><kbd class="font-mono px-1.5 border border-line-2 rounded">Tab</kbd> {{ __('public.sp_switch_type') }}</span>
                <span class="flex-1"></span>
                <span>
                    <span x-text="scope === 'all' ? @js(__('public.sp_scope_all')) : @js(__('public.sp_scope_locale'))">{{ __('public.sp_scope_locale') }}</span> ·
                    <button type="button" @click="toggleScope()" class="text-accent hover:text-accent-ink"
                        x-text="scope === 'all' ? @js(__('public.sp_search_locale')) : @js(__('public.sp_search_all'))">{{ __('public.sp_search_all') }}</button>
                </span>
            </div>
        </div>
    </div>
</div>
