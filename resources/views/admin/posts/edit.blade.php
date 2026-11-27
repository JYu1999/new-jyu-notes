@extends('layouts.editor')

@section('title', $mode === 'create' ? '新增文章' : '編輯：' . $post->title)

{{--
    Focus-mode post editor (JYU-548).
    - Header: language versions, autosave status, 寫作 / 左右對照 / 預覽, settings panel, 發布
    - Main: cover, title, format toolbar, Markdown body (paste / drop media, @ mentions, YouTube prompt), counts
    - Sidebar: 發布, 網址, 摘要, 分類與標籤, 引用關係, 翻譯版本
--}}
@section('content')
@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('admin.posts.store') : route('admin.posts.update', $post);
    $locale = old('locale', $post->locale ?: 'zh');
    $langNames = ['zh' => '繁中', 'en' => 'English', 'ja' => '日本語', 'vi' => 'Tiếng Việt', 'id' => 'Indonesia'];
    $statusLabel = ['draft' => '草稿', 'published' => '已發布', 'hidden' => '隱藏'];
    $mentionUrl = fn ($m) => $m instanceof \App\Models\Tweet ? route('admin.tweets.edit', $m) : route('admin.posts.edit', $m);
    // Scheduled publishing (JYU-549): times are shown and entered in the site's display timezone.
    $tz = (string) config('app.display_timezone', 'UTC');
    $scheduled = ! $isCreate && $post->isScheduled();
    $scheduledLabel = $scheduled ? $post->scheduled_at->timezone($tz)->format('Y/m/d H:i') : null;
    $canSchedule = ($post->status ?? 'draft') !== 'published';
    $publishWhen = old('publish_when', $scheduled ? 'schedule' : 'now');
    $mentionLabel = fn ($m) => $m instanceof \App\Models\Tweet ? \Illuminate\Support\Str::limit(strip_tags((string) $m->body), 40) : $m->title;
@endphp

<form method="POST" action="{{ $action }}" id="post-form" enctype="multipart/form-data"
    x-data="postEditor({
        postId: @js($post->id),
        status: @js(old('status', $post->status ?? 'draft')),
        locale: @js($locale),
        publishWhen: @js($canSchedule ? $publishWhen : 'now'),
        autosaveUrl: @js(route('admin.posts.autosave')),
        previewUrl: @js(route('admin.posts.preview')),
    })"
    @input="changed()" @change="changed()"
    @keydown.window.meta.s.prevent="save()" @keydown.window.ctrl.s.prevent="save()"
    @keydown.window.meta.shift.p.prevent="togglePreview()" @keydown.window.ctrl.shift.p.prevent="togglePreview()"
    class="min-h-screen flex flex-col">
    @csrf
    @unless($isCreate)@method('PUT')@endunless

    {{-- Header --}}
    <header class="sticky top-0 z-30 flex flex-wrap items-center gap-x-3 gap-y-2 min-h-14 px-4 py-2 border-b border-line bg-paper/95 backdrop-blur-sm">
        <a href="{{ route('admin.posts.index') }}" class="inline-flex items-center gap-1.5 min-h-9 text-sm text-ink-2 hover:text-accent">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>文章
        </a>
        <span aria-hidden="true" class="w-px h-5 bg-line-2"></span>

        @unless($isCreate)
            <nav aria-label="翻譯版本" data-versions class="flex flex-wrap items-center gap-1">
                @foreach($versionStates as $loc => $v)
                    @if($v['post'])
                        @php $current = $v['post']->id === $post->id; @endphp
                        <a href="{{ route('admin.posts.edit', $v['post']) }}" @if($current) aria-current="page" @endif
                            class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-sm {{ $current ? 'bg-paper-2 text-ink font-semibold' : 'text-ink-2 hover:bg-paper-2' }}">
                            {{ $langNames[$loc] }}
                            <span class="text-[11px] font-normal {{ $v['stale'] ? 'text-warn' : 'text-ink-3' }}">{{ $v['original'] ? '原文' : ($statusLabel[$v['post']->status] ?? '') }}{{ $v['stale'] ? ' · 比原文舊' : '' }}</span>
                        </a>
                    @else
                        <button type="submit" form="translate-post-{{ $loc }}" data-create-version="{{ $loc }}"
                            class="inline-flex items-center h-8 px-2 rounded-lg border border-dashed border-line-2 text-xs text-ink-3 hover:border-accent hover:text-accent"
                            aria-label="建立{{ $langNames[$loc] }}版本">＋ {{ $langNames[$loc] }}</button>
                    @endif
                @endforeach
            </nav>
        @else
            <span class="text-sm text-ink-2">新文章</span>
        @endunless

        <span class="flex-1"></span>

        <span data-autosave-status class="inline-flex items-center gap-1.5 text-[13px] text-ink-3" aria-live="polite">
            <span aria-hidden="true" class="size-2 rounded-full" :class="{ 'bg-good': saveState === 'saved', 'bg-warn': saveState === 'dirty' || saveState === 'saving', 'bg-danger': saveState === 'error', 'bg-line-2': saveState === 'idle' }"></span>
            <span x-text="saveLabel">{{ $isCreate ? '尚未儲存' : '已儲存' }}</span>
        </span>

        @if($scheduled)
            <span data-scheduled-chip class="inline-flex items-center h-7 px-2.5 rounded-full bg-accent-soft text-accent-ink text-xs font-semibold">已排程 · {{ $scheduledLabel }}</span>
        @endif

        <div role="group" aria-label="檢視模式" class="flex gap-0.5 p-0.5 rounded-lg bg-paper-2">
            @foreach(['write' => '寫作', 'split' => '左右對照', 'preview' => '預覽'] as $key => $label)
                <button type="button" data-mode="{{ $key }}" @click="setMode('{{ $key }}')" :aria-pressed="mode === '{{ $key }}' ? 'true' : 'false'" aria-pressed="{{ $key === 'write' ? 'true' : 'false' }}"
                    class="h-8 px-3 rounded-md text-sm text-ink-2 aria-pressed:bg-card aria-pressed:text-ink aria-pressed:font-semibold aria-pressed:shadow-sm">{{ $label }}</button>
            @endforeach
        </div>

        <button type="button" @click="panel = !panel" :aria-pressed="panel ? 'true' : 'false'" aria-label="文章設定面板"
            class="inline-flex items-center justify-center size-9 rounded-lg text-ink-2 hover:bg-paper-2 aria-pressed:bg-paper-2">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16"/></svg>
        </button>

        {{-- Publish split button: ▾ offers 排程… and 設為隱藏 (JYU-549). --}}
        <div class="relative flex ml-auto" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape="menu = false">
            <button type="submit" data-publish @click="publishClicked($event)"
                class="h-9 px-4 rounded-l-lg bg-ink text-paper text-sm font-semibold hover:bg-accent"
                x-text="publishLabel">{{ $canSchedule && $publishWhen === 'schedule' ? '排程' : (($post->status ?? 'draft') === 'published' ? '更新' : '發布') }}</button>
            <button type="button" @click="menu = !menu" :aria-expanded="menu ? 'true' : 'false'" aria-expanded="false" aria-haspopup="menu"
                aria-label="其他發布選項：{{ $canSchedule ? '排程、' : '' }}設為隱藏"
                class="h-9 w-9 rounded-r-lg bg-ink text-paper border-l border-paper/25 text-xs hover:bg-accent">▾</button>
            <div x-show="menu" x-cloak role="menu" class="absolute right-0 top-full z-40 mt-1 w-40 py-1 bg-card border border-line rounded-lg shadow-lg">
                @if($canSchedule)
                    <button type="button" role="menuitem" data-schedule-option @click="menu = false; chooseSchedule()"
                        class="w-full text-left px-3 py-2 text-sm text-ink hover:bg-paper-2">排程…</button>
                @endif
                <button type="button" role="menuitem" @click="menu = false; hideAndSave()"
                    class="w-full text-left px-3 py-2 text-sm text-ink hover:bg-paper-2">設為隱藏</button>
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="px-4 py-2 bg-accent-soft text-accent-ink text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="px-4 py-3 bg-danger/10 text-danger text-sm">
            <ul class="m-0 list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif
    @if(! $isCreate && ($versionStates[$post->locale]['stale'] ?? false))
        <p data-stale class="m-0 px-4 py-2 bg-warn/10 text-sm text-warn">比原文舊：原文在這個翻譯最後更新之後修改過，記得同步。</p>
    @endif

    <div class="flex-1 flex min-h-0">
        {{-- Writing area --}}
        <main class="flex-1 min-w-0 flex" :class="mode === 'split' && 'divide-x divide-line'">
            <div x-show="mode !== 'preview'" class="flex-1 min-w-0 flex flex-col">
                <div class="flex-1 w-full max-w-[760px] mx-auto px-5 md:px-10 pt-8 pb-6">
                    {{-- Cover --}}
                    <div x-data="coverUpload({ initial: @js(old('cover_image_path', $post->cover_image_path)) })" class="mb-5">
                        <template x-if="path">
                            <div class="relative group mb-2">
                                <img :src="previewUrl" alt="" class="w-full max-h-64 object-cover rounded-[var(--radius-card)] border border-line">
                                <button type="button" @click="clear()" class="absolute top-2 right-2 h-8 px-2.5 rounded-md bg-paper/90 border border-line text-xs hover:text-danger">移除封面</button>
                            </div>
                        </template>
                        <label class="inline-flex items-center gap-2 min-h-9 text-sm text-ink-3 hover:text-accent cursor-pointer" :class="uploading && 'animate-pulse'"
                            @dragover.prevent @drop.prevent="upload($event.dataTransfer.files[0])">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M21 16l-5-5-8 8"/></svg>
                            <span x-text="uploading ? '上傳中…' : (path ? '更換封面圖' : '加入封面圖（可直接拖曳進來）')">加入封面圖</span>
                            <input type="file" class="sr-only" accept="image/*" @change="upload($event.target.files[0])">
                        </label>
                        <input type="hidden" name="cover_image_path" x-model="path">
                        <p x-show="error" x-cloak class="mt-1 text-xs text-danger" x-text="error"></p>
                    </div>

                    <label for="title" class="sr-only">標題</label>
                    {{-- A one-line textarea so long titles wrap instead of being clipped. --}}
                    <textarea id="title" name="title" rows="1" placeholder="標題" required x-ref="title" @keydown.enter.prevent
                        class="w-full mb-4 bg-transparent border-0 resize-none field-sizing-content font-serif text-[34px] md:text-[40px] leading-tight font-medium text-ink placeholder:text-ink-3/60 focus:outline-none">{{ old('title', $post->title) }}</textarea>

                    <div x-data="markdownMediaInsert({ locale: @js($locale), postId: @js($post->id) })" class="relative">
                        {{-- Format toolbar --}}
                        <div role="toolbar" aria-label="格式" class="sticky top-14 z-10 flex flex-wrap items-center gap-0.5 mb-3 py-1 bg-paper/95 border-b border-line">
                            <button type="button" @click="format('bold', $refs.body)" aria-label="粗體" title="粗體 (⌘B)" class="size-9 rounded-md font-bold text-ink-2 hover:bg-paper-2">B</button>
                            <button type="button" @click="format('italic', $refs.body)" aria-label="斜體" title="斜體 (⌘I)" class="size-9 rounded-md italic font-serif text-ink-2 hover:bg-paper-2">I</button>
                            <button type="button" @click="format('heading', $refs.body)" aria-label="標題" class="h-9 px-2 rounded-md text-sm font-semibold text-ink-2 hover:bg-paper-2">H2</button>
                            <button type="button" @click="format('quote', $refs.body)" aria-label="引用區塊" class="size-9 rounded-md text-ink-2 hover:bg-paper-2">❝</button>
                            <button type="button" @click="format('code', $refs.body)" aria-label="程式碼" class="h-9 px-2 rounded-md font-mono text-sm text-ink-2 hover:bg-paper-2">&lt;/&gt;</button>
                            <span aria-hidden="true" class="w-px h-5 mx-1 bg-line-2"></span>
                            <button type="button" @click="pick()" aria-label="插入圖片或影片" class="size-9 rounded-md text-ink-2 hover:bg-paper-2 inline-flex items-center justify-center">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/></svg>
                            </button>
                            <button type="button" @click="format('mention', $refs.body); detectMention()" aria-label="引用文章或碎念" class="size-9 rounded-md text-ink-2 hover:bg-paper-2">@</button>
                            <span class="ml-auto text-xs text-ink-3" x-text="uploading > 0 ? '上傳中…' : '輸入 / 叫出所有指令'">輸入 / 叫出所有指令</span>
                        </div>

                        <label for="body" class="sr-only">內文（Markdown）</label>
                        <textarea id="body" name="body" x-ref="body" required placeholder="開始寫作…（支援 Markdown，貼上圖片或 YouTube 網址都可以）"
                            @dragover.prevent="dragging = true" @dragleave="dragging = false"
                            @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)"
                            @paste="handlePaste($event)"
                            @input="dismissYtPrompt(); detectMention(); detectSlash()"
                            @click="detectSlash()" @blur="setTimeout(() => closeSlash(), 150)"
                            @keydown="onSlashKeydown($event) || onMentionKeydown($event)"
                            @keydown.meta.b.prevent="format('bold', $refs.body)" @keydown.meta.i.prevent="format('italic', $refs.body)"
                            :class="dragging && 'ring-2 ring-accent'"
                            class="w-full min-h-[55vh] bg-transparent border-0 font-serif text-[18px] leading-[1.85] text-ink placeholder:text-ink-3/60 resize-none focus:outline-none field-sizing-content">{{ old('body', $post->body) }}</textarea>
                        <input type="file" class="hidden" x-ref="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" @change="handleFiles($event.target.files); $event.target.value = ''">
                        <p x-show="error" x-cloak class="mt-1 text-xs text-danger" x-text="error"></p>
                        @include('admin.partials.youtube-embed-prompt')
                        {{-- "/" command menu (JYU-552): ↑↓ to choose, Enter to insert, Esc to close --}}
                        <div x-show="slashActive" x-cloak data-slash-menu role="listbox" aria-label="指令" :style="{ top: slashTop + 'px' }"
                            class="absolute left-0 top-0 z-20 mt-1 w-72 max-w-full bg-card border border-line rounded-[var(--radius-control)] shadow-lg overflow-hidden">
                            <div class="px-3 py-1.5 text-xs text-ink-3 border-b border-line">指令 · ↑↓ 選擇　Enter 插入　Esc 關閉</div>
                            <template x-for="(cmd, i) in slashResults" :key="cmd.id">
                                <button type="button" role="option" :aria-selected="i === slashIndex ? 'true' : 'false'"
                                    @mousedown.prevent="runSlash(cmd)" @mouseenter="slashIndex = i"
                                    :class="i === slashIndex ? 'bg-paper-2' : ''" class="w-full min-h-10 px-3 py-2 flex items-center justify-between gap-3 text-left hover:bg-paper-2">
                                    <span class="text-sm text-ink" x-text="cmd.label"></span>
                                    <span class="font-mono text-xs text-ink-3" x-text="cmd.hint"></span>
                                </button>
                            </template>
                            <p x-show="!slashResults.length" class="m-0 px-3 py-2 text-sm text-ink-3">沒有符合的指令</p>
                        </div>
                        <div x-show="mentionActive && mentionResults.length" x-cloak role="listbox" aria-label="引用建議"
                            class="absolute left-0 right-0 z-20 mt-1 bg-card border border-line rounded-[var(--radius-control)] shadow-lg overflow-hidden max-h-64 overflow-y-auto">
                            <div class="px-3 py-1.5 text-xs text-ink-3 border-b border-line">引用文章或碎念 · 存檔時自動建立反向連結</div>
                            <template x-for="(item, i) in mentionResults" :key="item.type + ':' + item.id">
                                <button type="button" role="option" :aria-selected="i === mentionIndex" @click="pickMention(item)" @mouseenter="mentionIndex = i"
                                    :class="i === mentionIndex ? 'bg-paper-2' : ''" class="w-full text-left px-3 py-2 hover:bg-paper-2 flex items-center gap-2">
                                    <span class="text-[11px] px-1.5 py-0.5 rounded bg-paper-2 text-ink-3 shrink-0" x-text="item.type === 'tweet' ? '碎念' : '文章'"></span>
                                    <span class="text-sm truncate" x-text="item.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Counts + shortcuts --}}
                <div data-editor-stats class="sticky bottom-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-5 md:px-10 py-2 border-t border-line bg-paper/95 text-xs text-ink-3">
                    <span x-text="statsLabel">0 字 · 約 1 分鐘</span>
                    <span x-text="'引用 ' + refCount + ' 篇'">引用 0 篇</span>
                    <span class="flex-1"></span>
                    <span><kbd class="font-mono">⌘S</kbd> 儲存　<kbd class="font-mono">⌘⇧P</kbd> 預覽</span>
                </div>
            </div>

            {{-- Preview: same Markdown pipeline and prose styles as the public post page --}}
            <div x-show="mode !== 'write'" x-cloak class="flex-1 min-w-0 overflow-y-auto bg-paper" data-preview-pane>
                <article class="max-w-[720px] mx-auto px-5 md:px-10 py-10">
                    <h1 class="m-0 mb-6 font-serif text-[34px] md:text-5xl leading-tight font-medium tracking-tight" x-text="title || '（未命名）'"></h1>
                    <div class="prose-blog post-body" x-html="previewHtml"></div>
                    <p x-show="previewLoading" class="text-sm text-ink-3">產生預覽中…</p>
                </article>
            </div>
        </main>

        {{-- Settings --}}
        {{-- Below lg the panel is a slide-over (closed by default); on desktop it sits beside the text. --}}
        <div x-show="panel" x-cloak @click="panel = false" class="fixed inset-0 z-30 bg-ink/30 lg:hidden" aria-hidden="true"></div>
        <aside x-show="panel" x-cloak aria-label="文章設定" @keydown.escape="if (window.innerWidth < 1024) panel = false"
            class="fixed inset-y-0 right-0 z-40 w-[min(320px,88vw)] shadow-xl lg:static lg:z-auto lg:w-[300px] lg:shadow-none shrink-0 border-l border-line bg-card overflow-y-auto">
            <div class="flex flex-col divide-y divide-line text-sm">
                <section class="p-4 flex flex-col gap-3">
                    <h2 class="m-0 font-sans text-xs font-bold tracking-[0.06em] text-ink-3">發布</h2>
                    @if($isCreate)
                        <label class="flex items-center justify-between gap-2">語言
                            <select name="locale" class="h-9 px-2 rounded-md border border-line-2 bg-transparent">
                                @foreach($langNames as $code => $label)
                                    <option value="{{ $code }}" @selected($locale === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="flex items-center justify-between gap-2">狀態
                        <select name="status" x-model="status" x-ref="status" class="h-9 px-2 rounded-md border border-line-2 bg-transparent">
                            @foreach($statusLabel as $val => $label)
                                <option value="{{ $val }}" @selected(old('status', $post->status ?? 'draft') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($canSchedule)
                        <label class="flex items-center justify-between gap-2">發布時間
                            <select name="publish_when" x-model="publishWhen" class="h-9 px-2 rounded-md border border-line-2 bg-transparent">
                                <option value="now" @selected($publishWhen === 'now')>按下發布時</option>
                                <option value="schedule" @selected($publishWhen === 'schedule')>排程…</option>
                            </select>
                        </label>
                        <div x-show="publishWhen === 'schedule'" @if($publishWhen !== 'schedule') x-cloak @endif data-schedule class="flex flex-col gap-1.5">
                            <input type="datetime-local" name="scheduled_at" x-ref="scheduledAt" aria-label="排程發布時間"
                                value="{{ old('scheduled_at', $scheduled ? $post->scheduled_at->timezone($tz)->format('Y-m-d\TH:i') : '') }}"
                                min="{{ now($tz)->format('Y-m-d\TH:i') }}"
                                class="h-9 px-2 rounded-md border border-line-2 bg-transparent font-mono text-xs text-ink">
                            <span class="text-xs text-ink-3">按「排程」儲存，時間到會自動發布（{{ $tz }}）</span>
                        </div>
                        @if($scheduled)
                            <p data-scheduled-notice class="m-0 flex items-center justify-between gap-2 text-xs text-accent-ink">
                                已排程：{{ $scheduledLabel }}
                                <button type="submit" form="unschedule-post" class="text-xs text-danger hover:underline">取消排程</button>
                            </p>
                        @endif
                    @endif
                    <label class="flex items-center justify-between gap-2 cursor-pointer">設為精選
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured)) class="size-[18px] accent-accent">
                    </label>
                    <span class="text-xs text-ink-3">新精選排在最後，可在「文章 › 精選」調整順序</span>
                </section>

                <section class="p-4 flex flex-col gap-2">
                    <h2 class="m-0 font-sans text-xs font-bold tracking-[0.06em] text-ink-3">網址</h2>
                    <label class="flex items-center h-9 rounded-md border border-line-2 overflow-hidden">
                        <span class="pl-2 font-mono text-xs text-ink-3 whitespace-nowrap">/{{ $locale }}/posts/</span>
                        <input type="text" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="自動產生" aria-label="Slug"
                            class="flex-1 min-w-0 h-full px-1 bg-transparent border-0 font-mono text-xs focus:outline-none">
                    </label>
                    <span data-slug-hint class="text-xs text-ink-3">由標題自動產生，發布後修改會保留舊網址轉址</span>
                </section>

                <section class="p-4 flex flex-col gap-2" x-data="{ n: {{ mb_strlen((string) old('excerpt', $post->excerpt)) }} }">
                    <div class="flex items-baseline justify-between">
                        <h2 class="m-0 font-sans text-xs font-bold tracking-[0.06em] text-ink-3">摘要</h2>
                        <span data-excerpt-count class="text-xs" :class="n > 120 ? 'text-warn' : 'text-ink-3'"><span x-text="n">{{ mb_strlen((string) old('excerpt', $post->excerpt)) }}</span> / 120</span>
                    </div>
                    <textarea name="excerpt" rows="3" aria-label="摘要" @input="n = Array.from($event.target.value).length"
                        placeholder="顯示在卡片與搜尋結果"
                        class="w-full p-2 rounded-md border border-line-2 bg-transparent text-sm focus:outline-none focus:border-accent">{{ old('excerpt', $post->excerpt) }}</textarea>
                </section>

                <section class="p-4 flex flex-col gap-3">
                    <h2 class="m-0 font-sans text-xs font-bold tracking-[0.06em] text-ink-3">分類與標籤</h2>
                    <div class="flex flex-col gap-1.5 max-h-40 overflow-y-auto">
                        @foreach($categories as $cat)
                            @php
                                $checked = ! $isCreate && $post->categories->contains($cat->id);
                                $order = $checked ? $post->categories->firstWhere('id', $cat->id)?->pivot?->order_in_category : null;
                            @endphp
                            <div class="flex items-center gap-2">
                                <label class="flex-1 flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked($checked) class="accent-accent">
                                    {{ $cat->name('zh') ?: '(unnamed)' }}
                                </label>
                                <input type="number" name="categories_order[{{ $cat->id }}]" value="{{ $order }}" placeholder="順序" aria-label="{{ $cat->name('zh') }} 中的順序"
                                    class="w-14 h-7 px-1 rounded border border-line-2 bg-transparent font-mono text-xs">
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($tags as $tag)
                            @php $has = ! $isCreate && $post->tags->contains($tag->id); @endphp
                            <label class="cursor-pointer">
                                <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked($has) class="peer sr-only">
                                <span class="inline-flex items-center h-7 px-2.5 rounded-full text-xs bg-paper-2 text-ink-2 peer-checked:bg-ink peer-checked:text-paper peer-focus-visible:ring-2 peer-focus-visible:ring-accent">#{{ $tag->name('zh') ?: '(unnamed)' }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="p-4 flex flex-col gap-2" data-references>
                    <h2 class="m-0 font-sans text-xs font-bold tracking-[0.06em] text-ink-3">引用關係</h2>
                    <span class="text-xs font-semibold text-ink-2">本文引用</span>
                    @forelse($cites as $m)
                        <a href="{{ $mentionUrl($m) }}" class="block truncate text-ink-2 hover:text-accent">→ {{ $mentionLabel($m) }}</a>
                    @empty
                        <span class="text-xs text-ink-3">（無）</span>
                    @endforelse
                    <span class="mt-2 text-xs font-semibold text-ink-2">被引用</span>
                    @forelse($citedBy as $m)
                        <a href="{{ $mentionUrl($m) }}" class="block truncate text-ink-2 hover:text-accent">← {{ $mentionLabel($m) }}</a>
                    @empty
                        <span class="text-xs text-ink-3">（無）</span>
                    @endforelse
                    <span class="text-[11px] text-ink-3">存檔時會依內文的連結自動更新</span>
                </section>

                @unless($isCreate)
                    <section class="p-4 flex flex-col gap-1.5" data-translation-states>
                        <h2 class="m-0 mb-1 font-sans text-xs font-bold tracking-[0.06em] text-ink-3">翻譯版本</h2>
                        @foreach($versionStates as $loc => $v)
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ $langNames[$loc] }}</span>
                                @if($v['post'])
                                    <a href="{{ route('admin.posts.edit', $v['post']) }}" class="text-xs {{ $v['stale'] ? 'text-warn' : 'text-ink-3' }} hover:text-accent">
                                        {{ $v['original'] ? '原文 · ' : '' }}{{ $statusLabel[$v['post']->status] ?? $v['post']->status }}{{ $v['stale'] ? ' · 比原文舊' : '' }}
                                    </a>
                                @else
                                    <button type="submit" form="translate-post-{{ $loc }}" class="text-xs text-accent hover:text-accent-ink">＋ 建立</button>
                                @endif
                            </div>
                        @endforeach
                    </section>

                    <section class="p-4 text-xs text-ink-3 font-mono flex flex-col gap-1">
                        <span>建立：{{ $post->created_at?->format('Y/m/d H:i') }}</span>
                        <span>更新：{{ $post->updated_at?->format('Y/m/d H:i') }}</span>
                        <span>瀏覽：{{ number_format($post->views_count) }}</span>
                        @if($post->status === 'published')
                            <a href="{{ route('public.posts.show', [$post->locale, $post->slug]) }}" target="_blank" rel="noopener" class="font-sans text-accent hover:text-accent-ink">查看前台 ↗</a>
                        @endif
                    </section>
                @endunless
            </div>
        </aside>
    </div>
</form>

{{-- "Create translation" / "cancel schedule" forms live outside the main form (no nested forms). --}}
@if($scheduled)
    <form id="unschedule-post" method="POST" action="{{ route('admin.posts.unschedule', $post) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@unless($isCreate)
    @foreach($versionStates as $loc => $v)
        @unless($v['post'])
            <form id="translate-post-{{ $loc }}" method="POST" action="{{ route('admin.posts.create-translation', $post) }}" class="hidden">
                @csrf
                <input type="hidden" name="locale" value="{{ $loc }}">
            </form>
        @endunless
    @endforeach
@endunless
@endsection
