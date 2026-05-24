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
