@props(['tweet', 'preview' => false, 'variant' => 'card'])

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

@php $isList = $variant === 'list'; @endphp
<article
    @if($isLong) x-data="{ expanded: false }" @endif
    @class([
        'bg-card border-l-2 border-accent/40 pl-4 pr-3 py-3 relative overflow-hidden' => ! $isList,
        'py-6 border-b border-line' => $isList,
    ])>

    @if($isList)
        {{-- List layout (tweets page, JYU-537): date left, tags right --}}
        <div class="flex items-start justify-between gap-3 mb-2.5">
            <a href="{{ route('public.tweets.show', [app()->getLocale(), $tweet->id]) }}"
                class="inline-flex items-center min-h-8 font-mono text-[13px] text-ink-3 hover:text-accent">
                {{ $tweet->published_at?->format('m/d H:i') }}
            </a>
            @if($tweet->tags->isNotEmpty())
                <span class="flex flex-wrap justify-end gap-1.5">
                    @foreach($tweet->tags as $tag)
                        <x-ui.tag :tag="$tag" size="sm" />
                    @endforeach
                </span>
            @endif
        </div>
    @else
        <div class="text-xs text-ink-3 font-mono mb-1.5">
            <a href="{{ route('public.tweets.show', [app()->getLocale(), $tweet->id]) }}"
                class="hover:text-accent"
                @if(! $preview) @click.stop @endif>
                {{ $tweet->published_at?->format('Y/m/d H:i') }}
            </a>
        </div>
    @endif

    @if($preview)
        <div class="font-serif text-[15px] leading-relaxed text-ink">{{ $previewText }}</div>
    @else
        {{-- Full markdown rendering, collapsible if long --}}
        <div class="prose-blog tweet-body {{ $isList ? 'text-[17px] leading-[1.75]' : 'text-[15px] leading-relaxed' }} relative"
            @if($isLong) :class="expanded ? '' : 'max-h-72 overflow-hidden'" @endif>
            {!! $rendered !!}
            @if($isLong)
                <div x-show="!expanded"
                    class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t {{ $isList ? 'from-paper via-paper/80' : 'from-card via-card/80' }} to-transparent pointer-events-none"></div>
            @endif
        </div>
        @if($isLong)
            <button type="button" x-show="!expanded" @click.prevent.stop="expanded = true" :aria-expanded="expanded"
                class="mt-2 inline-flex items-center min-h-9 text-sm font-semibold text-accent hover:text-accent-ink">
                {{ __('public.tweets_expand') }}
            </button>
            <button type="button" x-show="expanded" x-cloak @click.prevent.stop="expanded = false"
                class="mt-2 inline-flex items-center min-h-9 text-sm text-ink-3 hover:text-accent">
                {{ __('public.tweets_collapse') }}
            </button>
        @endif
    @endif

    @unless($preview)
        @include('components.partials.link-preview', ['tweet' => $tweet])
    @endunless

    @include('components.partials.tweet-media-grid', ['media' => $media])

    @if(! $isList && $tweet->tags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($tweet->tags as $tag)
                <x-ui.tag :tag="$tag" size="sm" />
            @endforeach
        </div>
    @endif
</article>
