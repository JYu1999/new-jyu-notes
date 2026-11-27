{{--
    Featured posts. Layout depends on how many (JYU-534):
      1 → one full-width hero; 2 → two equal cards; 3 → one large + two small.
    Phones only show the first (hero) card.
--}}
@php
    $loc = app()->getLocale();
    $n = $featured->count();
    $label = function ($post) use ($loc) {
        $tags = $post->tags->map(fn ($t) => $t->translations->firstWhere('locale', $loc)?->name)->filter()->take(2);
        if ($tags->isEmpty()) {
            $tags = collect([$post->categories->first()?->name($loc)])->filter();
        }

        return $tags->implode(' · ');
    };
    $meta = fn ($post) => $post->published_at?->format('Y/m/d').' · '.__('public.minutes', ['n' => $post->reading_time]);
    $hero = $featured->first();
@endphp
<section aria-labelledby="featured-h" data-featured-count="{{ $n }}" class="pt-10 md:pt-12">
    <x-ui.section-heading id="featured-h" class="mb-4"
        :action-href="$featuredTotal > $n ? route('public.posts.index', ['locale' => $loc, 'featured' => 1]) : null"
        :action-label="__('public.home_more_featured', ['n' => $featuredTotal - $n])">{{ __('public.home_featured') }}</x-ui.section-heading>

    <div class="flex flex-wrap gap-6 pb-10">
        {{-- Hero: inverse colors (ink background) --}}
        <a href="{{ route('public.posts.show', [$loc, $hero->slug]) }}" data-featured-card="hero"
            class="min-w-0 flex flex-col gap-3.5 p-6 md:p-8 rounded-[var(--radius-card-lg)] bg-ink text-paper hover:text-paper transition-opacity hover:opacity-95
                {{ $n === 1 ? 'basis-full md:min-h-[260px]' : ($n === 2 ? 'basis-full md:basis-0 md:flex-1' : 'basis-full md:basis-0 md:grow-[2]') }}">
            @if($label($hero))<span class="font-mono text-xs tracking-[0.08em] text-inverse-accent">{{ $label($hero) }}</span>@endif
            <span class="font-serif text-[23px] md:text-[34px] leading-tight font-medium">{{ $hero->title }}</span>
            @if($hero->excerpt)<span class="text-inverse-ink-2 max-w-[640px] {{ $n === 1 ? '' : 'line-clamp-3' }}">{{ $hero->excerpt }}</span>@endif
            <span class="mt-auto font-mono text-[13px] text-inverse-ink-3">{{ $meta($hero) }}</span>
        </a>

        @if($n === 2)
            @php $p = $featured[1]; @endphp
            <x-ui.card :href="route('public.posts.show', [$loc, $p->slug])" padding="lg" radius="lg" data-featured-card="side"
                class="hidden md:flex md:basis-0 md:flex-1 min-w-0 flex-col gap-3.5">
                @if($label($p))<span class="font-mono text-xs tracking-[0.08em] text-ink-3">{{ $label($p) }}</span>@endif
                <span class="font-serif text-[30px] leading-tight font-medium">{{ $p->title }}</span>
                @if($p->excerpt)<span class="text-ink-2 line-clamp-3">{{ $p->excerpt }}</span>@endif
                <span class="mt-auto font-mono text-[13px] text-ink-3">{{ $meta($p) }}</span>
            </x-ui.card>
        @elseif($n >= 3)
            <div class="hidden md:flex md:basis-0 md:grow min-w-[280px] flex-col gap-3">
                @foreach($featured->slice(1, 2) as $p)
                    <x-ui.card :href="route('public.posts.show', [$loc, $p->slug])" padding="none" radius="lg" data-featured-card="side"
                        class="flex-1 flex flex-col gap-2 p-[22px]">
                        @if($label($p))<span class="font-mono text-xs text-ink-3">{{ $label($p) }}</span>@endif
                        <span class="font-serif text-xl leading-snug font-medium">{{ $p->title }}</span>
                        <span class="font-mono text-[13px] text-ink-3">{{ $meta($p) }}</span>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</section>
