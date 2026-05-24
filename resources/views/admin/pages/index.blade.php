@extends('layouts.admin')

@section('title', 'Pages')

@section('content')
<header class="mb-6 flex items-center justify-between gap-3 flex-wrap">
    <div>
        <h1 class="font-serif text-2xl font-semibold">Pages</h1>
        <p class="text-sm text-ink-3 mt-1">共 {{ $pages->total() }} 個頁面</p>
    </div>
    <a href="{{ route('admin.pages.create') }}" class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">+ 新增頁面</a>
</header>

<div class="bg-card border border-line rounded-md overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-paper-2 border-b border-line">
            <tr class="text-left text-xs uppercase tracking-wider text-ink-3 font-mono">
                <th class="px-4 py-3">標題</th>
                <th class="px-4 py-3 w-24">Slug</th>
                <th class="px-4 py-3 w-20">狀態</th>
                <th class="px-4 py-3 w-16">語言</th>
                <th class="px-4 py-3 w-28">更新</th>
                <th class="px-4 py-3 w-32 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse($pages as $page)
                <tr class="hover:bg-paper-2">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.pages.edit', $page->id) }}" class="font-medium text-ink hover:text-accent">{{ $page->title }}</a>
                    </td>
                    <td class="px-4 py-3 text-xs font-mono text-ink-3">{{ $page->slug }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded font-mono
                            {{ $page->trashed() ? 'bg-danger/10 text-danger'
                                : ($page->status === 'published' ? 'bg-good/10 text-good'
                                : ($page->status === 'draft' ? 'bg-warn/10 text-warn' : 'bg-ink-3/10 text-ink-3')) }}">
                            {{ $page->trashed() ? 'trashed' : $page->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs font-mono uppercase text-ink-3">{{ $page->locale }}</td>
                    <td class="px-4 py-3 text-xs text-ink-3 font-mono">{{ $page->updated_at->format('Y/m/d') }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($page->trashed())
                            <form method="POST" action="{{ route('admin.pages.restore', $page->id) }}" class="inline">
                                @csrf
                                <button class="text-xs text-accent hover:text-accent-ink">還原</button>
                            </form>
                        @else
                            <a href="{{ url('/' . $page->locale . '/' . $page->slug) }}" target="_blank" class="text-xs text-ink-3 hover:text-accent mr-3">↗</a>
                            <a href="{{ route('admin.pages.edit', $page->id) }}" class="text-xs text-accent hover:text-accent-ink mr-3">編輯</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page->id) }}" class="inline" onsubmit="return confirm('確定？')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-danger hover:underline">刪除</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-ink-3">沒有頁面</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $pages->links() }}</div>
@endsection
