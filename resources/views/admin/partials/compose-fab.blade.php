{{-- Phones: floating shortcut to the full-screen tweet composer (JYU-551); it returns to the page it was opened from (JYU-563). --}}
<a href="{{ route('admin.tweets.compose', request()->routeIs('admin.tweets.*') ? ['from' => 'tweets'] : []) }}" data-compose-fab
    class="md:hidden fixed right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-30 inline-flex items-center gap-1.5 h-12 px-5 rounded-full bg-ink text-paper text-sm font-semibold shadow-lg hover:bg-accent hover:text-paper">
    <span aria-hidden="true" class="text-lg leading-none">＋</span>碎念
</a>
