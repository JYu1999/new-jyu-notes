@extends('layouts.admin')

@section('title', $mode === 'create' ? '新增頁面' : '編輯頁面')

@section('content')
@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('admin.pages.store') : route('admin.pages.update', $page);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if(!$isCreate)@method('PUT')@endif

    <header class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-ink-3 font-mono mb-1">
                <a href="{{ route('admin.pages.index') }}" class="hover:text-accent">Pages</a> / {{ $isCreate ? '新增' : '編輯' }}
            </nav>
            <h1 class="font-serif text-2xl font-semibold">{{ $isCreate ? '新增頁面' : '編輯頁面' }}</h1>
        </div>
        <div class="flex items-center gap-3">
            @if(!$isCreate)
                <a href="{{ url('/' . $page->locale . '/' . $page->slug) }}" target="_blank" class="text-sm text-accent hover:text-accent-ink font-mono">預覽 ↗</a>
            @endif
            <select name="status" class="bg-card border border-line rounded px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                @foreach(['draft' => '草稿', 'published' => '發布', 'hidden' => '隱藏'] as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $page->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">儲存</button>
        </div>
    </header>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-md p-4 text-sm">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-[1fr_320px] gap-6">
        <div class="space-y-4">
            <div>
                <input type="text" name="title" value="{{ old('title', $page->title) }}" placeholder="頁面標題"
                    class="w-full text-2xl font-serif font-semibold bg-transparent border-0 border-b-2 border-line focus:border-accent focus:outline-none py-2" required>
            </div>
            <div x-data="markdownMediaInsert()">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs text-ink-3 font-mono uppercase">內文 (Markdown)</label>
                    <button type="button" @click="pick()"
                        class="text-xs text-ink-3 hover:text-accent font-mono"
                        x-text="uploading > 0 ? '上傳中…' : '📷 插入媒體'"></button>
                </div>
                <textarea name="body" rows="24" x-ref="body"
                    @dragover.prevent="dragging = true"
                    @dragleave="dragging = false"
                    @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)"
                    @paste="handlePaste($event)"
                    :class="dragging ? 'border-accent' : ''"
                    class="w-full bg-card border border-line rounded-md p-4 font-mono text-sm focus:border-accent focus:outline-none leading-relaxed">{{ old('body', $page->body) }}</textarea>
                <input type="file" class="hidden" x-ref="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"
                    @change="handleFiles($event.target.files); $event.target.value = ''">
                <p x-show="error" x-cloak class="mt-1 text-xs text-danger" x-text="error"></p>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="bg-card border border-line rounded-md p-4 space-y-4">
                @if($isCreate)
                    <div>
                        <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
                        <select name="locale" class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm">
                            @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                                <option value="{{ $loc }}" {{ old('locale', $page->locale) === $loc ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
                        <div class="text-sm font-mono uppercase">{{ $page->locale }}</div>
                    </div>
                @endif

                <div>
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $page->slug) }}" placeholder="auto-generated"
                        class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>

                <div x-data="coverUpload({ initial: @js($page->cover_image_path) })">
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">封面圖片</label>
                    <template x-if="path">
                        <div class="relative mb-2">
                            <img :src="previewUrl" class="rounded max-h-32 w-full object-cover border border-line">
                            <button type="button" @click="clear()" class="absolute top-1 right-1 bg-paper/90 border border-line rounded px-2 py-0.5 text-xs hover:text-danger">移除</button>
                        </div>
                    </template>
                    <label class="block cursor-pointer border-2 border-dashed border-line rounded p-2 text-center text-xs text-ink-3 hover:border-accent hover:text-accent">
                        <span x-show="!uploading">點此{{ '{}' }}<template x-if="path"><span>重新</span></template>上傳</span>
                        <span x-show="uploading" x-cloak>上傳中…</span>
                        <input type="file" class="hidden" accept="image/*" @change="upload($event.target.files[0])">
                    </label>
                    <input type="hidden" name="cover_image_path" x-model="path">
                </div>
            </div>

            @if(!$isCreate)
                <div class="bg-card border border-line rounded-md p-4">
                    <h3 class="text-xs text-ink-3 font-mono uppercase mb-3">翻譯版本</h3>
                    <div class="space-y-1 text-sm">
                        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                            @php $t = $translations[$loc] ?? null; @endphp
                            <div class="flex items-center justify-between">
                                <span class="font-mono uppercase text-xs text-ink-3 w-8">{{ $loc }}</span>
                                @if($t)
                                    <a href="{{ route('admin.pages.edit', $t) }}" class="text-accent hover:text-accent-ink flex-1 truncate ml-2">
                                        {{ $t->title ?: '(no title)' }}
                                    </a>
                                @else
                                    <button type="submit" form="translate-page-{{ $loc }}" class="text-xs text-ink-3 hover:text-accent">+ 新增翻譯</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="text-xs text-ink-3 font-mono space-y-1 px-1">
                    <div>建立：{{ $page->created_at?->format('Y/m/d H:i') }}</div>
                    <div>更新：{{ $page->updated_at?->format('Y/m/d H:i') }}</div>
                </div>
            @endif
        </aside>
    </div>
</form>

@if(!$isCreate)
    @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
        @if(!isset($translations[$loc]))
            <form id="translate-page-{{ $loc }}" method="POST"
                action="{{ route('admin.pages.create-translation', $page) }}" class="hidden">
                @csrf
                <input type="hidden" name="locale" value="{{ $loc }}">
            </form>
        @endif
    @endforeach
@endif
@endsection
