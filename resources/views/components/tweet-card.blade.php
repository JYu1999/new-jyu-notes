@props(['tweet'])

<article class="bg-card border-l-2 border-accent/40 pl-4 pr-2 py-3 relative">
    <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-1.5">
        {{ $tweet->published_at?->format('Y/m/d H:i') }}
    </div>

    <div class="font-serif text-[15px] leading-relaxed text-ink whitespace-pre-line">{{ \Illuminate\Support\Str::limit($tweet->body, 280) }}</div>

    @if($tweet->media)
        <div class="mt-3 grid {{ count($tweet->media) > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
            @foreach($tweet->media as $m)
                @if(($m['type'] ?? 'image') === 'image')
                    <img src="{{ asset('storage/' . $m['path']) }}" alt="{{ $m['alt'] ?? '' }}" class="rounded-md w-full h-auto object-cover max-h-64">
                @else
                    <video src="{{ asset('storage/' . $m['path']) }}" controls class="rounded-md w-full"></video>
                @endif
            @endforeach
        </div>
    @endif

    @if($tweet->tags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($tweet->tags as $tag)
                <a href="{{ route('public.tags.show', [app()->getLocale(), $tag->slug(app()->getLocale())]) }}" class="text-[10px] font-mono px-2 py-0.5 bg-paper-2 text-ink-3 hover:text-accent rounded">
                    #{{ $tag->name(app()->getLocale()) }}
                </a>
            @endforeach
        </div>
    @endif
</article>
