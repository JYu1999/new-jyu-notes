@props(['post'])

<article class="bg-card border border-line rounded-md p-5 hover:border-line-2 transition-colors flex flex-col h-full">
    @if($post->cover_image_path)
        <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}" class="block mb-4 -mx-5 -mt-5 overflow-hidden rounded-t-md">
            <img src="{{ asset('storage/' . $post->cover_image_path) }}" alt="" class="w-full h-44 object-cover">
        </a>
    @endif

    @if($post->categories->isNotEmpty())
        <div class="text-[10px] uppercase tracking-widest text-accent font-mono mb-2">
            {{ $post->categories->first()->name(app()->getLocale()) }}
        </div>
    @endif

    <h3 class="font-serif text-lg font-semibold leading-tight mb-2 line-clamp-2">
        <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}" class="text-ink hover:text-accent">
            {{ $post->title }}
        </a>
    </h3>

    @if($post->excerpt)
        <p class="text-sm text-ink-2 line-clamp-3 mb-4">{{ $post->excerpt }}</p>
    @endif

    <div class="mt-auto flex items-center justify-between text-xs text-ink-3 font-mono">
        <span>{{ $post->published_at?->format('Y/m/d') }}</span>
        <span>{{ $post->views_count }} views</span>
    </div>

    @if($post->tags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($post->tags->take(4) as $tag)
                <a href="{{ route('public.tags.show', [app()->getLocale(), $tag->slug(app()->getLocale())]) }}" class="text-[10px] font-mono px-2 py-0.5 bg-paper-2 text-ink-3 hover:text-accent rounded">
                    #{{ $tag->name(app()->getLocale()) }}
                </a>
            @endforeach
        </div>
    @endif
</article>
