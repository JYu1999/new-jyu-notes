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
            <x-ui.section-heading size="md" class="mb-4">{{ __('public.search_posts') }}</x-ui.section-heading>
            <div class="space-y-4">
                @foreach($results['posts'] as $post)
                    <x-ui.card as="article" padding="none">
                        <a href="{{ route('public.posts.show', [app()->getLocale(), $post->slug]) }}" class="block p-5">
                            <h3 class="font-serif text-lg font-medium text-ink hover:text-accent">{{ $post->title }}</h3>
                            @if($post->excerpt)
                                <p class="text-sm text-ink-2 mt-2 line-clamp-2">{{ $post->excerpt }}</p>
                            @endif
                            <div class="mt-3 text-xs text-ink-3 font-mono">
                                {{ $post->published_at?->format('Y/m/d') }} · {{ $post->views_count }} {{ __('public.views') }}
                            </div>
                        </a>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    @if($results['tweets']->isNotEmpty())
        <section>
            <x-ui.section-heading size="md" class="mb-4">{{ __('public.search_tweets') }}</x-ui.section-heading>
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
