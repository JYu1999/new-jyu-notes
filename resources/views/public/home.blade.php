@extends('layouts.public')

@section('title', config('app.name'))

@section('content')
@php $loc = app()->getLocale(); @endphp
<div class="max-w-[1200px] mx-auto px-4 md:px-8 pt-7 md:pt-14 pb-16 md:pb-24">

    {{-- Intro + topics --}}
    <section class="flex flex-wrap items-end gap-6 md:gap-12 pb-8 md:pb-12 border-b border-line">
        <div class="grow-[999] basis-[560px] min-w-0">
            <p class="m-0 mb-2 md:mb-3 font-mono text-xs md:text-[13px] tracking-[0.04em] text-accent">{{ __('footer.tagline') }}</p>
            <h1 class="m-0 font-serif text-[32px] md:text-[52px] leading-tight md:leading-[1.15] font-medium tracking-tight">{{ __('public.home_hello') }}</h1>
            <div class="flex flex-wrap items-center gap-2.5 mt-6">
                <x-ui.button :href="$workUrl" variant="primary">{{ __('public.home_work_cta') }}<span aria-hidden="true">→</span></x-ui.button>
            </div>
        </div>

        @if($topics->isNotEmpty())
            <nav aria-label="{{ __('public.home_topics') }}"
                class="grow basis-[340px] min-w-0 flex md:grid md:grid-cols-2 gap-2 md:gap-2.5 overflow-x-auto scrollbar-none -mx-4 px-4 md:mx-0 md:px-0">
                @foreach($topics as $topic)
                    @php $slug = $topic->slug($loc); @endphp
                    <a href="{{ route('public.tags.show', [$loc, $slug]) }}"
                        class="flex-none flex items-center justify-between gap-2 min-h-11 md:min-h-12 px-3.5 rounded-[var(--radius-control)] border border-line bg-card text-sm md:text-[15px] text-ink hover:border-line-2 hover:text-ink">
                        <span class="flex items-center gap-2.5">
                            <span aria-hidden="true" class="size-2 rounded-full" style="background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $topic->color) ? $topic->color : 'var(--color-ink-3)' }}"></span>
                            {{ $topic->name($loc) }}
                        </span>
                        <span class="font-mono text-xs text-ink-3">{{ $topic->posts_count }}</span>
                    </a>
                @endforeach
                <a href="{{ route('public.topics', $loc) }}"
                    class="flex-none flex items-center justify-between gap-2 min-h-11 md:min-h-12 px-3.5 rounded-[var(--radius-control)] border border-line bg-card text-sm md:text-[15px] text-ink hover:border-line-2 hover:text-ink">
                    <span class="flex items-center gap-2.5"><span aria-hidden="true" class="size-2 rounded-full bg-ink-3"></span>{{ __('public.home_all_tags') }}</span>
                    <span aria-hidden="true" class="font-mono text-xs text-ink-3">→</span>
                </a>
            </nav>
        @endif
    </section>

    {{-- Featured: hidden entirely when there are none --}}
    @if($featured->isNotEmpty())
        @include('public.home._featured')
    @else
        <div class="h-8 md:h-12"></div>
    @endif

    <div class="flex flex-wrap gap-14 items-start">
        {{-- Latest: posts + tweets timeline --}}
        <section id="latest" aria-labelledby="latest-h" class="grow-[999] basis-[560px] min-w-0"
            x-data="homeFeed({ url: @js(route('public.home', $loc)), type: @js($feedType), next: @js($feedNext) })">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
                <x-ui.section-heading id="latest-h">{{ __('public.home_latest') }}</x-ui.section-heading>
                <div role="group" aria-label="{{ __('public.feed_filter') }}" class="flex gap-1 p-1 rounded-xl bg-paper-2">
                    @foreach(['all' => __('public.feed_all'), 'post' => __('public.feed_posts'), 'tweet' => __('public.feed_tweets')] as $key => $label)
                        <a href="{{ route('public.home', ['locale' => $loc, 'type' => $key === 'all' ? null : $key]) }}#latest"
                            data-feed-tab="{{ $key }}"
                            @click.prevent="switchTo('{{ $key }}')"
                            :aria-pressed="type === '{{ $key }}' ? 'true' : 'false'"
                            aria-pressed="{{ $feedType === $key ? 'true' : 'false' }}"
                            :class="type === '{{ $key }}' ? 'bg-card text-ink font-semibold shadow-sm' : 'text-ink-2'"
                            class="inline-flex items-center h-11 md:h-9 px-3.5 rounded-[9px] text-sm {{ $feedType === $key ? 'bg-card text-ink font-semibold shadow-sm' : 'text-ink-2' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div x-ref="list" :aria-busy="loading ? 'true' : 'false'" :class="loading && 'opacity-60'" class="transition-opacity">
                @include('public.home._feed-items', ['items' => $feedItems])
            </div>

            <p x-ref="empty" @class(['py-10 text-center text-ink-3 border-t border-line', 'hidden' => $feedItems->isNotEmpty()])>{{ __('public.feed_empty') }}</p>
            <p x-show="error" x-cloak role="alert" class="py-3 text-center text-sm text-danger">{{ __('public.feed_error') }}</p>

            <x-ui.button size="lg" class="w-full mt-2 {{ $feedNext ? '' : 'hidden' }}" x-ref="more"
                :href="route('public.home', ['locale' => $loc, 'type' => $feedType === 'all' ? null : $feedType, 'before' => $feedNext]).'#latest'"
                @click.prevent="loadMore()" ::class="{ 'hidden': !next }" ::aria-disabled="loading">
                <span x-show="!loading">{{ __('public.load_more') }}</span>
                <span x-show="loading" x-cloak>{{ __('public.loading_more') }}</span>
            </x-ui.button>
        </section>

        {{-- Sidebar --}}
        <aside class="grow basis-[300px] min-w-0 flex flex-col gap-10">
            @if($series->isNotEmpty())
                <section aria-labelledby="series-h">
                    <x-ui.section-heading id="series-h" size="md" class="mb-3.5">{{ __('public.home_series') }}</x-ui.section-heading>
                    <div class="flex flex-col gap-2.5">
                        @foreach($series as $cat)
                            <x-ui.card :href="route('public.categories.show', [$loc, $cat->slug($loc)])" padding="sm">
                                <span class="block font-semibold text-[15px] leading-snug">{{ $cat->name($loc) }}</span>
                                <span class="block mt-1.5 font-mono text-xs text-ink-3">{{ __('public.n_posts', ['n' => $cat->posts_count]) }}</span>
                            </x-ui.card>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($tags->isNotEmpty())
                <section aria-labelledby="tags-h">
                    <x-ui.section-heading id="tags-h" size="md" class="mb-3.5">{{ __('public.home_tags') }}</x-ui.section-heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tags as $tag)
                            <x-ui.tag :tag="$tag" :count="$tag->posts_count" />
                        @endforeach
                    </div>
                </section>
            @endif

            @if($latestChange)
                <section aria-labelledby="site-h" class="p-5 rounded-[var(--radius-card)] border border-dashed border-line-2">
                    <h2 id="site-h" class="m-0 mb-1.5 font-sans text-sm font-bold tracking-normal">{{ __('public.home_site_updates') }}</h2>
                    <p class="m-0 mb-1 text-sm text-ink-2 changelog-md" lang="en">{!! $latestChange->html !!}</p>
                    <p class="m-0 mb-2 font-mono text-xs text-ink-3">{{ $latestChange->date }}</p>
                    <a href="{{ route('changelog') }}" class="inline-flex items-center min-h-11 text-sm text-accent hover:text-accent-ink">{{ __('public.home_changelog_link') }}</a>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
