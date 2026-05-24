@extends('layouts.admin')

@section('title', $mode === 'create' ? '新增文章' : '編輯文章')

@section('content')
@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('admin.posts.store') : route('admin.posts.update', $post);
    $method = $isCreate ? 'POST' : 'PUT';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data" id="post-form">
    @csrf
    @if(!$isCreate)@method('PUT')@endif

    <header class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-ink-3 font-mono mb-1">
                <a href="{{ route('admin.posts.index') }}" class="hover:text-accent">Posts</a>
                / {{ $isCreate ? '新增' : '編輯' }}
            </nav>
            <h1 class="font-serif text-2xl font-semibold">{{ $isCreate ? '新增文章' : '編輯文章' }}</h1>
        </div>

        <div class="flex items-center gap-3">
            @if(!$isCreate)
                <a href="{{ route('public.posts.show', [$post->locale, $post->slug]) }}" target="_blank" class="text-sm text-accent hover:text-accent-ink font-mono">預覽 ↗</a>
            @endif
            <select name="status" class="bg-card border border-line rounded px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                @foreach(['draft' => '草稿', 'published' => '發布', 'hidden' => '隱藏'] as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $post->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">儲存</button>
        </div>
    </header>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-md p-4 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-[1fr_320px] gap-6">
        {{-- Main editor --}}
        <div class="space-y-4">
            <div>
                <input type="text" name="title" value="{{ old('title', $post->title) }}" placeholder="文章標題"
                    class="w-full text-2xl font-serif font-semibold bg-transparent border-0 border-b-2 border-line focus:border-accent focus:outline-none py-2"
                    required>
            </div>
            <div>
                <textarea name="excerpt" rows="2" placeholder="摘要（顯示在卡片預覽）"
                    class="w-full bg-card border border-line rounded-md p-3 text-sm focus:border-accent focus:outline-none">{{ old('excerpt', $post->excerpt) }}</textarea>
            </div>
            <div>
                <label class="block text-xs text-ink-3 mb-1 font-mono uppercase tracking-wide">內文 (Markdown)</label>
                <textarea name="body" rows="24"
                    class="w-full bg-card border border-line rounded-md p-4 font-mono text-sm focus:border-accent focus:outline-none leading-relaxed">{{ old('body', $post->body) }}</textarea>
            </div>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-5">
            {{-- Slug + locale + featured --}}
            <div class="bg-card border border-line rounded-md p-4 space-y-4">
                @if($isCreate)
                    <div>
                        <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
                        <select name="locale" class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
                            @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                                <option value="{{ $loc }}" {{ old('locale', $post->locale) === $loc ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
                        <div class="text-sm font-mono uppercase">{{ $post->locale }}</div>
                    </div>
                @endif

                <div>
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="auto-generated"
                        class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>

                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $post->is_featured) ? 'checked' : '' }}>
                    <span>顯示在首頁精選</span>
                </label>

                <div x-data="coverUpload({ initial: @js($post->cover_image_path) })">
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">封面圖片</label>

                    {{-- Preview --}}
                    <template x-if="path">
                        <div class="relative mb-2 group">
                            <img :src="'/storage/' + path" class="rounded max-h-40 w-full object-cover border border-line">
                            <button type="button" @click="clear()" class="absolute top-1 right-1 bg-paper/90 border border-line rounded px-2 py-0.5 text-xs hover:text-danger">移除</button>
                        </div>
                    </template>

                    {{-- Upload zone --}}
                    <label class="block cursor-pointer border-2 border-dashed border-line rounded-md p-4 text-center text-xs text-ink-3 hover:border-accent hover:text-accent transition-colors"
                        :class="uploading ? 'animate-pulse' : ''">
                        <span x-show="!uploading">
                            <template x-if="path">
                                <span>點此重新上傳</span>
                            </template>
                            <template x-if="!path">
                                <span>點此選擇圖片（PNG/JPG/WebP，≤ 10 MB）</span>
                            </template>
                        </span>
                        <span x-show="uploading" x-cloak>上傳中…</span>
                        <input type="file" class="hidden" accept="image/*" @change="upload($event.target.files[0])">
                    </label>

                    {{-- Hidden form field that actually submits --}}
                    <input type="hidden" name="cover_image_path" x-model="path">
                    <p x-show="error" x-cloak class="mt-2 text-xs text-danger" x-text="error"></p>
                </div>
            </div>

            {{-- Tags --}}
            <div class="bg-card border border-line rounded-md p-4">
                <h3 class="text-xs text-ink-3 font-mono uppercase mb-3">標籤</h3>
                <div class="space-y-1.5 max-h-48 overflow-y-auto">
                    @foreach($tags as $tag)
                        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-paper-2 px-2 py-1 rounded">
                            <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                                {{ $isCreate ? '' : ($post->tags->contains($tag->id) ? 'checked' : '') }}>
                            <span>{{ $tag->name(app()->getLocale()) ?: '(unnamed)' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Categories --}}
            <div class="bg-card border border-line rounded-md p-4">
                <h3 class="text-xs text-ink-3 font-mono uppercase mb-3">分類 / 系列</h3>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @foreach($categories as $cat)
                        @php
                            $checked = !$isCreate && $post->categories->contains($cat->id);
                            $order = $checked ? $post->categories->firstWhere('id', $cat->id)?->pivot?->order_in_category : null;
                        @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <label class="flex items-center gap-2 cursor-pointer flex-1">
                                <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" {{ $checked ? 'checked' : '' }}>
                                <span>{{ $cat->name(app()->getLocale()) ?: '(unnamed)' }}</span>
                            </label>
                            <input type="number" name="categories_order[{{ $cat->id }}]" value="{{ $order }}"
                                placeholder="順序"
                                class="w-16 bg-paper border border-line rounded px-1 py-0.5 text-xs font-mono">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Translations (edit mode only).
                 NOTE: HTML disallows nested forms. The buttons below use the `form="..."`
                 attribute to associate with hidden forms rendered AFTER the main #post-form. --}}
            @if(!$isCreate)
                <div class="bg-card border border-line rounded-md p-4">
                    <h3 class="text-xs text-ink-3 font-mono uppercase mb-3">翻譯版本</h3>
                    <div class="space-y-1 text-sm">
                        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                            @php $t = $translations[$loc] ?? null; @endphp
                            <div class="flex items-center justify-between">
                                <span class="font-mono uppercase text-xs text-ink-3 w-8">{{ $loc }}</span>
                                @if($t)
                                    <a href="{{ route('admin.posts.edit', $t) }}" class="text-accent hover:text-accent-ink flex-1 truncate">
                                        {{ $t->title ?: '(no title)' }}
                                    </a>
                                @else
                                    <button type="submit" form="translate-post-{{ $loc }}"
                                        class="text-xs text-ink-3 hover:text-accent">
                                        + 新增翻譯
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Metadata --}}
            @if(!$isCreate)
                <div class="text-xs text-ink-3 font-mono space-y-1 px-1">
                    <div>建立：{{ $post->created_at?->format('Y/m/d H:i') }}</div>
                    <div>更新：{{ $post->updated_at?->format('Y/m/d H:i') }}</div>
                    <div>觀看：{{ $post->views_count }}</div>
                </div>
            @endif
        </aside>
    </div>
</form>

{{-- Hidden "create translation" forms (referenced via form="..." attribute from sidebar). --}}
@if(!$isCreate)
    @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
        @if(!isset($translations[$loc]))
            <form id="translate-post-{{ $loc }}" method="POST"
                action="{{ route('admin.posts.create-translation', $post) }}" class="hidden">
                @csrf
                <input type="hidden" name="locale" value="{{ $loc }}">
            </form>
        @endif
    @endforeach
@endif
@endsection
