@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<header class="mb-6 flex items-center justify-between gap-3 flex-wrap">
    <div>
        <h1 class="font-serif text-2xl font-semibold">Categories</h1>
        <p class="text-sm text-ink-3 mt-1">總共 {{ $categories->count() }} 個分類</p>
    </div>
    <button onclick="document.getElementById('new-cat-form').classList.toggle('hidden')"
        class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">+ 新增分類</button>
</header>

<form method="POST" action="{{ route('admin.categories.store') }}" id="new-cat-form" class="hidden mb-6 bg-card border border-line rounded-md p-5 space-y-4">
    @csrf
    <h2 class="font-serif text-lg font-semibold">新增分類</h2>

    <div class="grid sm:grid-cols-2 gap-4">
        <div x-data="coverUpload({ initial: '' })">
            <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">封面圖片</label>
            <template x-if="path">
                <div class="relative mb-2">
                    <img :src="'/storage/' + path" class="rounded max-h-32 w-full object-cover border border-line">
                    <button type="button" @click="clear()" class="absolute top-1 right-1 bg-paper/90 border border-line rounded px-2 py-0.5 text-xs hover:text-danger">移除</button>
                </div>
            </template>
            <label class="block cursor-pointer border-2 border-dashed border-line rounded-md p-3 text-center text-xs text-ink-3 hover:border-accent hover:text-accent"
                :class="uploading ? 'animate-pulse' : ''">
                <span x-show="!uploading">
                    <template x-if="path"><span>重新上傳</span></template>
                    <template x-if="!path"><span>點此選擇圖片</span></template>
                </span>
                <span x-show="uploading" x-cloak>上傳中…</span>
                <input type="file" class="hidden" accept="image/*" @change="upload($event.target.files[0])">
            </label>
            <input type="hidden" name="cover_image_path" x-model="path">
            <p x-show="error" x-cloak class="mt-1 text-xs text-danger" x-text="error"></p>
        </div>
        <div>
            <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">排序方式</label>
            <select name="sort_method" class="w-full bg-paper border border-line rounded px-3 py-1.5 text-sm">
                <option value="date_desc">時間新→舊</option>
                <option value="date_asc">時間舊→新</option>
                <option value="manual">手動 (依 order_in_category)</option>
            </select>
        </div>
    </div>

    <div class="space-y-2">
        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $i => $loc)
            <div class="grid grid-cols-[40px_1fr_140px_1fr] gap-2 items-center">
                <span class="text-xs font-mono uppercase text-ink-3">{{ $loc }}</span>
                <input type="hidden" name="translations[{{ $i }}][locale]" value="{{ $loc }}">
                <input type="text" name="translations[{{ $i }}][name]" placeholder="名稱"
                    class="bg-paper border border-line rounded px-2 py-1.5 text-sm">
                <input type="text" name="translations[{{ $i }}][slug]" placeholder="slug"
                    class="bg-paper border border-line rounded px-2 py-1.5 text-sm font-mono">
                <input type="text" name="translations[{{ $i }}][description]" placeholder="描述（選填）"
                    class="bg-paper border border-line rounded px-2 py-1.5 text-sm">
            </div>
        @endforeach
    </div>

    <div class="flex justify-end gap-3">
        <button type="button" onclick="document.getElementById('new-cat-form').classList.add('hidden')" class="text-sm text-ink-2 px-3 py-1.5">取消</button>
        <button type="submit" class="bg-accent text-white px-4 py-1.5 rounded-md hover:bg-accent-ink text-sm">建立</button>
    </div>
</form>

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-md p-4 text-sm mb-4">
        <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid sm:grid-cols-2 gap-4">
    @forelse($categories as $cat)
        <div x-data="{ editing: false }" class="bg-card border border-line rounded-md p-5">
            <div x-show="!editing">
                @if($cat->cover_image_path)
                    <img src="{{ asset('storage/' . $cat->cover_image_path) }}" class="w-full h-32 object-cover rounded mb-3">
                @endif
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="text-xs text-ink-3 font-mono uppercase mb-1">{{ $cat->sort_method }}</div>
                        <h3 class="font-serif text-lg font-semibold">{{ $cat->name('zh') ?? '—' }}</h3>
                        <p class="text-sm text-ink-2 mt-0.5">{{ $cat->name('en') ?? '' }} · {{ $cat->name('ja') ?? '' }}</p>
                    </div>
                    <span class="text-xs font-mono text-ink-3">{{ $cat->posts_count ?? 0 }} posts</span>
                </div>
                @if($cat->description('zh'))
                    <p class="text-sm text-ink-2 mb-3 line-clamp-2">{{ $cat->description('zh') }}</p>
                @endif
                <div class="flex justify-end gap-3 text-xs">
                    <button @click="editing = true" class="text-accent hover:text-accent-ink">編輯</button>
                    <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" class="inline" onsubmit="return confirm('刪除分類會解除所有 post 關聯。確定？')">
                        @csrf @method('DELETE')
                        <button class="text-danger hover:underline">刪除</button>
                    </form>
                </div>
            </div>

            <form x-show="editing" x-cloak method="POST" action="{{ route('admin.categories.update', $cat) }}" class="space-y-3">
                @csrf @method('PUT')
                <div x-data="coverUpload({ initial: @js($cat->cover_image_path) })">
                    <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">封面圖片</label>
                    <template x-if="path">
                        <div class="relative mb-2">
                            <img :src="'/storage/' + path" class="rounded max-h-28 w-full object-cover border border-line">
                            <button type="button" @click="clear()" class="absolute top-1 right-1 bg-paper/90 border border-line rounded px-2 py-0.5 text-xs hover:text-danger">移除</button>
                        </div>
                    </template>
                    <label class="block cursor-pointer border-2 border-dashed border-line rounded p-2 text-center text-xs text-ink-3 hover:border-accent hover:text-accent"
                        :class="uploading ? 'animate-pulse' : ''">
                        <span x-show="!uploading">
                            <template x-if="path"><span>重新上傳</span></template>
                            <template x-if="!path"><span>點此選擇圖片</span></template>
                        </span>
                        <span x-show="uploading" x-cloak>上傳中…</span>
                        <input type="file" class="hidden" accept="image/*" @change="upload($event.target.files[0])">
                    </label>
                    <input type="hidden" name="cover_image_path" x-model="path">
                </div>
                <select name="sort_method" class="w-full bg-paper border border-line rounded px-2 py-1 text-sm">
                    @foreach(['date_desc' => '時間新→舊', 'date_asc' => '時間舊→新', 'manual' => '手動'] as $val => $label)
                        <option value="{{ $val }}" {{ $cat->sort_method === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $i => $loc)
                    @php $tr = $cat->translations->firstWhere('locale', $loc); @endphp
                    <div class="grid grid-cols-[30px_1fr_1fr] gap-1.5">
                        <span class="text-xs font-mono uppercase text-ink-3 self-center">{{ $loc }}</span>
                        <input type="hidden" name="translations[{{ $i }}][locale]" value="{{ $loc }}">
                        <input type="text" name="translations[{{ $i }}][name]" value="{{ $tr?->name }}" placeholder="名稱"
                            class="bg-paper border border-line rounded px-2 py-1 text-xs">
                        <input type="text" name="translations[{{ $i }}][slug]" value="{{ $tr?->slug }}" placeholder="slug"
                            class="bg-paper border border-line rounded px-2 py-1 text-xs font-mono">
                    </div>
                @endforeach
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editing = false" class="text-xs text-ink-2 px-2">取消</button>
                    <button type="submit" class="bg-accent text-white px-3 py-1 rounded text-xs">儲存</button>
                </div>
            </form>
        </div>
    @empty
        <p class="text-ink-3 text-center col-span-2 py-8">沒有分類</p>
    @endforelse
</div>
@endsection
