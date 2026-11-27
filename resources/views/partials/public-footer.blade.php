{{-- Public footer. Expects $navItems (for the About link). RSS intentionally omitted (JYU-533). --}}
@php $about = collect($navItems)->firstWhere('key', 'about'); @endphp
<footer class="border-t border-line mt-24">
    <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-6 md:py-8 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-[13px] text-ink-3">
        <p class="m-0">
            <span class="font-serif font-semibold text-ink">JYu.</span>
            © {{ date('Y') }} · {{ __('footer.tagline') }}
        </p>
        <nav aria-label="{{ __('footer.footer_nav') }}" class="flex gap-1 -mx-2">
            <a href="{{ $about['url'] }}" class="inline-flex items-center min-h-11 px-2 text-ink-3 hover:text-accent">{{ __('footer.about') }}</a>
            <a href="{{ route('changelog') }}" class="inline-flex items-center min-h-11 px-2 text-ink-3 hover:text-accent">{{ __('footer.changelog') }}</a>
        </nav>
    </div>
</footer>
