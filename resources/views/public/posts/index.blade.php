@extends('layouts.public')

@section('title', __('public.posts') . ' · ' . config('app.name'))

@section('content')
<div class="max-w-6xl mx-auto px-6 py-12">
    <header class="mb-10 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl md:text-4xl font-semibold">{{ __('public.posts') }}</h1>
            <p class="text-ink-3 text-sm mt-2 font-mono">{{ __('public.posts_total', ['n' => $posts->total()]) }}</p>
        </div>

        {{-- Sort selector --}}
        <form method="GET" class="flex items-center gap-3">
            @if($selectedTag)<input type="hidden" name="tag" value="{{ $selectedTag }}">@endif
            @if($selectedCategory)<input type="hidden" name="category" value="{{ $selectedCategory }}">@endif
            <label for="sort" class="text-sm text-ink-2">{{ __('public.sort_label') }}</label>
            <select name="sort" id="sort" onchange="this.form.submit()" class="bg-card border border-line rounded px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                <option value="published" {{ $sort === 'published' ? 'selected' : '' }}>{{ __('public.sort_published') }}</option>
                <option value="updated" {{ $sort === 'updated' ? 'selected' : '' }}>{{ __('public.sort_updated') }}</option>
                <option value="views" {{ $sort === 'views' ? 'selected' : '' }}>{{ __('public.sort_views') }}</option>
            </select>
        </form>
    </header>

    <div class="grid lg:grid-cols-[1fr_220px] gap-10">
        <div>
            @if($posts->isEmpty())
                <p class="text-ink-3">{{ __('public.no_posts') }}</p>
            @else
                <div class="grid sm:grid-cols-2 gap-5">
                    @foreach($posts as $post)
                        <x-post-card :post="$post" />
                    @endforeach
                </div>
                <div class="mt-10">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>

        <aside class="space-y-8">
            @if($categories->isNotEmpty())
                <section>
                    <x-ui.section-heading as="h2" size="label" class="mb-3">{{ __('public.categories') }}</x-ui.section-heading>
                    <ul class="space-y-1.5 text-sm">
                        @foreach($categories as $cat)
                            @php $catSlug = $cat->slug(app()->getLocale()); @endphp
                            @if($catSlug)
                                <li>
                                    <a href="{{ route('public.categories.show', [app()->getLocale(), $catSlug]) }}" class="text-ink-2 hover:text-accent flex items-baseline justify-between">
                                        <span>{{ $cat->name(app()->getLocale()) }}</span>
                                        <span class="text-ink-3 font-mono text-xs ml-2">{{ $cat->posts_count ?? 0 }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($tags->isNotEmpty())
                <section>
                    <x-ui.section-heading as="h2" size="label" class="mb-3">{{ __('public.tags') }}</x-ui.section-heading>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($tags as $tag)
                            <x-ui.tag :tag="$tag" size="sm" />
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
