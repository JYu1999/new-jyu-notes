@extends('layouts.admin')

@section('title', 'Posts')

@section('content')
<header class="mb-6 flex items-center justify-between gap-4 flex-wrap">
    <div>
        <h1 class="font-serif text-2xl font-semibold">Posts</h1>
        <p class="text-sm text-ink-3 mt-1">總共 {{ $counts['all'] }} 篇</p>
    </div>
    <a href="{{ route('admin.posts.create') }}" class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">
        + 新增文章
    </a>
</header>

{{-- Status tabs --}}
<div class="border-b border-line mb-6 flex items-center gap-1 overflow-x-auto">
    @php
        $tabs = [
            'all' => ['label' => '全部', 'count' => $counts['all']],
            'published' => ['label' => '已發布', 'count' => $counts['published']],
            'draft' => ['label' => '草稿', 'count' => $counts['draft']],
            'hidden' => ['label' => '隱藏', 'count' => $counts['hidden']],
            'trashed' => ['label' => '垃圾桶', 'count' => $counts['trashed']],
        ];
    @endphp
    @foreach($tabs as $key => $tab)
        @php
            $active = $currentStatus === $key || ($key === 'all' && empty($currentStatus));
        @endphp
        <a href="{{ route('admin.posts.index', ['status' => $key]) }}"
           class="px-4 py-2.5 text-sm whitespace-nowrap border-b-2 -mb-px {{ $active ? 'border-accent text-accent font-medium' : 'border-transparent text-ink-2 hover:text-accent' }}">
            {{ $tab['label'] }} <span class="text-ink-3 font-mono text-xs ml-1">{{ $tab['count'] }}</span>
        </a>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" class="mb-6 flex items-center gap-3">
    @if($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
    <input
        type="search"
        name="q"
        value="{{ $currentSearch }}"
        placeholder="搜尋標題或摘要..."
        class="flex-1 max-w-md px-3 py-1.5 bg-card border border-line rounded text-sm focus:border-accent focus:outline-none"
    >
    <select name="locale" class="bg-card border border-line rounded px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
        <option value="">所有語言</option>
        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
            <option value="{{ $loc }}" {{ $currentLocale === $loc ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
        @endforeach
    </select>
    <button class="bg-paper-2 border border-line px-3 py-1.5 rounded text-sm hover:border-accent">套用</button>
</form>

<div class="bg-card border border-line rounded-md overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-paper-2 border-b border-line">
            <tr class="text-left text-xs uppercase tracking-wider text-ink-3 font-mono">
                <th class="px-4 py-3">標題</th>
                <th class="px-4 py-3 w-20">狀態</th>
                <th class="px-4 py-3 w-16">語言</th>
                <th class="px-4 py-3 w-20">觀看</th>
                <th class="px-4 py-3 w-28">更新</th>
                <th class="px-4 py-3 w-28 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse($posts as $post)
                <tr class="hover:bg-paper-2">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.posts.edit', $post->id) }}" class="font-medium text-ink hover:text-accent block truncate max-w-md">
                            {{ $post->title ?: '(no title)' }}
                        </a>
                        @if($post->categories->isNotEmpty())
                            <div class="text-xs text-ink-3 font-mono mt-0.5">
                                {{ $post->categories->map(fn($c) => $c->name(app()->getLocale()))->join(' · ') }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $statusColor = [
                                'published' => 'bg-good/10 text-good',
                                'draft' => 'bg-warn/10 text-warn',
                                'hidden' => 'bg-ink-3/10 text-ink-3',
                            ][$post->status] ?? 'bg-ink-3/10 text-ink-3';
                        @endphp
                        @if($post->trashed())
                            <span class="text-xs px-2 py-0.5 rounded font-mono bg-danger/10 text-danger">trashed</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded font-mono {{ $statusColor }}">{{ $post->status }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs font-mono uppercase text-ink-3">{{ $post->locale }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-ink-3">{{ $post->views_count }}</td>
                    <td class="px-4 py-3 text-xs text-ink-3 font-mono">{{ $post->updated_at->format('Y/m/d') }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($post->trashed())
                            <form method="POST" action="{{ route('admin.posts.restore', $post->id) }}" class="inline">
                                @csrf
                                <button class="text-xs text-accent hover:text-accent-ink">還原</button>
                            </form>
                        @else
                            <a href="{{ route('admin.posts.edit', $post->id) }}" class="text-xs text-accent hover:text-accent-ink mr-3">編輯</a>
                            <form method="POST" action="{{ route('admin.posts.destroy', $post->id) }}" class="inline" onsubmit="return confirm('確定要刪除？')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs text-danger hover:underline">刪除</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-ink-3">沒有符合條件的文章</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $posts->withQueryString()->links() }}</div>
@endsection
