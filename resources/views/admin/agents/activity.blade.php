@extends('layouts.admin')

@section('title', 'AI 最近做了什麼')

@section('content')
<div class="max-w-[760px]">
    <a href="{{ route('admin.agents.index') }}" class="text-sm text-ink-2 hover:text-accent">← AI 連線</a>
    <h1 class="m-0 mt-2 mb-6 font-serif text-[28px] font-medium">AI 最近做了什麼</h1>
    <ol class="m-0 p-0 list-none bg-card border border-line rounded-[var(--radius-card)] px-4">
        @forelse($activities as $a)
            @include('admin.agents._activity', ['a' => $a])
        @empty
            <li class="py-6 text-sm text-ink-3">AI 還沒有做過任何事。</li>
        @endforelse
    </ol>
    <div class="mt-4">{{ $activities->links() }}</div>
</div>
@endsection
