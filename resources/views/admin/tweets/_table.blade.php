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
