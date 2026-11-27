{{-- Home "Latest" timeline items. $items from App\Services\HomeFeedService::latest(). --}}
@php $loc = app()->getLocale(); @endphp
@foreach($items as $item)
    @php
        $m = $item['model'];
        $isPost = $item['type'] === 'post';
        $url = $isPost ? route('public.posts.show', [$loc, $m->slug]) : route('public.tweets.show', [$loc, $m->id]);
        $kind = $isPost ? __('public.kind_post') : __('public.kind_tweet');
        $images = $isPost ? [] : collect($m->media ?? [])->where('type', '!=', 'video')->values();
    @endphp
    <article data-feed-item="{{ $item['type'] }}" class="grid md:grid-cols-[76px_minmax(0,1fr)] gap-1.5 md:gap-5 py-6 border-t border-line">
        <div class="flex md:flex-col gap-1.5 md:pt-1 font-mono text-xs text-ink-3">
            <time datetime="{{ $item['published_at']->toIso8601String() }}" class="text-ink-2 md:text-[13px]">{{ $item['published_at']->format('m/d') }}</time>
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
            @else
                <a href="{{ $url }}" class="block text-ink hover:text-accent [overflow-wrap:anywhere]">{{ $m->preview(220) }}</a>
                @php
                    $videos = collect($m->media ?? [])->where('type', 'video');
                    $hasEmbed = str_contains((string) $m->body, '{{< youtube');
                @endphp
                @if($images->isEmpty() && ($videos->isNotEmpty() || $hasEmbed))
                    <a href="{{ $url }}" aria-label="{{ __('public.feed_video') }}"
                        class="flex items-center justify-center mt-3.5 max-w-[460px] h-[200px] rounded-[10px] bg-ink text-paper hover:text-paper">
                        <span class="flex items-center justify-center size-14 rounded-full bg-paper/90 text-ink">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </a>
                @endif
                @if($images->isNotEmpty())
                    @php $first = $images->first(); @endphp
                    <a href="{{ $url }}" class="relative block mt-3.5 max-w-[460px] rounded-[10px] overflow-hidden bg-paper-3">
                        <img src="{{ media_url($first['path']) }}" alt="{{ $first['alt'] ?? '' }}" loading="lazy"
                            class="w-full h-[200px] object-cover {{ ! empty($first['sensitive']) ? 'blur-xl scale-105' : '' }}">
                        @if($images->count() > 1)
                            <span class="absolute right-2 bottom-2 px-2 py-0.5 rounded-md bg-ink/70 text-paper text-xs font-mono">{{ __('public.n_photos', ['n' => $images->count()]) }}</span>
                        @endif
                    </a>
                @endif
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
