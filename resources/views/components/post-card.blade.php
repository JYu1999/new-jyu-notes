@props(['post'])

{{-- Whole card is clickable via the stretched-link on the title (::before overlay);
     inner tag links lift back above with z-index. --}}
<x-ui.card as="article" padding="none" class="p-5 hover:border-line-2 transition-colors flex flex-col h-full relative overflow-hidden">
    @if($post->cover_image_path)
        <div class="block mb-4 -mx-5 -mt-5 overflow-hidden">
            <img src="{{ media_url($post->cover_image_path) }}" alt="" class="w-full h-44 object-cover">
        </div>
    @endif

    @php
        $loc = app()->getLocale();
        $catTrans = $post->categories->first()?->translations->firstWhere('locale', $loc);
    @endphp
    @if($catTrans)
        <div class="text-xs tracking-wider text-ink-3 font-mono mb-2">
            {{ $catTrans->name }}
        </div>
    @endif

    <h3 class="font-serif text-lg font-semibold leading-tight mb-2 line-clamp-2">
        <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}"
           class="text-ink hover:text-accent before:content-[''] before:absolute before:inset-0">
            {{ $post->title }}
        </a>
    </h3>

    @if($post->excerpt)
        <p class="text-sm text-ink-2 line-clamp-3 mb-4">{{ $post->excerpt }}</p>
    @endif

    <div class="mt-auto flex items-center justify-between text-xs text-ink-3 font-mono">
        <span>{{ $post->published_at?->format('Y/m/d') }}</span>
        <span>{{ $post->reading_time }} {{ __('public.min_read') }}</span>
        <span>{{ $post->views_count }} {{ __('public.views') }}</span>
    </div>

    @if($post->tags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5 relative z-10">
            @foreach($post->tags->take(4) as $tag)
                <x-ui.tag :tag="$tag" size="sm" />
            @endforeach
        </div>
    @endif
</x-ui.card>
