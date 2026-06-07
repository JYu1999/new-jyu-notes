@extends('layouts.admin')

@section('title', $mode === 'create' ? '新增 Tweet' : '編輯 Tweet')

@section('content')
@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('admin.tweets.store') : route('admin.tweets.update', $tweet);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" id="tweet-form"
    x-data="tweetMediaUpload({ initial: @js(old('media', $tweet->media ?? [])) })">
    @csrf
    @if(!$isCreate)@method('PUT')@endif

    <header class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <nav class="text-xs text-ink-3 font-mono mb-1">
                <a href="{{ route('admin.tweets.index') }}" class="hover:text-accent">Tweets</a> / {{ $isCreate ? '新增' : '編輯' }}
            </nav>
            <h1 class="font-serif text-2xl font-semibold">{{ $isCreate ? '新增 Tweet' : '編輯 Tweet' }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <select name="status" class="bg-card border border-line rounded px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                @foreach(['draft' => '草稿', 'published' => '發布', 'hidden' => '隱藏'] as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $tweet->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" :disabled="uploading > 0" :class="uploading > 0 ? 'opacity-50 cursor-wait' : ''"
                class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">儲存</button>
        </div>
    </header>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-md p-4 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-[1fr_280px] gap-6">
        {{-- Main composer --}}
        <div class="space-y-4 max-w-2xl">
            <div class="relative" x-data="youtubePastePrompt()">
                <textarea name="body" rows="8" maxlength="2000" placeholder="今天想分享什麼…" x-ref="body"
                    @paste="handlePaste($event)"
                    @input="dismissYtPrompt()"
                    class="w-full bg-card border border-line rounded-md p-4 font-serif text-base focus:border-accent focus:outline-none" required>{{ old('body', $tweet->body) }}</textarea>
                @include('admin.partials.youtube-embed-prompt')
            </div>

            {{-- Media (max 4, Twitter-style) --}}
            <div>
                <label class="block text-xs text-ink-3 mb-2 font-mono uppercase">媒體（最多 4 個）</label>

                <div class="grid grid-cols-2 gap-3">
                    <template x-for="(item, i) in items" :key="item.path">
                        <div class="relative border border-line rounded-md overflow-hidden bg-card">
                            <template x-if="item.type === 'image'">
                                <img :src="url(item)" class="w-full h-32 object-cover">
                            </template>
                            <template x-if="item.type === 'video'">
                                <video :src="url(item)" class="w-full h-32 object-cover" preload="metadata" muted></video>
                            </template>
                            <button type="button" @click="remove(i)"
                                class="absolute top-1 right-1 bg-paper/90 border border-line rounded px-2 py-0.5 text-xs hover:text-danger">✕</button>
                            <input type="text" x-model="item.alt" maxlength="200" placeholder="alt 描述（選填）"
                                class="w-full bg-paper border-t border-line px-2 py-1 text-xs focus:outline-none">
                            <input type="hidden" :name="`media[${i}][path]`" :value="item.path">
                            <input type="hidden" :name="`media[${i}][type]`" :value="item.type">
                            <input type="hidden" :name="`media[${i}][alt]`" :value="item.alt">
                        </div>
                    </template>

                    <template x-if="!full">
                        <label class="cursor-pointer border-2 border-dashed border-line rounded-md h-32 flex items-center justify-center text-xs text-ink-3 hover:border-accent hover:text-accent transition-colors"
                            :class="uploading > 0 ? 'animate-pulse' : ''"
                            @dragover.prevent @drop.prevent="add($event.dataTransfer.files)">
                            <span x-show="uploading === 0">＋ 圖片 / 影片（≤ 10 MB）</span>
                            <span x-show="uploading > 0" x-cloak>上傳中…</span>
                            <input type="file" class="hidden" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"
                                @change="add($event.target.files); $event.target.value = ''">
                        </label>
                    </template>
                </div>

                {{-- 清空媒體時仍送出 media key，後端才會把欄位清掉（見 TweetAdminMediaTest） --}}
                <template x-if="items.length === 0">
                    <input type="hidden" name="media" value="">
                </template>

                <p x-show="error" x-cloak class="mt-2 text-xs text-danger" x-text="error"></p>
            </div>

            <div>
                <label class="block text-xs text-ink-3 mb-2 font-mono uppercase">標籤</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($tags as $tag)
                        <label class="inline-flex items-center gap-1.5 text-sm px-3 py-1 bg-card border border-line rounded-full hover:border-accent cursor-pointer">
                            <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                                {{ !$isCreate && $tweet->tags->contains($tag->id) ? 'checked' : '' }}>
                            <span>#{{ $tag->name(app()->getLocale()) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-5">
            {{-- Locale (create-mode only) --}}
            @if($isCreate)
                <div class="bg-card border border-line rounded-md p-4">
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
                    <select name="locale" class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm">
                        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                            <option value="{{ $loc }}" {{ old('locale', $tweet->locale) === $loc ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="bg-card border border-line rounded-md p-4">
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
                    <div class="text-sm font-mono uppercase">{{ $tweet->locale }}</div>
                </div>
            @endif

            {{-- Translations (same UX as posts edit) --}}
            @if(!$isCreate)
                <div class="bg-card border border-line rounded-md p-4">
                    <h3 class="text-xs text-ink-3 font-mono uppercase mb-3">翻譯版本</h3>
                    <div class="space-y-1 text-sm">
                        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                            @php $t = $translations[$loc] ?? null; @endphp
                            <div class="flex items-center justify-between">
                                <span class="font-mono uppercase text-xs text-ink-3 w-8">{{ $loc }}</span>
                                @if($t)
                                    <a href="{{ route('admin.tweets.edit', $t) }}" class="text-accent hover:text-accent-ink flex-1 truncate ml-2">
                                        {{ \Illuminate\Support\Str::limit($t->body, 30) ?: '(empty)' }}
                                    </a>
                                @else
                                    <button type="submit" form="translate-tweet-{{ $loc }}"
                                        class="text-xs text-ink-3 hover:text-accent">
                                        + 新增翻譯
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Metadata --}}
                <div class="text-xs text-ink-3 font-mono space-y-1 px-1">
                    <div>建立：{{ $tweet->created_at?->format('Y/m/d H:i') }}</div>
                    <div>更新：{{ $tweet->updated_at?->format('Y/m/d H:i') }}</div>
                </div>
            @endif
        </aside>
    </div>
</form>

{{-- Hidden "create translation" forms (referenced via form="..." attribute). --}}
@if(!$isCreate)
    @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
        @if(!isset($translations[$loc]))
            <form id="translate-tweet-{{ $loc }}" method="POST"
                action="{{ route('admin.tweets.create-translation', $tweet) }}" class="hidden">
                @csrf
                <input type="hidden" name="locale" value="{{ $loc }}">
            </form>
        @endif
    @endforeach
@endif
@endsection
