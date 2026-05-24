@extends('layouts.public')

@section('title', config('app.name'))

@section('content')
<div class="max-w-6xl mx-auto px-6 py-12">
    {{-- Hero --}}
    <section class="mb-16 text-center md:text-left max-w-3xl">
        <h1 class="font-serif text-4xl md:text-5xl font-semibold leading-tight tracking-tight text-ink mb-4">
            @lang('footer.tagline')
        </h1>
        <p class="text-ink-2 text-base md:text-lg">
            {{ __('public.home_intro') }}
        </p>
    </section>

    <div class="grid lg:grid-cols-[2fr_1fr] gap-10">
        {{-- Featured posts --}}
        <section>
            <div class="flex items-baseline justify-between mb-6">
                <h2 class="font-serif text-xl font-semibold">{{ __('public.home_featured_posts') }}</h2>
                <a href="{{ route('public.posts.index', app()->getLocale()) }}" class="text-sm text-accent hover:text-accent-ink font-mono">{{ __('public.home_view_all') }}</a>
            </div>
            @if($featuredPosts->isEmpty())
                <div class="bg-card border border-line rounded-md p-8 text-center text-ink-3 text-sm">
                    {{ __('public.home_no_featured') }}
                </div>
            @else
                <div class="grid sm:grid-cols-2 gap-5">
                    @foreach($featuredPosts as $post)
                        <x-post-card :post="$post" />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Sidebar --}}
        <aside class="space-y-8">
            <section>
                <h2 class="font-serif text-xl font-semibold mb-4">{{ __('public.home_recent_tweets') }}</h2>
                <div class="space-y-4">
                    @forelse($recentTweets as $tweet)
                        <x-tweet-card :tweet="$tweet" />
                    @empty
                        <p class="text-ink-3 text-sm">{{ __('public.no_tweets') }}</p>
                    @endforelse
                </div>
                <a href="{{ route('public.tweets.index', app()->getLocale()) }}" class="block mt-4 text-sm text-accent hover:text-accent-ink font-mono">{{ __('public.home_view_all_tweets') }}</a>
            </section>

            @if($popularTags->isNotEmpty())
                <section>
                    <h2 class="font-serif text-xl font-semibold mb-4">{{ __('public.home_popular_tags') }}</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($popularTags as $tag)
                            @php $tslug = $tag->slug(app()->getLocale()); @endphp
                            @if($tslug)
                                <a href="{{ route('public.tags.show', [app()->getLocale(), $tslug]) }}" class="font-mono text-xs px-3 py-1.5 bg-card border border-line rounded-full text-ink-2 hover:text-accent hover:border-accent">
                                    #{{ $tag->name(app()->getLocale()) }}
                                    <span class="text-ink-3 ml-1">{{ $tag->posts_count ?? '' }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
