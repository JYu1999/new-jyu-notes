@extends('layouts.admin')

@section('title', 'Tweets')

@section('content')
<header class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="font-serif text-2xl font-semibold">Tweets</h1>
        <p class="text-sm text-ink-3 mt-1">總共 {{ $counts['all'] }} 則</p>
    </div>
    <a href="{{ route('admin.tweets.create') }}" class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">+ 新增 Tweet</a>
</header>

<div class="border-b border-line mb-6 flex items-center gap-1 overflow-x-auto">
    @php
        $tabs = [
            'all' => '全部',
            'published' => '已發布',
            'draft' => '草稿',
            'hidden' => '隱藏',
            'trashed' => '垃圾桶',
        ];
    @endphp
    @foreach($tabs as $key => $label)
        @php $active = $currentStatus === $key || ($key === 'all' && empty($currentStatus)); @endphp
        <a href="{{ route('admin.tweets.index', ['status' => $key]) }}"
           class="px-4 py-2.5 text-sm whitespace-nowrap border-b-2 -mb-px {{ $active ? 'border-accent text-accent font-medium' : 'border-transparent text-ink-2 hover:text-accent' }}">
            {{ $label }} <span class="text-ink-3 font-mono text-xs ml-1">{{ $counts[$key] }}</span>
        </a>
    @endforeach
</div>

{{-- Filters auto-apply via AJAX --}}
<div x-data="liveFilter({ url: '{{ route('admin.tweets.index') }}', target: '#tweet-results' })" class="mb-6">
    <form @input.debounce.300ms="submit($event)" @change="submit($event)" class="flex items-center gap-3">
        @if($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
        <input
            type="search" name="q" value="{{ $currentSearch }}"
            placeholder="搜尋 tweet 內文..."
            class="flex-1 max-w-md px-3 py-1.5 bg-card border border-line rounded text-sm focus:border-accent focus:outline-none">
        <select name="locale" class="bg-card border border-line rounded px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
            <option value="">所有語言</option>
            @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                <option value="{{ $loc }}" {{ $currentLocale === $loc ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
            @endforeach
        </select>
        <span x-show="loading" x-cloak class="text-xs text-ink-3 font-mono animate-pulse">載入中…</span>
    </form>
</div>

<div id="tweet-results">
    @include('admin.tweets._table')
</div>
@endsection
