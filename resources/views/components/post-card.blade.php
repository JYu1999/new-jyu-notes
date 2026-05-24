@props(['post'])

{{-- Whole card is clickable via the stretched-link on the title (::before overlay);
     inner tag links lift back above with z-index. --}}
<article class="bg-card border border-line rounded-md p-5 hover:border-line-2 transition-colors flex flex-col h-full relative">
    @if($post->cover_image_path)
        <div class="block mb-4 -mx-5 -mt-5 overflow-hidden rounded-t-md">
            <img src="{{ asset('storage/' . $post->cover_image_path) }}" alt="" class="w-full h-44 object-cover">
        </div>
    @endif

    @if($post->categories->isNotEmpty())
        <div class="text-[10px] uppercase tracking-widest text-accent font-mono mb-2">
            {{ $post->categories->first()->name(app()->getLocale()) }}
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
        <span>{{ $post->views_count }} {{ __('public.views') }}</span>
    </div>

    @if($post->tags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5 relative z-10">
            @foreach($post->tags->take(4) as $tag)
                @php $tslug = $tag->slug(app()->getLocale()); @endphp
                @if($tslug)
                    <a href="{{ route('public.tags.show', [app()->getLocale(), $tslug]) }}" class="text-[10px] font-mono px-2 py-0.5 bg-paper-2 text-ink-3 hover:text-accent rounded">
                        #{{ $tag->name(app()->getLocale()) }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</article>
