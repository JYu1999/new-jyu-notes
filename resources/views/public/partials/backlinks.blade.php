{{-- "Mentioned in" (JYU-536): posts and tweets that link to this page, as cards. --}}
@if($backlinks->isNotEmpty())
    <section id="backlinks" aria-labelledby="backlinks-h" class="mt-12 scroll-mt-40 lg:scroll-mt-28">
        <h2 id="backlinks-h" class="m-0 mb-1 font-serif text-[22px] font-semibold">{{ __('public.mentioned_in') }}</h2>
        <p class="m-0 mb-4 text-sm text-ink-3">{{ __('public.post_mentioned_in_hint') }}</p>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach($backlinks as $bl)
                @php $isTweet = $bl instanceof \App\Models\Tweet; @endphp
                <x-ui.card :href="$isTweet ? route('public.tweets.show', [$bl->locale, $bl->id]) : route('public.posts.show', [$bl->locale, $bl->slug])"
                    class="flex flex-col gap-1.5">
                    <span class="font-mono text-xs text-ink-3">{{ $isTweet ? __('public.kind_tweet') : __('public.kind_post') }} · {{ $bl->published_at?->format('Y/m/d') }}</span>
                    @if($isTweet)
                        <span class="text-[15px] text-ink-2 line-clamp-3">{{ $bl->preview(120) }}</span>
                    @else
                        <span class="font-semibold leading-snug">{{ $bl->title }}</span>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    </section>
@endif
