{{-- Admin post list rows (JYU-545). One row per article; the trash shows single language versions. --}}
@php
    $trash = $filters['status'] === 'trashed';
    $badges = \App\Services\TranslationStatusService::BADGES;
    $langNames = \App\Services\TranslationStatusService::LANGUAGE_NAMES;
    $statusLabel = ['published' => '已發布', 'draft' => '草稿', 'hidden' => '隱藏'];
    $statusClass = ['published' => 'bg-good/12 text-good', 'draft' => 'bg-warn/15 text-warn', 'hidden' => 'bg-paper-2 text-ink-2'];
    $chip = [
        'published' => ['class' => 'bg-ink text-paper border-transparent', 'aria' => '已發布'],
        'draft' => ['class' => 'bg-transparent text-ink border-ink border-[1.5px]', 'aria' => '草稿'],
        'hidden' => ['class' => 'bg-paper-2 text-ink-3 border-line-2', 'aria' => '隱藏'],
        'stale' => ['class' => 'bg-warn/20 text-warn border-warn', 'aria' => '比原文舊，需要更新'],
    ];
@endphp
<div class="relative overflow-x-auto bg-card border border-line rounded-[var(--radius-card)]">
    <table class="w-full min-w-[880px] border-collapse text-sm">
        <thead>
            <tr class="text-left text-xs font-bold tracking-[0.04em] text-ink-3">
                <th class="px-4 py-3 border-b border-line">標題</th>
                <th class="px-3 py-3 border-b border-line">狀態</th>
                @unless($trash)
                    <th class="px-3 py-3 border-b border-line">翻譯 <span class="font-medium">中 EN 日 VI ID</span></th>
                @else
                    <th class="px-3 py-3 border-b border-line">語言</th>
                @endunless
                <th class="px-3 py-3 border-b border-line">分類</th>
                <th class="px-3 py-3 border-b border-line text-right">瀏覽</th>
                <th class="px-3 py-3 border-b border-line">修改</th>
                <th class="w-24 border-b border-line"><span class="sr-only">操作</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $post)
                <tr data-post-row="{{ $post->id }}" class="hover:bg-paper-2/60">
                    <td class="px-4 py-3 border-b border-line/70 max-w-[420px]">
                        @if($trash)
                            <span class="block font-semibold text-[15px] truncate text-ink-2">{{ $post->title ?: '（未命名）' }}</span>
                        @else
                            <a href="{{ route('admin.posts.edit', $post) }}" class="block font-semibold text-[15px] truncate text-ink hover:text-accent">
                                @if($post->is_featured)<span data-featured aria-label="精選" title="精選" class="text-accent">★ </span>@endif{{ $post->title ?: '（未命名）' }}
                            </a>
                        @endif
                        <span class="font-mono text-xs text-ink-3">/{{ $post->slug }}</span>
                    </td>
                    <td class="px-3 py-3 border-b border-line/70">
                        @if($trash)
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[13px] font-semibold bg-danger/10 text-danger">垃圾桶</span>
                        @else
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[13px] font-semibold {{ $statusClass[$post->status] ?? '' }}">{{ $statusLabel[$post->status] ?? $post->status }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 border-b border-line/70">
                        @if($trash)
                            <span class="font-mono text-xs uppercase text-ink-3">{{ $post->locale }}</span>
                        @else
                            <span class="flex gap-1">
                                @foreach($languageStates[$post->post_group_id] ?? [] as $locale => $lang)
                                    @if($lang['state'] === 'missing')
                                        <form method="POST" action="{{ route('admin.posts.create-translation', $post) }}" class="contents">
                                            @csrf
                                            <input type="hidden" name="locale" value="{{ $locale }}">
                                            <button type="submit" data-lang="{{ $locale }}" data-state="missing"
                                                aria-label="{{ $langNames[$locale] }}：尚未翻譯，點一下建立" title="{{ $langNames[$locale] }}：尚未翻譯，點一下建立"
                                                class="min-w-[30px] h-[26px] px-1 rounded-[5px] border border-dashed border-line-2 text-[11px] font-bold text-ink-3 hover:border-accent hover:text-accent">+</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.posts.edit', $lang['post']) }}" data-lang="{{ $locale }}" data-state="{{ $lang['state'] }}"
                                            aria-label="{{ $langNames[$locale] }}：{{ $chip[$lang['state']]['aria'] }}" title="{{ $langNames[$locale] }}：{{ $chip[$lang['state']]['aria'] }}"
                                            class="inline-flex items-center justify-center min-w-[30px] h-[26px] px-1 rounded-[5px] border text-[11px] font-bold {{ $chip[$lang['state']]['class'] }}">{{ $badges[$locale] }}</a>
                                    @endif
                                @endforeach
                            </span>
                        @endif
                    </td>
                    <td class="px-3 py-3 border-b border-line/70 text-ink-2">{{ $post->categories->map(fn ($c) => $c->name('zh'))->join('、') ?: '—' }}</td>
                    <td class="px-3 py-3 border-b border-line/70 text-right font-mono text-[13px] text-ink-2">{{ number_format($post->group_views ?? $post->views_count) }}</td>
                    <td class="px-3 py-3 border-b border-line/70 text-ink-3 whitespace-nowrap">
                        @php $updated = $trash ? $post->deleted_at : $post->updated_at; @endphp
                        <time datetime="{{ $updated?->toIso8601String() }}" title="{{ $updated?->format('Y/m/d H:i') }}">{{ $updated?->isToday() ? $updated->locale('zh_TW')->diffForHumans() : $updated?->format($updated?->isCurrentYear() ? 'm/d' : 'Y/m/d') }}</time>
                    </td>
                    <td class="px-3 py-3 border-b border-line/70 text-right whitespace-nowrap">
                        @if($trash)
                            <form method="POST" action="{{ route('admin.posts.restore', $post->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center min-h-9 px-2 text-sm text-accent hover:text-accent-ink">還原</button>
                            </form>
                        @else
                            <details class="relative inline-block text-left">
                                <summary aria-label="更多操作" class="list-none inline-flex items-center justify-center size-9 rounded-md text-lg text-ink-3 hover:bg-paper-2 cursor-pointer">⋯</summary>
                                {{-- Last rows open upwards so the menu isn't clipped by the scrolling table. --}}
                                <div class="absolute z-20 {{ $loop->count <= 2 ? 'right-full top-0 mr-1' : ($loop->remaining < 2 ? 'right-0 bottom-full mb-1' : 'right-0 mt-1') }} w-36 py-1 bg-card border border-line rounded-lg shadow-lg text-left">
                                    <a href="{{ route('admin.posts.edit', $post) }}" class="block px-3 py-2 text-sm text-ink hover:bg-paper-2">編輯</a>
                                    @if($post->status === 'published')
                                        <a href="{{ route('public.posts.show', [$post->locale, $post->slug]) }}" target="_blank" rel="noopener" class="block px-3 py-2 text-sm text-ink hover:bg-paper-2">查看前台 ↗</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('移到垃圾桶？之後可以在垃圾桶還原。')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full text-left px-3 py-2 text-sm text-danger hover:bg-paper-2">移到垃圾桶</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-ink-3">沒有符合條件的文章</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="flex flex-wrap items-center gap-[18px] mt-3.5 text-[13px] text-ink-2">
    @unless($trash)
        <span class="flex items-center gap-1.5"><span class="w-[18px] h-4 rounded bg-ink"></span>已發布</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-3.5 rounded border-[1.5px] border-ink"></span>草稿</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-3.5 rounded bg-warn/20 border border-warn"></span>比原文舊</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-3.5 rounded border border-dashed border-line-2"></span>尚未翻譯（點一下建立）</span>
    @endunless
    <span class="flex-1"></span>
    @if($posts->total())
        <span>第 {{ $posts->firstItem() }}–{{ $posts->lastItem() }} 筆，共 {{ $posts->total() }} 筆</span>
    @endif
</div>

<div class="mt-6">{{ $posts->links() }}</div>
