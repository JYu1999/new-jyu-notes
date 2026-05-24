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

<div class="bg-card border border-line rounded-md divide-y divide-line">
    @forelse($tweets as $t)
        <div class="p-4 hover:bg-paper-2">
            <div class="flex items-baseline justify-between gap-3 mb-2">
                <a href="{{ route('admin.tweets.edit', $t->id) }}" class="text-sm font-medium text-ink hover:text-accent line-clamp-2 flex-1">
                    {{ \Illuminate\Support\Str::limit($t->body, 120) }}
                </a>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="text-xs px-2 py-0.5 rounded font-mono
                        {{ $t->trashed() ? 'bg-danger/10 text-danger'
                            : ($t->status === 'published' ? 'bg-good/10 text-good'
                            : ($t->status === 'draft' ? 'bg-warn/10 text-warn' : 'bg-ink-3/10 text-ink-3')) }}">
                        {{ $t->trashed() ? 'trashed' : $t->status }}
                    </span>
                    <span class="text-xs font-mono uppercase text-ink-3">{{ $t->locale }}</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs text-ink-3 font-mono">
                <span>{{ $t->published_at?->format('Y/m/d H:i') ?? '—' }}</span>
                <div class="flex gap-3">
                    @if($t->trashed())
                        <form method="POST" action="{{ route('admin.tweets.restore', $t->id) }}" class="inline">
                            @csrf<button class="text-accent hover:text-accent-ink">還原</button>
                        </form>
                    @else
                        <a href="{{ route('admin.tweets.edit', $t->id) }}" class="text-accent hover:text-accent-ink">編輯</a>
                        <form method="POST" action="{{ route('admin.tweets.destroy', $t->id) }}" class="inline" onsubmit="return confirm('確定？')">
                            @csrf @method('DELETE')
                            <button class="text-danger hover:underline">刪除</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="p-8 text-center text-ink-3 text-sm">沒有資料</div>
    @endforelse
</div>

<div class="mt-6">{{ $tweets->withQueryString()->links() }}</div>
@endsection
