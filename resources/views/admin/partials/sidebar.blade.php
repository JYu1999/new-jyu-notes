{{-- Admin sidebar (JYU-541). Slide-in on phones, static on md+. --}}
@php
    $groups = \App\Support\AdminNav::groups(request());
    $user = auth()->user();
@endphp
<aside data-admin-sidebar aria-label="後台選單"
    class="bg-card border-r border-line w-64 md:w-60 flex-shrink-0 flex flex-col gap-5 px-3.5 py-5
           fixed inset-y-0 left-0 z-40 transform transition-transform duration-200 ease-out
           md:sticky md:top-0 md:h-screen md:translate-x-0"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
    <div class="flex items-center justify-between gap-2 px-2">
        <a href="{{ route('admin.dashboard') }}" class="font-serif text-[22px] font-semibold text-ink hover:text-ink">
            JYu<span class="text-accent">.</span> <span class="font-sans text-xs font-medium text-ink-3">後台</span>
        </a>
        <span class="flex items-center gap-1">
            <a href="{{ route('public.root') }}" target="_blank" rel="noopener" class="inline-flex items-center min-h-9 px-1 text-[13px] text-ink-3 hover:text-accent">網站 ↗</a>
            <button type="button" @click="sidebar = false" aria-label="關閉選單" class="md:hidden inline-flex items-center justify-center size-9 text-ink-3 hover:text-accent">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M6 18L18 6"/></svg>
            </button>
        </span>
    </div>

    <nav class="flex-1 overflow-y-auto flex flex-col gap-0.5 text-sm">
        @foreach($groups as $group)
            <span class="px-2.5 pb-1.5 {{ $loop->first ? '' : 'pt-3.5' }} text-xs font-bold tracking-[0.06em] text-ink-3">{{ $group['label'] }}</span>
            @foreach($group['items'] as $item)
                <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                    class="flex items-center justify-between min-h-10 px-2.5 rounded-lg {{ $item['active'] ? 'bg-paper-2 text-ink font-semibold' : 'text-ink-2 hover:bg-paper-2 hover:text-ink' }}">
                    <span>{{ $item['label'] }}</span>
                    @if($item['count'] !== null)
                        <span class="font-mono text-xs text-ink-3">{{ $item['count'] }}</span>
                    @endif
                </a>
            @endforeach
        @endforeach
    </nav>

    <div class="flex items-center gap-1 pt-3 border-t border-line">
        {{-- 帳號與安全 (JYU-566): reached from the account block, not the main nav. --}}
        <a href="{{ route('admin.account.show') }}" title="帳號與安全" @if(request()->routeIs('admin.account.*')) aria-current="page" @endif
            class="flex-1 min-w-0 flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg {{ request()->routeIs('admin.account.*') ? 'bg-paper-2' : 'hover:bg-paper-2' }}">
            <span aria-hidden="true" class="flex items-center justify-center size-8 shrink-0 rounded-full bg-ink text-paper text-[13px] font-bold">{{ mb_strtoupper(mb_substr($user?->name ?? '?', 0, 1)) }}</span>
            <span class="flex-1 min-w-0">
                <span class="block text-sm text-ink truncate">{{ $user?->name }}</span>
                <span class="block text-xs text-ink-3 truncate">{{ $user?->email }}</span>
            </span>
        </a>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="min-h-9 px-2 text-[13px] text-ink-3 hover:text-accent">登出</button>
        </form>
    </div>
</aside>
