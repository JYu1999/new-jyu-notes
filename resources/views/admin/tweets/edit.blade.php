@extends('layouts.admin')

@section('title', $mode === 'create' ? '新增 Tweet' : '編輯 Tweet')

@section('content')
@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('admin.tweets.store') : route('admin.tweets.update', $tweet);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6 max-w-2xl">
    @csrf
    @if(!$isCreate)@method('PUT')@endif

    <header class="flex items-center justify-between gap-3">
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

    @if($isCreate)
        <div>
            <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">語言</label>
            <select name="locale" class="w-32 bg-card border border-line rounded px-2 py-1.5 text-sm">
                @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                    <option value="{{ $loc }}" {{ old('locale', $tweet->locale) === $loc ? 'selected' : '' }}>{{ strtoupper($loc) }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <textarea name="body" rows="6" maxlength="2000" placeholder="今天想分享什麼…"
            class="w-full bg-card border border-line rounded-md p-4 font-serif text-base focus:border-accent focus:outline-none" required>{{ old('body', $tweet->body) }}</textarea>
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

    @if(!$isCreate)
        <div class="text-xs text-ink-3 font-mono space-y-1">
            <div>建立：{{ $tweet->created_at?->format('Y/m/d H:i') }}</div>
            <div>翻譯版本：
                @foreach($translations as $loc => $t)
                    <a href="{{ route('admin.tweets.edit', $t) }}" class="text-accent uppercase ml-1">{{ $loc }}</a>
                @endforeach
                @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $loc)
                    @if(!$translations->has($loc))
                        <form method="POST" action="{{ route('admin.tweets.create-translation', $tweet) }}" class="inline ml-1">
                            @csrf
                            <input type="hidden" name="locale" value="{{ $loc }}">
                            <button class="text-ink-3 hover:text-accent uppercase">+ {{ $loc }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</form>
@endsection
