@extends('layouts.editor')

@section('title', '新碎念')

{{--
    Full-screen tweet composer for phones (JYU-551): cancel / publish header, language + tags,
    text with @ mentions, up to 4 photos with upload progress, a sensitive-content switch and
    draft autosave. Also usable on desktop (centred column).
--}}
@section('content')
@php
    $locales = ['zh' => '繁中', 'en' => 'English', 'ja' => '日本語', 'vi' => 'Tiếng Việt', 'id' => 'Indonesia'];
    $tagOptions = $tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name('zh') ?: '(unnamed)'])->values();
@endphp

<form method="POST" action="{{ route('admin.tweets.store') }}" id="compose-form"
    x-data="tweetCompose({ autosaveUrl: @js(route('admin.tweets.autosave')), tags: @js($tagOptions) })"
    @input="changed()" @change="changed()" @media-changed="changed()" @submit="submitting = true"
    class="min-h-[100dvh] flex flex-col max-w-xl mx-auto bg-paper md:border-x md:border-line">
    @csrf
    <input type="hidden" name="redirect_to" value="dashboard">
    <input type="hidden" name="tags_sent" value="1">

    <div x-data="tweetMediaUpload({ initial: [] })" class="flex-1 flex flex-col">
        <header class="sticky top-0 z-20 grid grid-cols-[1fr_auto_1fr] items-center h-14 px-4 border-b border-line bg-paper/95 backdrop-blur-sm">
            <a href="{{ route('admin.dashboard') }}" class="justify-self-start inline-flex items-center min-h-11 text-[15px] text-ink-2 hover:text-accent">取消</a>
            <h1 class="m-0 font-sans text-base font-bold tracking-normal">新碎念</h1>
            <button type="submit" name="status" value="published" data-publish :disabled="uploading !== 0"
                class="justify-self-end h-9 px-4 rounded-full bg-ink text-paper text-sm font-semibold hover:bg-accent disabled:opacity-50">發布</button>
        </header>

        @if($errors->any())
            <ul class="m-0 px-4 py-2 list-disc list-inside bg-danger/10 text-sm text-danger">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        @endif

        {{-- Language + tags --}}
        <div class="flex flex-wrap items-center gap-2 px-4 pt-3">
            <label class="sr-only" for="compose-locale">語言</label>
            <select id="compose-locale" name="locale"
                class="h-9 pl-3 pr-8 rounded-full border border-line-2 bg-transparent text-[13px] font-semibold text-ink">
                @foreach($locales as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', $locale) === $code)>{{ $label }}</option>
                @endforeach
            </select>

            <template x-for="tag in selectedTags" :key="tag.id">
                <span data-tag-chip class="inline-flex items-center gap-1 h-9 pl-3 pr-1 rounded-full bg-paper-2 text-[13px] text-ink-2">
                    <span x-text="'#' + tag.name"></span>
                    <button type="button" @click="toggleTag(tag.id)" :aria-label="'移除' + tag.name" class="inline-flex items-center justify-center size-7 rounded-full hover:bg-line">×</button>
                    <input type="hidden" name="tag_ids[]" :value="tag.id">
                </span>
            </template>
            <button type="button" @click="tagsOpen = true" :aria-expanded="tagsOpen ? 'true' : 'false'"
                class="h-9 px-3 rounded-full border border-dashed border-line-2 text-[13px] text-ink-2 hover:text-accent hover:border-accent">＋ 標籤</button>
        </div>

        {{-- Text --}}
        <div class="relative px-4" x-data="tweetComposer({ locale: @js($locale) })"
            @insert-mention.window="$refs.body.focus(); $refs.body.setRangeText('@', $refs.body.selectionStart, $refs.body.selectionEnd, 'end'); detectMention()">
            <label for="compose-body" class="sr-only">碎念內容</label>
            <textarea id="compose-body" name="body" x-ref="body" maxlength="2000" required rows="6"
                placeholder="在想什麼？支援 Markdown、@ 引用文章或碎念"
                @paste="handlePaste($event)" @paste.capture="if ($event.clipboardData?.files?.length) { $event.preventDefault(); add($event.clipboardData.files) }"
                @input="dismissYtPrompt(); detectMention()" @keydown="onMentionKeydown($event)"
                class="block w-full min-h-[30vh] py-4 border-0 bg-transparent text-[17px] leading-[1.7] text-ink placeholder:text-ink-3 resize-none field-sizing-content focus:outline-none">{{ old('body') }}</textarea>
            @include('admin.partials.youtube-embed-prompt')
            <div x-show="mentionActive && mentionResults.length" x-cloak role="listbox" aria-label="引用建議"
                class="absolute left-4 right-4 z-20 bg-card border border-line rounded-lg shadow-lg overflow-hidden max-h-64 overflow-y-auto">
                <template x-for="(item, i) in mentionResults" :key="item.type + ':' + item.id">
                    <button type="button" role="option" :aria-selected="i === mentionIndex" @click="pickMention(item)" @mouseenter="mentionIndex = i"
                        :class="i === mentionIndex ? 'bg-paper-2' : ''" class="w-full text-left min-h-11 px-3 py-2 border-b border-line last:border-0 flex items-center gap-2">
                        <span class="text-[11px] px-1.5 py-0.5 rounded bg-paper-2 text-ink-3 shrink-0" x-text="item.type === 'tweet' ? '碎念' : '文章'"></span>
                        <span class="text-sm truncate" x-text="item.label"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Photos (max 4) with upload progress --}}
        <div x-show="items.length || pending.length" x-cloak data-photos class="grid grid-cols-2 gap-2 px-4 pb-3">
            <template x-for="(item, i) in items" :key="item.path">
                <div class="relative aspect-[4/3] rounded-[var(--radius-card)] overflow-hidden border border-line bg-paper-2">
                    <template x-if="item.type === 'image'"><img :src="url(item)" alt="" class="size-full object-cover"></template>
                    <template x-if="item.type === 'video'"><video :src="url(item)" class="size-full object-cover" muted></video></template>
                    <button type="button" @click="remove(i); $dispatch('media-changed')" :aria-label="'移除照片 ' + (i + 1)"
                        class="absolute top-1.5 right-1.5 inline-flex items-center justify-center size-8 rounded-full bg-ink/70 text-paper text-base">×</button>
                    <input type="hidden" :name="`media[${i}][path]`" :value="item.path">
                    <input type="hidden" :name="`media[${i}][type]`" :value="item.type">
                    <input type="hidden" :name="`media[${i}][alt]`" :value="item.alt">
                    <input type="hidden" :name="`media[${i}][sensitive]`" :value="sensitive ? '1' : '0'">
                </div>
            </template>
            <template x-for="job in pending" :key="job.id">
                <div data-upload-progress class="flex flex-col items-center justify-center gap-2 aspect-[4/3] rounded-[var(--radius-card)] border border-dashed border-line-2 bg-paper-2 text-xs text-ink-2">
                    <span class="w-2/3 h-1.5 rounded-full bg-line overflow-hidden" role="progressbar" :aria-valuenow="job.progress" aria-valuemin="0" aria-valuemax="100">
                        <span class="block h-full bg-accent transition-[width]" :style="`width: ${job.progress}%`"></span>
                    </span>
                    <span x-text="'上傳中 ' + job.progress + '%'"></span>
                </div>
            </template>
        </div>
        <p x-show="error" x-cloak class="m-0 px-4 pb-2 text-xs text-danger" x-text="error"></p>

        {{-- Sensitive content --}}
        <label class="flex items-center justify-between gap-4 mx-4 mb-3 p-3 rounded-[var(--radius-card)] border border-line cursor-pointer">
            <span class="flex flex-col">
                <span class="text-sm font-semibold">標記為敏感內容</span>
                <span class="text-xs text-ink-3">照片會先模糊，讀者點一下才顯示</span>
            </span>
            <input type="checkbox" x-model="sensitive" class="peer sr-only" data-sensitive>
            <span aria-hidden="true" class="relative shrink-0 w-11 h-6 rounded-full bg-line-2 transition-colors peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-paper after:transition-transform peer-checked:after:translate-x-5"></span>
        </label>

        <span class="flex-1"></span>

        {{-- Bottom toolbar --}}
        <div class="sticky bottom-0 flex items-center gap-1 px-2 py-2 border-t border-line bg-paper/95 backdrop-blur-sm pb-[max(0.5rem,env(safe-area-inset-bottom))]">
            <label class="inline-flex items-center justify-center size-11 rounded-lg text-ink-2 hover:bg-paper-2 cursor-pointer" :class="full && 'opacity-40 pointer-events-none'" aria-label="加入照片或影片">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/></svg>
                <input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" :disabled="full"
                    @change="add($event.target.files); $event.target.value = ''">
            </label>
            <button type="button" @click="$dispatch('insert-mention')" aria-label="引用文章或碎念"
                class="inline-flex items-center justify-center size-11 rounded-lg text-lg text-ink-2 hover:bg-paper-2">@</button>
            <span class="font-mono text-xs text-ink-3" x-text="items.length + pending.length + ' / 4'">0 / 4</span>
            <span class="flex-1"></span>
            <span data-autosave-status class="inline-flex items-center gap-1.5 text-xs text-ink-3" aria-live="polite">
                <span aria-hidden="true" class="size-2 rounded-full" :class="{ 'bg-good': saveState === 'saved', 'bg-warn': saveState === 'dirty' || saveState === 'saving', 'bg-danger': saveState === 'error', 'bg-line-2': saveState === 'idle' }"></span>
                <span x-text="saveLabel">尚未儲存</span>
            </span>
        </div>
    </div>

    {{-- Tag picker (bottom sheet on phones) --}}
    <div x-show="tagsOpen" x-cloak class="fixed inset-0 z-40 flex items-end md:items-center justify-center bg-ink/30" @click.self="tagsOpen = false" @keydown.escape.window="tagsOpen = false">
        <div role="dialog" aria-label="選擇標籤" class="w-full max-w-xl max-h-[70vh] flex flex-col bg-card rounded-t-2xl md:rounded-2xl shadow-xl">
            <div class="flex items-center justify-between px-4 h-12 border-b border-line">
                <span class="text-sm font-bold">標籤</span>
                <button type="button" @click="tagsOpen = false" class="min-h-11 px-2 text-sm text-accent">完成</button>
            </div>
            <div class="flex flex-wrap gap-2 p-4 overflow-y-auto">
                <template x-for="tag in tags" :key="tag.id">
                    <button type="button" @click="toggleTag(tag.id)" :aria-pressed="selected.includes(tag.id) ? 'true' : 'false'"
                        class="h-9 px-3 rounded-full bg-paper-2 text-[13px] text-ink-2 aria-pressed:bg-ink aria-pressed:text-paper" x-text="'#' + tag.name"></button>
                </template>
                <p x-show="!tags.length" class="m-0 text-sm text-ink-3">還沒有標籤</p>
            </div>
        </div>
    </div>
</form>
@endsection
