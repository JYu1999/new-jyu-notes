@extends('layouts.public')

@section('title', '文章 · ' . config('app.name'))

@section('content')
<div class="max-w-6xl mx-auto px-6 py-12">
    <header class="mb-10 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl md:text-4xl font-semibold">@lang('nav.posts')</h1>
            <p class="text-ink-3 text-sm mt-2 font-mono">共 {{ $posts->total() }} 篇</p>
        </div>

        {{-- Sort selector --}}
        <form method="GET" class="flex items-center gap-3">
            @if($selectedTag)<input type="hidden" name="tag" value="{{ $selectedTag }}">@endif
            @if($selectedCategory)<input type="hidden" name="category" value="{{ $selectedCategory }}">@endif
            <label for="sort" class="text-sm text-ink-2">排序：</label>
            <select name="sort" id="sort" onchange="this.form.submit()" class="bg-card border border-line rounded px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                <option value="published" {{ $sort === 'published' ? 'selected' : '' }}>發布時間</option>
                <option value="updated" {{ $sort === 'updated' ? 'selected' : '' }}>最後更新</option>
                <option value="views" {{ $sort === 'views' ? 'selected' : '' }}>觀看次數</option>
            </select>
        </form>
    </header>

    <div class="grid lg:grid-cols-[1fr_220px] gap-10">
        <div>
            @if($posts->isEmpty())
                <p class="text-ink-3">目前沒有文章。</p>
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
                    <h3 class="font-mono text-xs uppercase tracking-widest text-ink-3 mb-3">分類</h3>
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
                    <h3 class="font-mono text-xs uppercase tracking-widest text-ink-3 mb-3">標籤</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($tags as $tag)
                            @php $tagSlug = $tag->slug(app()->getLocale()); @endphp
                            @if($tagSlug)
                                <a href="{{ route('public.tags.show', [app()->getLocale(), $tagSlug]) }}" class="font-mono text-[11px] px-2 py-1 bg-card border border-line rounded-full text-ink-2 hover:text-accent hover:border-accent">
                                    #{{ $tag->name(app()->getLocale()) }}
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
