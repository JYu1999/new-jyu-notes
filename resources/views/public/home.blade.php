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

    <div class="grid lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-10">
        {{-- Featured posts --}}
        <section>
            <x-ui.section-heading class="mb-6" :action-href="route('public.posts.index', app()->getLocale())" :action-label="__('public.home_view_all')">{{ __('public.home_featured_posts') }}</x-ui.section-heading>
            @if($featuredPosts->isEmpty())
                <x-ui.card padding="lg" class="text-center text-ink-3 text-sm">{{ __('public.home_no_featured') }}</x-ui.card>
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
                <x-ui.section-heading size="md" class="mb-4">{{ __('public.home_recent_tweets') }}</x-ui.section-heading>
                <div class="space-y-4">
                    @forelse($recentTweets as $tweet)
                        <x-tweet-card :tweet="$tweet" />
                    @empty
                        <p class="text-ink-3 text-sm">{{ __('public.no_tweets') }}</p>
                    @endforelse
                </div>
                <x-ui.button :href="route('public.tweets.index', app()->getLocale())" size="lg" class="w-full mt-4">{{ __('public.home_view_all_tweets') }}</x-ui.button>
            </section>

            @if($popularTags->isNotEmpty())
                <section>
                    <x-ui.section-heading size="md" class="mb-4">{{ __('public.home_popular_tags') }}</x-ui.section-heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($popularTags as $tag)
                            <x-ui.tag :tag="$tag" :count="$tag->posts_count" />
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
