@php
    $postsCount = $results['posts']->count();
    $tweetsCount = $results['tweets']->count();
@endphp

@if(trim($q) === '')
    <p class="text-ink-3 text-sm font-mono">{{ __('public.search_placeholder') }}</p>
@else
    <p class="text-sm text-ink-3 font-mono mb-6">
        {{ __('public.search_summary', ['total' => $postsCount + $tweetsCount, 'posts' => $postsCount, 'tweets' => $tweetsCount]) }}
    </p>

    @if($results['posts']->isNotEmpty())
        <section class="mb-10">
            <h2 class="font-serif text-xl font-semibold mb-4">{{ __('public.search_posts') }}</h2>
            <div class="space-y-4">
                @foreach($results['posts'] as $post)
                    <article class="border border-line rounded-md p-5 bg-card hover:border-accent transition-colors">
                        <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}" class="block">
                            <h3 class="font-serif text-lg font-medium text-ink hover:text-accent">{{ $post->title }}</h3>
                            @if($post->excerpt)
                                <p class="text-sm text-ink-2 mt-2 line-clamp-2">{{ $post->excerpt }}</p>
                            @endif
                            <div class="mt-3 text-xs text-ink-3 font-mono">
                                {{ $post->published_at?->format('Y/m/d') }} · {{ $post->views_count }} {{ __('public.views') }}
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($results['tweets']->isNotEmpty())
        <section>
            <h2 class="font-serif text-xl font-semibold mb-4">{{ __('public.search_tweets') }}</h2>
            <div class="space-y-4">
                @foreach($results['tweets'] as $tweet)
                    <a href="{{ route('public.tweets.show', [app()->getLocale(), $tweet->id]) }}" class="block">
                        <x-tweet-card :tweet="$tweet" preview />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($postsCount === 0 && $tweetsCount === 0)
        <p class="text-ink-3 text-center py-12">{{ __('public.search_no_results') }}</p>
    @endif
@endif
