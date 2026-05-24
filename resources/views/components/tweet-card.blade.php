@props(['tweet', 'preview' => false])

@php
    $renderer = app(\App\Support\MarkdownRenderer::class);
    $rendered = $renderer->render($tweet->body);

    if ($preview) {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($rendered)));
        $previewText = \Illuminate\Support\Str::limit($plain, 220);
    }

    $bodyLen = mb_strlen($tweet->body);
    $isLong = ! $preview && $bodyLen > 800;
    $media = $tweet->media ?? [];
    $mediaCount = is_array($media) ? count($media) : 0;
@endphp

<article
    @if($isLong) x-data="{ expanded: false }" @endif
    class="bg-card border-l-2 border-accent/40 pl-4 pr-3 py-3 relative overflow-hidden">

    <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-1.5">
        <a href="{{ route('public.tweets.show', [app()->getLocale(), $tweet->id]) }}"
            class="hover:text-accent"
            @if(! $preview) @click.stop @endif>
            {{ $tweet->published_at?->format('Y/m/d H:i') }}
        </a>
    </div>

    @if($preview)
        <div class="font-serif text-[15px] leading-relaxed text-ink">{{ $previewText }}</div>
    @else
        {{-- Full markdown rendering, collapsible if long --}}
        <div class="prose-blog tweet-body text-[15px] leading-relaxed relative"
            @if($isLong) :class="expanded ? '' : 'max-h-72 overflow-hidden'" @endif>
            {!! $rendered !!}
            @if($isLong)
                <div x-show="!expanded"
                    class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-card via-card/80 to-transparent pointer-events-none"></div>
            @endif
        </div>
        @if($isLong)
            <button type="button" x-show="!expanded" @click.prevent.stop="expanded = true"
                class="mt-2 text-xs font-mono text-accent hover:text-accent-ink">
                … Read more
            </button>
            <button type="button" x-show="expanded" x-cloak @click.prevent.stop="expanded = false"
                class="mt-2 text-xs font-mono text-ink-3 hover:text-accent">
                ↑ Show less
            </button>
        @endif
    @endif

    {{-- Media: 1 → full, 2 → side-by-side, 3+ → horizontal scroll-snap --}}
    @if($mediaCount === 1)
        @php $m = $media[0]; @endphp
        <div class="mt-3">
            @if(($m['type'] ?? 'image') === 'image')
                <img src="{{ asset('storage/' . $m['path']) }}" alt="{{ $m['alt'] ?? '' }}"
                    class="rounded-md w-full h-auto object-cover max-h-96">
            @else
                <video src="{{ asset('storage/' . $m['path']) }}" controls
                    class="rounded-md w-full max-w-full max-h-96"></video>
            @endif
        </div>
    @elseif($mediaCount === 2)
        <div class="mt-3 grid grid-cols-2 gap-2">
            @foreach($media as $m)
                @if(($m['type'] ?? 'image') === 'image')
                    <img src="{{ asset('storage/' . $m['path']) }}" alt="{{ $m['alt'] ?? '' }}"
                        class="rounded-md w-full h-48 object-cover">
                @else
                    <video src="{{ asset('storage/' . $m['path']) }}" controls
                        class="rounded-md w-full h-48 object-cover"></video>
                @endif
            @endforeach
        </div>
    @elseif($mediaCount > 2)
        <div class="mt-3 -mx-1 flex gap-2 overflow-x-auto snap-x snap-mandatory pb-2 scrollbar-thin">
            @foreach($media as $m)
                <div class="snap-start flex-shrink-0 w-56 sm:w-64">
                    @if(($m['type'] ?? 'image') === 'image')
                        <img src="{{ asset('storage/' . $m['path']) }}" alt="{{ $m['alt'] ?? '' }}"
                            class="rounded-md w-full h-44 object-cover">
                    @else
                        <video src="{{ asset('storage/' . $m['path']) }}" controls
                            class="rounded-md w-full h-44 object-cover"></video>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($tweet->tags->isNotEmpty())
        @php $loc = app()->getLocale(); @endphp
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($tweet->tags as $tag)
                @php $tt = $tag->translations->firstWhere('locale', $loc); @endphp
                @if($tt)
                    <a href="{{ route('public.tags.show', [$loc, $tt->slug]) }}"
                        class="text-[10px] font-mono px-2 py-0.5 bg-paper-2 text-ink-3 hover:text-accent rounded">
                        #{{ $tt->name }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</article>
