@extends('layouts.editor')

@section('title', '版本紀錄：'.$label)

{{--
    Version history (JYU-553): versions grouped by day on the left (filter: all / AI / status),
    the selected version's changed paragraphs and field changes on the right, a compare target,
    and "還原成這一版之前".
--}}
@section('content')
@php
    $tz = config('app.display_timezone');
    $query = fn (array $extra) => route('admin.revisions.index', array_filter(['type' => $type, 'id' => $model->id, 'filter' => $filter === 'all' ? null : $filter, ...$extra]));
    $who = fn ($r) => match ($r->actor) { 'ai' => $r->actor_name ?: 'AI', 'system' => $r->actor_name ?: '系統', default => $r->user_id === auth()->id() ? '你' : ($r->actor_name ?: '使用者') };
    $initial = fn ($r) => mb_strtoupper(mb_substr($who($r) === '你' ? (auth()->user()->name ?: 'J') : $who($r), 0, 1));
    $time = fn ($r) => $r->created_at->timezone($tz);
@endphp

<div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-30 flex flex-wrap items-center gap-x-3 gap-y-2 min-h-14 px-4 py-2 border-b border-line bg-paper/95 backdrop-blur-sm">
        <a href="{{ route('admin.'.$type.'.index') }}" class="inline-flex items-center gap-1.5 min-h-9 text-sm text-ink-2 hover:text-accent">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>{{ ['posts' => '文章', 'tweets' => '碎念', 'pages' => '頁面'][$type] }}
        </a>
        <span aria-hidden="true" class="w-px h-5 bg-line-2"></span>
        <span class="min-w-0 truncate text-sm font-semibold">{{ $label }}</span>
        @if($model->trashed())
            <span class="px-2 py-0.5 rounded-full bg-danger/10 text-danger text-xs font-semibold">在垃圾桶</span>
        @endif
        <span class="flex-1"></span>
        <nav aria-label="模式" class="flex gap-0.5 p-0.5 rounded-lg bg-paper-2">
            @unless($model->trashed())
                <a href="{{ $editUrl }}" class="inline-flex items-center h-8 px-3 rounded-md text-sm text-ink-2 hover:text-ink">編輯</a>
            @endunless
            <span aria-current="page" data-revision-count class="inline-flex items-center h-8 px-3 rounded-md bg-card text-sm font-semibold text-ink shadow-sm">版本紀錄 · {{ $total }}</span>
        </nav>
    </header>

    @if(session('success'))
        <div class="px-4 py-2 bg-accent-soft text-accent-ink text-sm">{{ session('success') }}</div>
    @endif

    <div class="flex-1 flex flex-col lg:flex-row min-h-0">
        {{-- Version list --}}
        <aside aria-label="版本列表" class="lg:w-[320px] shrink-0 border-b lg:border-b-0 lg:border-r border-line bg-card lg:overflow-y-auto lg:max-h-[calc(100vh-3.5rem)] lg:sticky lg:top-14">
            <div class="flex gap-1 p-3 border-b border-line" role="group" aria-label="篩選">
                @foreach(['all' => '全部', 'ai' => '只看 AI', 'status' => '只看狀態變更'] as $key => $text)
                    <a href="{{ route('admin.revisions.index', array_filter(['type' => $type, 'id' => $model->id, 'filter' => $key === 'all' ? null : $key])) }}"
                        aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        class="inline-flex items-center h-8 px-3 rounded-full text-[13px] text-ink-2 hover:text-ink aria-pressed:bg-ink aria-pressed:text-paper">{{ $text }}</a>
                @endforeach
            </div>

            <div class="p-2 max-h-[45vh] lg:max-h-none overflow-y-auto">
                @forelse($groups as $day => $items)
                    <div class="px-2 pt-3 pb-1 font-mono text-[11px] text-ink-3">{{ $day }}</div>
                    @foreach($items as $r)
                        @php $on = $selected && $r->id === $selected->id; @endphp
                        <a href="{{ $query(['rev' => $r->id]) }}" data-revision="{{ $r->id }}" aria-current="{{ $on ? 'true' : 'false' }}"
                            class="flex items-center gap-2.5 px-2 py-2.5 rounded-[10px] text-ink hover:bg-paper-2 {{ $on ? 'bg-paper-2 shadow-[inset_3px_0_0_var(--color-accent)]' : '' }}">
                            <span aria-hidden="true" class="flex-none inline-flex items-center justify-center size-7 rounded-lg text-xs font-bold {{ $r->isAi() ? 'bg-ink text-paper' : 'bg-paper-2 text-ink' }}">{{ $initial($r) }}</span>
                            <span class="flex-1 min-w-0 flex flex-col">
                                <span class="text-sm truncate">{{ $r->summary }}</span>
                                <span class="text-xs text-ink-3">{{ $who($r) }} · {{ $time($r)->format('H:i') }}</span>
                            </span>
                            <span class="font-mono text-xs text-ink-3 whitespace-nowrap">{{ $stats[$r->id] }}</span>
                        </a>
                    @endforeach
                @empty
                    <p class="m-0 p-4 text-sm text-ink-3">{{ $total ? '沒有符合的版本' : '還沒有版本紀錄。下次儲存後就會開始記錄。' }}</p>
                @endforelse
            </div>
        </aside>

        {{-- Selected version --}}
        <main class="flex-1 min-w-0 px-5 md:px-10 py-8" x-data="{ full: false }">
            @if($selected)
                <div class="max-w-[760px] mx-auto">
                    <div class="flex flex-wrap items-start gap-3 mb-5">
                        <div class="flex-1 min-w-[240px]">
                            <div class="text-xs text-ink-3">{{ $who($selected) }} · {{ $time($selected)->format('Y/m/d H:i') }}</div>
                            <h1 class="m-0 mt-1 font-serif text-[26px] font-medium">{{ $selected->summary }}</h1>
                            @if($selected->isAi())
                                <div data-ai-note class="mt-2 text-[13px] text-ink-2">透過 API／AI 連線 · {{ $selected->actor_name }}</div>
                            @endif
                        </div>

                        @if($others->isNotEmpty())
                            <form method="GET" action="{{ route('admin.revisions.index', ['type' => $type, 'id' => $model->id]) }}" class="flex items-center gap-2">
                                <input type="hidden" name="rev" value="{{ $selected->id }}">
                                @if($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                                <label class="text-[13px] text-ink-2" for="compare">比較對象</label>
                                <select id="compare" name="compare" onchange="this.form.requestSubmit()"
                                    class="h-9 max-w-[220px] px-2 rounded-lg border border-line-2 bg-transparent text-[13px]">
                                    @if($previous)
                                        <option value="" @selected($compare?->id === $previous->id)>前一版</option>
                                    @endif
                                    @foreach($others as $o)
                                        <option value="{{ $o->id }}" @selected($compare && $compare->id === $o->id && $o->id !== $previous?->id)>{{ $time($o)->format('m/d H:i') }} · {{ \Illuminate\Support\Str::limit($o->summary, 16) }}</option>
                                    @endforeach
                                </select>
                                <noscript><button type="submit" class="h-9 px-3 rounded-lg border border-line-2 text-[13px]">比較</button></noscript>
                            </form>
                        @endif

                        @if($previous)
                            <form method="POST" action="{{ route('admin.revisions.restore', ['type' => $type, 'id' => $model->id, 'revision' => $selected->id]) }}"
                                onsubmit="return confirm('把內容還原成這一版之前的樣子？狀態不會改變，還原本身也會記成一個新版本。')">
                                @csrf
                                <button type="submit" data-restore class="h-9 px-3.5 rounded-lg border border-line-2 text-[13px] font-semibold text-ink hover:border-accent hover:text-accent">還原成這一版之前</button>
                            </form>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-4 text-xs text-ink-2">
                        <span class="inline-flex items-center gap-1"><span aria-hidden="true" class="inline-flex items-center justify-center size-4 rounded bg-good/15 text-good font-bold">+</span>新增</span>
                        <span class="inline-flex items-center gap-1"><span aria-hidden="true" class="inline-flex items-center justify-center size-4 rounded bg-danger/10 text-danger font-bold">−</span><s>刪除</s></span>
                        <span data-fields class="text-ink-3">
                            @if(! $compare)
                                最早的版本，沒有可比較的前一版
                            @elseif(empty($fields['changed']))
                                欄位：{{ implode('、', $fields['unchanged']) }} 皆未變更
                            @else
                                欄位：
                                @foreach($fields['changed'] as $f)
                                    <span class="text-ink">{{ $f['label'] }}</span> {{ $f['from'] }} → {{ $f['to'] }}@if(! $loop->last)；@endif
                                @endforeach
                                @if($fields['unchanged'])（{{ implode('、', $fields['unchanged']) }} 未變更）@endif
                            @endif
                        </span>
                    </div>

                    {{-- Changed paragraphs only (or the full text) --}}
                    <div class="revision-diff flex flex-col gap-3 font-serif text-[17px] leading-[1.8]">
                        @forelse($changedParagraphs as $p)
                            <div x-show="!full" data-changed-paragraph>
                                <div class="mb-1 font-sans text-xs text-ink-3">{{ $p['context'] }}</div>
                                <div class="flex gap-3 p-3 rounded-[var(--radius-card)] border border-line bg-card">
                                    <span aria-hidden="true" class="font-mono text-sm {{ ['added' => 'text-good', 'removed' => 'text-danger'][$p['type']] ?? 'text-warn' }}">{{ ['added' => '+', 'removed' => '−'][$p['type']] ?? '~' }}</span>
                                    <p class="m-0 whitespace-pre-wrap">{!! $p['html'] !!}</p>
                                </div>
                            </div>
                        @empty
                            <p x-show="!full" class="m-0 font-sans text-sm text-ink-3">內文沒有變更。</p>
                        @endforelse

                        <div x-show="full" x-cloak class="flex flex-col gap-3">
                            @foreach($paragraphs as $p)
                                <p class="m-0 whitespace-pre-wrap {{ $p['type'] === 'same' ? 'text-ink-2' : '' }}">{!! $p['html'] !!}</p>
                            @endforeach
                        </div>
                    </div>

                    <p class="mt-6 text-xs text-ink-3">
                        <span x-text="full ? '顯示全文。' : '只顯示有變更的段落。'">只顯示有變更的段落。</span>
                        <button type="button" @click="full = !full" class="text-accent hover:text-accent-ink" x-text="full ? '只看變更' : '展開全文'">展開全文</button>
                        · 文章、碎念、頁面都會保留完整版本，移到垃圾桶後仍可從這裡還原。
                    </p>
                </div>
            @else
                <p class="text-sm text-ink-3">還沒有版本紀錄。</p>
            @endif
        </main>
    </div>
</div>
@endsection
