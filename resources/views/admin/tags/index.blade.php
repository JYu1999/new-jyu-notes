@extends('layouts.admin')

@section('title', 'Tags')

@section('content')
<header class="mb-6 flex items-center justify-between gap-3 flex-wrap">
    <div>
        <h1 class="font-serif text-2xl font-semibold">Tags</h1>
        <p class="text-sm text-ink-3 mt-1">總共 {{ $tags->count() }} 個標籤</p>
    </div>
    <button onclick="document.getElementById('new-tag-form').classList.toggle('hidden')"
        class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">+ 新增標籤</button>
</header>

{{-- Create form --}}
<form method="POST" action="{{ route('admin.tags.store') }}" id="new-tag-form" class="hidden mb-6 bg-card border border-line rounded-md p-5 space-y-4">
    @csrf
    <h2 class="font-serif text-lg font-semibold">新增標籤</h2>

    <div>
        <label class="block text-xs text-ink-3 mb-1 font-mono uppercase">顏色（hex, 選填）</label>
        <input type="text" name="color" value="#b2543b" placeholder="#b2543b"
            class="bg-paper border border-line rounded px-3 py-1.5 text-sm font-mono w-32 focus:border-accent focus:outline-none">
    </div>

    <div class="space-y-2">
        @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $i => $loc)
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono uppercase text-ink-3 w-8">{{ $loc }}</span>
                <input type="hidden" name="translations[{{ $i }}][locale]" value="{{ $loc }}">
                <input type="text" name="translations[{{ $i }}][name]" placeholder="名稱 ({{ $loc }})"
                    class="flex-1 bg-paper border border-line rounded px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
                <input type="text" name="translations[{{ $i }}][slug]" placeholder="slug"
                    class="w-32 bg-paper border border-line rounded px-2 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
            </div>
        @endforeach
        <p class="text-xs text-ink-3 mt-2">至少填寫一個語言。空白語言會被忽略。</p>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('new-tag-form').classList.add('hidden')" class="text-sm text-ink-2 px-3 py-1.5">取消</button>
        <button type="submit" class="bg-accent text-white px-4 py-1.5 rounded-md hover:bg-accent-ink text-sm">建立</button>
    </div>
</form>

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-md p-4 text-sm mb-4">
        <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="bg-card border border-line rounded-md overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-paper-2 border-b border-line">
            <tr class="text-left text-xs uppercase tracking-wider text-ink-3 font-mono">
                <th class="px-4 py-3">名稱（zh / en / ja）</th>
                <th class="px-4 py-3 w-20">顏色</th>
                <th class="px-4 py-3 w-16">Posts</th>
                <th class="px-4 py-3 w-16">Tweets</th>
                <th class="px-4 py-3 w-32 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse($tags as $tag)
                <tr x-data="{ editing: false }">
                    <td colspan="5" class="px-0 py-0">
                        <div x-show="!editing" class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-4">
                                <span class="font-medium">{{ $tag->name('zh') ?? '—' }}</span>
                                <span class="text-ink-3 font-mono text-xs">{{ $tag->name('en') ?? '—' }}</span>
                                <span class="text-ink-3 text-xs">{{ $tag->name('ja') ?? '—' }}</span>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0">
                                @if($tag->color)
                                    <span class="w-4 h-4 rounded-full inline-block border border-line" style="background:{{ $tag->color }}"></span>
                                @endif
                                <span class="text-xs font-mono text-ink-3">{{ $tag->posts_count ?? 0 }}p</span>
                                <span class="text-xs font-mono text-ink-3">{{ $tag->tweets_count ?? 0 }}t</span>
                                <button @click="editing = true" class="text-xs text-accent hover:text-accent-ink">編輯</button>
                                <form method="POST" action="{{ route('admin.tags.destroy', $tag) }}" class="inline" onsubmit="return confirm('刪除這個標籤會解除所有關聯。確定？')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-danger hover:underline">刪除</button>
                                </form>
                            </div>
                        </div>
                        {{-- Inline edit --}}
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.tags.update', $tag) }}" class="px-4 py-4 bg-paper-2 space-y-3">
                            @csrf @method('PUT')
                            <input type="text" name="color" value="{{ $tag->color }}" placeholder="#hex"
                                class="bg-card border border-line rounded px-2 py-1 text-sm font-mono w-28">
                            <div class="space-y-2">
                                @foreach(['zh', 'en', 'ja', 'vi', 'id'] as $i => $loc)
                                    @php $tr = $tag->translations->firstWhere('locale', $loc); @endphp
                                    @if($tr || true)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono uppercase text-ink-3 w-8">{{ $loc }}</span>
                                            <input type="hidden" name="translations[{{ $i }}][locale]" value="{{ $loc }}">
                                            <input type="text" name="translations[{{ $i }}][name]" value="{{ $tr?->name }}" placeholder="名稱"
                                                class="flex-1 bg-card border border-line rounded px-2 py-1 text-sm">
                                            <input type="text" name="translations[{{ $i }}][slug]" value="{{ $tr?->slug }}" placeholder="slug"
                                                class="w-32 bg-card border border-line rounded px-2 py-1 text-sm font-mono">
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="editing = false" class="text-xs text-ink-2 px-3 py-1">取消</button>
                                <button type="submit" class="bg-accent text-white px-3 py-1 rounded text-xs">儲存</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-ink-3">沒有標籤</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
