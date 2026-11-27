{{--
    Featured order (JYU-547). Drag rows (or use ↑ ↓) to reorder; saved right away.
    Only the first FEATURED_ON_HOME appear on the home page, the rest behind "還有 N 篇精選".
--}}
@php $onHome = \App\Http\Controllers\Public\HomeController::FEATURED_ON_HOME; @endphp
@if($articles->isEmpty())
    <x-ui.card padding="lg" class="max-w-[720px] text-center text-ink-3">還沒有精選文章。在文章編輯器勾選「設為精選」就會出現在這裡。</x-ui.card>
@else
    <div x-data="featuredSort({ url: @js(route('admin.posts.featured-order')), ids: @js($articles->pluck('post_group_id')->map(fn ($id) => (int) $id)->values()), onHome: {{ $onHome }} })"
        class="max-w-[720px]">
        <p class="m-0 mb-3 text-sm text-ink-2">拖曳調整順序，放開後立即儲存。首頁只顯示前 {{ $onHome }} 篇。
            <span x-show="status === 'saving'" x-cloak class="text-ink-3">儲存中…</span>
            <span x-show="status === 'saved'" x-cloak class="text-good">已儲存</span>
            <span x-show="status === 'error'" x-cloak class="text-danger">儲存失敗，請重新整理再試一次</span>
        </p>
        <ol x-ref="list" class="list-none m-0 p-0 bg-card border border-line rounded-[var(--radius-card)] overflow-hidden" data-featured-list>
            @foreach($articles as $i => $post)
                <li data-group="{{ $post->post_group_id }}" draggable="true"
                    @dragstart="dragStart($event)" @dragover.prevent="dragOver($event)" @drop.prevent="drop()" @dragend="dragEnd()"
                    class="group">
                    <div class="flex items-center gap-3 h-[52px] px-4 border-b border-line/70 bg-card cursor-grab active:cursor-grabbing">
                        <span aria-hidden="true" class="text-ink-3 tracking-[-2px] select-none">⋮⋮</span>
                        <span data-position class="w-5 font-mono text-ink-3">{{ $i + 1 }}</span>
                        <a href="{{ route('admin.posts.edit', $post) }}" class="flex-1 min-w-0 truncate font-semibold text-ink hover:text-accent">{{ $post->title }}</a>
                        <span class="flex gap-1">
                            <button type="button" @click="move($el.closest('li'), -1)" aria-label="上移「{{ $post->title }}」" class="size-8 rounded-md text-ink-3 hover:bg-paper-2 hover:text-ink">↑</button>
                            <button type="button" @click="move($el.closest('li'), 1)" aria-label="下移「{{ $post->title }}」" class="size-8 rounded-md text-ink-3 hover:bg-paper-2 hover:text-ink">↓</button>
                        </span>
                    </div>
                    {{-- Divider after the last post that's on the home page (moves with the list, see featuredSort). --}}
                    <div data-divider class="{{ $i === $onHome - 1 && $articles->count() > $onHome ? 'flex' : 'hidden' }} items-center h-7 px-4 border-t-2 border-dashed border-accent bg-accent-soft text-xs font-semibold text-accent-ink">
                        以下不會出現在首頁，只在「還有 N 篇精選」裡
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
