@props(['post'])

{{-- Whole card is clickable via the stretched-link on the title (::before overlay);
     inner tag links lift back above with z-index. --}}
<article class="bg-card border border-line rounded-md p-5 hover:border-line-2 transition-colors flex flex-col h-full relative">
    @if($post->cover_image_path)
        <div class="block mb-4 -mx-5 -mt-5 overflow-hidden rounded-t-md">
            <img src="{{ media_url($post->cover_image_path) }}" alt="" class="w-full h-44 object-cover">
        </div>
    @endif

    @php
        $loc = app()->getLocale();
        $catTrans = $post->categories->first()?->translations->firstWhere('locale', $loc);
    @endphp
    @if($catTrans)
        <div class="text-[10px] uppercase tracking-widest text-accent font-mono mb-2">
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
        <span>{{ $post->reading_time }} min read</span>
        <span>{{ $post->views_count }} {{ __('public.views') }}</span>
    </div>

    @if($post->tags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5 relative z-10">
            @foreach($post->tags->take(4) as $tag)
                @php $tt = $tag->translations->firstWhere('locale', $loc); @endphp
                @if($tt)
                    <a href="{{ route('public.tags.show', [$loc, $tt->slug]) }}"
                        class="text-[10px] font-mono px-2 py-0.5 rounded {{ $tag->color ? 'tag-chip' : 'bg-paper-2 text-ink-3 hover:text-accent' }}"
                        @if($tag->color) style="--tag-color: {{ $tag->color }}" @endif>
                        #{{ $tt->name }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</article>
