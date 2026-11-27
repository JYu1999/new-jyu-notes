@extends('layouts.admin')

@section('title', '文章')

@section('content')
@php
    $tabs = ['all' => '全部', 'published' => '已發布', 'draft' => '草稿', 'hidden' => '隱藏', 'featured' => '精選', 'trashed' => '垃圾桶'];
    $langNames = \App\Services\TranslationStatusService::LANGUAGE_NAMES;
    $tabUrl = fn ($key) => route('admin.posts.index', array_filter([...$filters, 'status' => $key === 'all' ? null : $key, 'translation' => $filters['translation']]));
@endphp
<header class="flex flex-wrap items-center gap-4 mb-5">
    <h1 class="m-0 font-serif text-[32px] font-medium">文章</h1>
    <span class="flex-1"></span>
    <a href="{{ route('admin.posts.create') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-ink text-paper text-sm font-semibold hover:bg-accent hover:text-paper">新增文章</a>
</header>


{{-- Status tabs (articles; the trash counts single language versions) --}}
<nav aria-label="狀態" class="flex flex-wrap gap-0.5 border-b border-line mb-4">
    @foreach($tabs as $key => $label)
        @php $active = $filters['status'] === $key; @endphp
        <a href="{{ $tabUrl($key) }}" @if($active) aria-current="page" @endif data-status-tab="{{ $key }}"
            class="inline-flex items-center gap-1.5 h-11 px-3.5 -mb-px border-b-2 text-sm {{ $active ? 'border-accent text-ink font-semibold' : 'border-transparent text-ink-2 hover:text-accent' }}">
            {{ $label }} <span class="font-normal text-ink-3">{{ $counts[$key] }}</span>
        </a>
    @endforeach
</nav>

@if($filters['status'] === 'featured')
    @include('admin.posts._featured', ['articles' => $featuredList])
@else
{{-- Filters: auto-apply via AJAX --}}
<div x-data="liveFilter({ url: '{{ route('admin.posts.index') }}', target: '#post-results' })" class="mb-3.5">
    <form @input.debounce.300ms="submit($event)" @change="submit($event)" class="flex flex-wrap items-center gap-2">
        @if($filters['status'] !== 'all')<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <label class="flex items-center gap-2 h-10 px-3 min-w-[260px] rounded-lg border border-line-2 bg-card text-ink-3">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <span class="sr-only">搜尋標題、slug、內文</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="搜尋標題、slug、內文" class="flex-1 min-w-0 border-0 bg-transparent text-sm text-ink focus:outline-none">
        </label>
        @if($filters['status'] !== 'trashed')
            <select name="category" aria-label="分類" class="h-10 px-2.5 rounded-lg border border-line-2 bg-transparent text-sm text-ink-2">
                <option value="">分類：全部</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($filters['category'] === $cat->id)>{{ $cat->name('zh') }}</option>
                @endforeach
            </select>
            <select name="tag" aria-label="標籤" class="h-10 px-2.5 rounded-lg border border-line-2 bg-transparent text-sm text-ink-2">
                <option value="">標籤：全部</option>
                @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" @selected($filters['tag'] === $tag->id)>#{{ $tag->name('zh') }}</option>
                @endforeach
            </select>
            <select name="translation" aria-label="翻譯狀態" class="h-10 px-2.5 rounded-lg border border-line-2 bg-transparent text-sm text-ink-2">
                <option value="">翻譯狀態：全部</option>
                <optgroup label="尚未翻譯">
                    @foreach($langNames as $code => $name)
                        <option value="missing:{{ $code }}" @selected($filters['translation'] === "missing:{$code}")>缺{{ $name }}版</option>
                    @endforeach
                </optgroup>
                <optgroup label="比原文舊">
                    @foreach($langNames as $code => $name)
                        <option value="stale:{{ $code }}" @selected($filters['translation'] === "stale:{$code}")>{{ $name }}版比原文舊</option>
                    @endforeach
                </optgroup>
            </select>
            <span class="flex-1"></span>
            <select name="sort" aria-label="排序" class="h-10 px-2.5 rounded-lg border-0 bg-transparent text-sm text-ink-2">
                @foreach(['updated' => '最後修改', 'published' => '發布時間', 'views' => '瀏覽數'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['sort'] === $key)>排序：{{ $label }}</option>
                @endforeach
            </select>
        @endif
        <span x-show="loading" x-cloak class="text-xs text-ink-3 animate-pulse">載入中…</span>
    </form>
</div>

<div id="post-results">
    @include('admin.posts._table')
</div>
@endif
@endsection
