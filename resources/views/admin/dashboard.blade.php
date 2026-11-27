@extends('layouts.admin')

@section('title', '首頁')

@section('content')
@php
    $locales = ['zh' => '繁中', 'en' => 'EN', 'ja' => '日本語', 'vi' => 'VI', 'id' => 'ID'];
@endphp
<h1 class="m-0 mb-7 font-serif text-[32px] font-medium">{{ $greeting }}，{{ auth()->user()->name }}</h1>

<div class="flex flex-col gap-6 max-w-[960px]">
    {{-- Quick tweet --}}
    <section aria-labelledby="qc-h" class="bg-card border border-line rounded-[var(--radius-card-lg)]">
        <form method="POST" action="{{ route('admin.tweets.store') }}" x-data="tweetMediaUpload({ initial: [] })"
            @keydown.meta.enter.prevent="$refs.publish.click()" @keydown.ctrl.enter.prevent="$refs.publish.click()">
            @csrf
            <input type="hidden" name="redirect_to" value="dashboard">
            <div x-data="{ sensitiveAll: false, tagsOpen: false, picked: 0 }">
                <h2 id="qc-h" class="m-0 px-5 pt-4 font-sans text-sm font-bold tracking-normal">快速碎念</h2>

                <div class="relative" x-data="tweetComposer({ locale: 'zh' })">
                    <label for="qc" class="sr-only">碎念內容</label>
                    <textarea id="qc" name="body" rows="4" maxlength="2000" required x-ref="body"
                        placeholder="在想什麼？支援 Markdown、@ 引用文章、直接貼上圖片"
                        @paste="handlePaste($event)" @paste.capture="if ($event.clipboardData?.files?.length) { $event.preventDefault(); add($event.clipboardData.files) }"
                        @input="dismissYtPrompt(); detectMention()"
                        @keydown="onMentionKeydown($event)"
                        class="block w-full px-5 py-3 border-0 bg-transparent text-[17px] leading-[1.7] text-ink placeholder:text-ink-3 resize-y focus:outline-none">{{ old('body') }}</textarea>
                    @include('admin.partials.youtube-embed-prompt')
                    <div x-show="mentionActive && mentionResults.length" x-cloak
                        class="absolute left-5 right-5 z-20 mt-1 bg-card border border-line rounded-md shadow-lg overflow-hidden max-h-64 overflow-y-auto">
                        <template x-for="(item, i) in mentionResults" :key="item.type + ':' + item.id">
                            <button type="button" @click="pickMention(item)" @mouseenter="mentionIndex = i"
                                :class="i === mentionIndex ? 'bg-paper-2' : ''"
                                class="w-full text-left px-3 py-2 border-b border-line last:border-0 hover:bg-paper-2 flex items-start gap-2">
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-2 text-ink-3 shrink-0" x-text="item.type === 'tweet' ? '碎念' : '文章'"></span>
                                <span class="min-w-0">
                                    <span class="block text-sm truncate" x-text="item.label"></span>
                                    <span class="block text-xs text-ink-3 font-mono truncate" x-text="item.url"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Attached media --}}
                <div x-show="items.length || uploading" x-cloak class="flex flex-wrap gap-2 px-5 pb-3">
                    <template x-for="(item, i) in items" :key="item.path">
                        <div class="relative size-20 rounded-lg overflow-hidden border border-line bg-paper-2">
                            <template x-if="item.type === 'image'"><img :src="url(item)" alt="" class="size-full object-cover"></template>
                            <template x-if="item.type === 'video'"><video :src="url(item)" class="size-full object-cover" muted></video></template>
                            <button type="button" @click="remove(i)" aria-label="移除媒體" class="absolute top-1 right-1 size-6 rounded bg-paper/90 text-xs">✕</button>
                            <input type="hidden" :name="`media[${i}][path]`" :value="item.path">
                            <input type="hidden" :name="`media[${i}][type]`" :value="item.type">
                            <input type="hidden" :name="`media[${i}][alt]`" :value="item.alt">
                            <input type="hidden" :name="`media[${i}][sensitive]`" :value="(sensitiveAll || item.sensitive) ? '1' : '0'">
                        </div>
                    </template>
                    <span x-show="uploading > 0" class="self-center text-xs text-ink-3">上傳中…</span>
                </div>
                <p x-show="error" x-cloak class="px-5 pb-2 text-xs text-danger" x-text="error"></p>

                {{-- Toolbar --}}
                <div class="flex flex-wrap items-center gap-1.5 px-3 py-2.5 border-t border-line">
                    <label class="inline-flex items-center justify-center size-10 rounded-lg text-ink-2 hover:bg-paper-2 cursor-pointer" aria-label="加入圖片或影片" title="加入圖片或影片">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/></svg>
                        <input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" @change="add($event.target.files); $event.target.value = ''">
                    </label>

                    <div class="relative" @click.outside="tagsOpen = false">
                        <button type="button" @click="tagsOpen = !tagsOpen" :aria-expanded="tagsOpen"
                            class="h-9 px-2.5 rounded-lg border border-dashed border-line-2 text-[13px] text-ink-2 hover:text-accent">
                            ＋ 標籤<span x-show="picked > 0" x-cloak x-text="' (' + picked + ')'"></span>
                        </button>
                        <div x-show="tagsOpen" x-cloak class="absolute left-0 bottom-11 z-20 w-64 max-h-64 overflow-y-auto p-2 bg-card border border-line rounded-lg shadow-lg">
                            @forelse($tags as $tag)
                                <label class="flex items-center gap-2 min-h-9 px-2 rounded hover:bg-paper-2 text-sm cursor-pointer">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @change="picked += $event.target.checked ? 1 : -1">
                                    #{{ $tag->name('zh') }}
                                </label>
                            @empty
                                <p class="p-2 text-xs text-ink-3">還沒有標籤</p>
                            @endforelse
                        </div>
                    </div>

                    <button type="button" @click="sensitiveAll = !sensitiveAll" :aria-pressed="sensitiveAll ? 'true' : 'false'"
                        :class="sensitiveAll ? 'bg-danger text-white border-danger' : 'text-ink-2 border-line-2'"
                        class="h-9 px-2.5 rounded-lg border text-[13px]">敏感內容</button>

                    <label class="sr-only" for="qc-locale">語言</label>
                    <select id="qc-locale" name="locale" class="h-9 px-2 rounded-lg border border-line-2 bg-transparent text-[13px] text-ink-2">
                        @foreach($locales as $code => $label)
                            <option value="{{ $code }}" @selected($code === 'zh')>{{ $label }}</option>
                        @endforeach
                    </select>

                    <span class="flex-1"></span>
                    <span class="hidden sm:inline font-mono text-xs text-ink-3">⌘↵ 發布</span>
                    <button type="submit" name="status" value="draft" :disabled="uploading !== 0"
                        class="h-10 px-3.5 rounded-lg border border-line-2 text-sm text-ink hover:border-accent disabled:opacity-50">存草稿</button>
                    <button type="submit" name="status" value="published" x-ref="publish" :disabled="uploading !== 0"
                        class="h-10 px-4.5 rounded-lg bg-ink text-paper text-sm font-semibold hover:bg-accent disabled:opacity-50">發布</button>
                </div>
            </div>
        </form>
        @if($errors->any())
            <ul class="m-0 px-5 pb-4 list-disc list-inside text-sm text-danger">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        @endif
    </section>

    {{-- Continue writing --}}
    <section aria-labelledby="cw-h">
        <div class="flex items-baseline justify-between mb-3">
            <h2 id="cw-h" class="m-0 font-sans text-lg font-bold tracking-normal">繼續寫</h2>
            <a href="{{ route('admin.drafts.index') }}" class="text-[13px] text-accent hover:text-accent-ink">所有草稿 ({{ $draftCount }}) →</a>
        </div>
        @if($drafts->isEmpty())
            <x-ui.card padding="md" class="text-sm text-ink-3">目前沒有草稿。想到什麼，先在上面寫一則碎念吧。</x-ui.card>
        @else
            <div class="grid sm:grid-cols-3 gap-3">
                @foreach($drafts as $d)
                    @include('admin.partials.draft-card', ['d' => $d])
                @endforeach
            </div>
        @endif
    </section>
    {{-- Views, last 30 days (posts only; JYU-543) --}}
    @php
        $max = max(1, collect($views['daily'])->max('count'));
        $change = $views['change'];
    @endphp
    <section aria-labelledby="st-h" class="bg-card border border-line rounded-[var(--radius-card-lg)] p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-3 mb-4">
            <h2 id="st-h" class="m-0 font-sans text-lg font-bold tracking-normal">近 30 天瀏覽</h2>
            <span class="text-[13px] text-ink-3">文章的不重複瀏覽（同一位讀者 30 分鐘內只算一次）</span>
        </div>
        <div class="flex flex-wrap gap-8">
            <div class="grow-[2] basis-[280px] min-w-0">
                <div class="font-serif text-4xl font-medium leading-none">
                    {{ number_format($views['total']) }}
                    @if($change !== null)
                        <span class="font-sans text-sm font-semibold {{ $change >= 0 ? 'text-good' : 'text-danger' }}">{{ $change >= 0 ? '↑' : '↓' }} {{ abs($change) }}%</span>
                    @endif
                </div>
                <div role="img" aria-label="近 30 天每日瀏覽長條圖，最高 {{ number_format($max) }}" class="flex items-end gap-[3px] h-24 mt-4">
                    @foreach($views['daily'] as $i => $d)
                        <span data-bar title="{{ $d['label'] }}：{{ number_format($d['count']) }}"
                            class="flex-1 rounded-t-sm {{ $i >= 23 ? 'bg-accent' : 'bg-accent/35' }}"
                            style="height: {{ $d['count'] ? max(4, round($d['count'] / $max * 100)) : 2 }}%"></span>
                    @endforeach
                </div>
                <div class="flex justify-between mt-1.5 font-mono text-[11px] text-ink-3">
                    <span>{{ $views['daily'][0]['label'] }}</span><span>{{ end($views['daily'])['label'] }}</span>
                </div>
            </div>
            <div class="grow basis-[240px] min-w-0">
                <div class="text-[13px] font-bold text-ink-3 mb-1.5">本週熱門</div>
                @if($views['top']->isEmpty())
                    <p class="m-0 py-2 text-sm text-ink-3">這段期間還沒有瀏覽紀錄。</p>
                @else
                    <ol class="list-none m-0 p-0 text-sm">
                        @foreach($views['top'] as $t)
                            <li class="flex justify-between gap-3 py-[7px] {{ $loop->last ? '' : 'border-b border-line' }}">
                                <a href="{{ route('admin.posts.edit', $t['post']) }}" class="min-w-0 truncate text-ink hover:text-accent">{{ $t['post']->title }}</a>
                                <span class="font-mono text-ink-3">{{ number_format($t['views']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
