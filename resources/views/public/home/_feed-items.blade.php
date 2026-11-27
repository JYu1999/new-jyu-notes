{{-- Home "Latest" timeline items. $items from App\Services\HomeFeedService::latest(). --}}
@php $loc = app()->getLocale(); @endphp
@foreach($items as $item)
    @php
        $m = $item['model'];
        $isPost = $item['type'] === 'post';
        $url = $isPost ? route('public.posts.show', [$loc, $m->slug]) : route('public.tweets.show', [$loc, $m->id]);
        $kind = $isPost ? __('public.kind_post') : __('public.kind_tweet');
    @endphp
    <article data-feed-item="{{ $item['type'] }}" class="grid md:grid-cols-[76px_minmax(0,1fr)] gap-1.5 md:gap-5 py-6 border-t border-line">
        <div class="flex md:flex-col gap-1.5 md:pt-1 font-mono text-xs text-ink-3">
            <a href="{{ $url }}" class="text-ink-2 hover:text-accent md:text-[13px]"><time datetime="{{ $item['published_at']->toIso8601String() }}">{{ $item['published_at']->format('m/d') }}</time></a>
            <span aria-hidden="true" class="md:hidden">·</span>
            <span>{{ $kind }}</span>
        </div>
        <div class="min-w-0">
            @if($isPost)
                <h3 class="m-0 mb-2 font-serif text-[21px] md:text-[23px] leading-snug font-medium">
                    <a href="{{ $url }}" class="text-ink hover:text-accent">{{ $m->title }}</a>
                </h3>
                @if($m->excerpt)
                    <p class="m-0 text-ink-2">{{ $m->excerpt }}</p>
                @endif
                @if($m->cover_image_path)
                    <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="block mt-3.5 max-w-[460px] rounded-[10px] overflow-hidden bg-paper-3">
                        <img src="{{ media_url($m->cover_image_path) }}" alt="" loading="lazy" class="w-full h-[200px] object-cover">
                    </a>
                @endif
            @else
                {{-- Tweet: full Markdown (YouTube shortcodes become embeds); long ones show ~500 characters first. --}}
                @php
                    $rendered = app(\App\Support\MarkdownRenderer::class)->render((string) $m->body);
                    $plainLength = mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags($rendered))));
                    $isLong = $plainLength > \App\Http\Controllers\Public\HomeController::FEED_EXCERPT_CHARS;
                @endphp
                <div @if($isLong) x-data="{ expanded: false }" @endif>
                    <div data-feed-body class="prose-blog tweet-body text-base leading-[1.75] text-ink relative [overflow-wrap:anywhere]"
                        @if($isLong) :class="expanded ? '' : 'max-h-[26rem] overflow-hidden'" @endif>
                        {!! $rendered !!}
                        @if($isLong)
                            <div x-show="!expanded" class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-paper via-paper/80 to-transparent pointer-events-none"></div>
                        @endif
                    </div>
                    @if($isLong)
                        <button type="button" x-show="!expanded" @click="expanded = true" :aria-expanded="expanded"
                            class="mt-1 inline-flex items-center min-h-9 text-sm font-semibold text-accent hover:text-accent-ink">{{ __('public.tweets_expand') }}</button>
                    @endif
                </div>
                @include('components.partials.link-preview', ['tweet' => $m])
                @include('components.partials.tweet-media-grid', ['media' => $m->media ?? []])
            @endif
            @if($m->tags->isNotEmpty() || $isPost)
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    @foreach($m->tags as $tag)
                        <x-ui.tag :tag="$tag" size="sm" />
                    @endforeach
                    @if($isPost)
                        <span class="font-mono text-xs text-ink-3">{{ __('public.minutes', ['n' => $m->reading_time]) }}</span>
                    @endif
                </div>
            @endif
        </div>
    </article>
@endforeach
