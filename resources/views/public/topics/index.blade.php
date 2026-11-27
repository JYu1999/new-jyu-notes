@extends('layouts.public')

@section('title', __('public.topics_title') . ' · ' . config('app.name'))

@section('content')
@php $loc = app()->getLocale(); @endphp
<div class="max-w-[1200px] mx-auto px-4 md:px-8 py-10 md:py-14">
    <header class="mb-10">
        <h1 class="font-serif text-4xl md:text-[40px] font-medium tracking-tight mb-2">{{ __('public.topics_title') }}</h1>
        <p class="text-ink-2">{{ __('public.topics_intro') }}</p>
    </header>

    @if($categories->isEmpty() && $tags->isEmpty())
        <x-ui.card padding="lg" class="text-center text-ink-3">{{ __('public.topics_empty') }}</x-ui.card>
    @endif

    @if($categories->isNotEmpty())
        <section aria-labelledby="topics-categories" class="mb-12">
            <x-ui.section-heading id="topics-categories" class="mb-4">{{ __('public.topics_categories') }}</x-ui.section-heading>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($categories as $cat)
                    <x-ui.card :href="route('public.categories.show', [$loc, $cat->slug($loc)])" padding="sm" class="flex flex-col gap-1.5 min-h-12">
                        <span class="flex items-start justify-between gap-3">
                            <span class="font-semibold leading-snug">{{ $cat->name($loc) }}</span>
                            <span class="font-mono text-xs text-ink-3 whitespace-nowrap pt-0.5">{{ __('public.n_posts', ['n' => $cat->posts_count]) }}</span>
                        </span>
                        @if($desc = $cat->description($loc))
                            <span class="text-sm text-ink-2 line-clamp-2">{{ $desc }}</span>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    @if($tags->isNotEmpty())
        <section aria-labelledby="topics-tags">
            <x-ui.section-heading id="topics-tags" class="mb-4">{{ __('public.topics_tags') }}</x-ui.section-heading>
            <div class="flex flex-wrap gap-2">
                @foreach($tags as $tag)
                    <x-ui.tag :tag="$tag" :count="$tag->content_count" class="min-h-11" />
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
