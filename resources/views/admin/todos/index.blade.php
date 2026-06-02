@extends('layouts.admin')

@section('title', 'Todos')

@section('content')
<header class="mb-6">
    <h1 class="font-serif text-2xl font-semibold">Todos</h1>
    <p class="text-sm text-ink-3 mt-1">完成並勾選「顯示於 Changelog」的項目會出現在公開 changelog。</p>
</header>

@if($errors->any())
    <div class="bg-danger-soft border border-danger rounded-md p-3 mb-6 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    {{-- List --}}
    <div class="space-y-2">
        @forelse($todos as $todo)
            <div class="bg-card border border-line rounded-md p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-sm">
                        <div class="font-medium {{ $todo->status === \App\Models\Todo::STATUS_DONE ? 'line-through text-ink-3' : '' }}">
                            {{ $todo->title }}
                        </div>
                        @if($todo->description)
                            <div class="text-ink-3 text-xs mt-1">{{ $todo->description }}</div>
                        @endif
                        <div class="text-ink-3 text-xs mt-1 flex gap-2 flex-wrap">
                            <span class="font-mono uppercase">{{ $todo->priority }}</span>
                            <span>· {{ $todo->status }}</span>
                            @if($todo->show_in_changelog)<span>· changelog ✓</span>@endif
                            @if($todo->completed_at)<span>· {{ $todo->completed_at->format('Y-m-d') }}</span>@endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.todos.destroy', $todo) }}"
                        onsubmit="return confirm('刪除？')">
                        @csrf @method('DELETE')
                        <button class="text-danger hover:underline text-xs">刪除</button>
                    </form>
                </div>

                {{-- Inline edit form --}}
                <form method="POST" action="{{ route('admin.todos.update', $todo) }}" class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    @csrf @method('PUT')
                    <input type="text" name="title" value="{{ $todo->title }}" required
                        class="col-span-2 bg-paper border border-line rounded px-2 py-1">
                    <select name="priority" class="bg-paper border border-line rounded px-2 py-1">
                        @foreach([\App\Models\Todo::PRIORITY_LOW, \App\Models\Todo::PRIORITY_MEDIUM, \App\Models\Todo::PRIORITY_HIGH] as $p)
                            <option value="{{ $p }}" @selected($todo->priority === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="bg-paper border border-line rounded px-2 py-1">
                        @foreach([\App\Models\Todo::STATUS_OPEN, \App\Models\Todo::STATUS_DONE] as $s)
                            <option value="{{ $s }}" @selected($todo->status === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    <label class="col-span-2 inline-flex items-center gap-2">
                        <input type="checkbox" name="show_in_changelog" value="1" @checked($todo->show_in_changelog)>
                        <span>顯示於 Changelog</span>
                    </label>
                    <button class="col-span-2 bg-paper-2 border border-line rounded px-2 py-1 hover:border-accent">儲存</button>
                </form>
            </div>
        @empty
            <p class="text-ink-3 text-sm py-6">尚無 Todo。</p>
        @endforelse
    </div>

    {{-- Create form --}}
    <aside class="bg-card border border-line rounded-md p-4">
        <h2 class="text-xs text-ink-3 font-mono uppercase tracking-wide mb-3">新增 Todo</h2>
        <form method="POST" action="{{ route('admin.todos.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block text-xs text-ink-3 mb-1">標題（英文，會顯示在 changelog）</label>
                <input type="text" name="title" value="{{ old('title') }}" required
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 focus:border-accent focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-ink-3 mb-1">描述（內部備註，可空）</label>
                <textarea name="description" rows="2"
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 focus:border-accent focus:outline-none">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="block text-xs text-ink-3 mb-1">優先級</label>
                <select name="priority" class="w-full bg-paper border border-line rounded px-2 py-1.5">
                    @foreach([\App\Models\Todo::PRIORITY_LOW, \App\Models\Todo::PRIORITY_MEDIUM, \App\Models\Todo::PRIORITY_HIGH] as $p)
                        <option value="{{ $p }}" @selected(old('priority', \App\Models\Todo::PRIORITY_MEDIUM) === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="status" value="open">
            <label class="inline-flex items-center gap-2 text-xs">
                <input type="checkbox" name="show_in_changelog" value="1" @checked(old('show_in_changelog'))>
                <span>顯示於 Changelog</span>
            </label>
            <button class="w-full bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink font-medium">新增</button>
        </form>
    </aside>
</div>
@endsection
