@extends('layouts.admin')

@section('title', '所有草稿')

@section('content')
<header class="mb-6">
    <nav class="text-xs text-ink-3 font-mono mb-1"><a href="{{ route('admin.dashboard') }}" class="hover:text-accent">首頁</a> / 草稿</nav>
    <h1 class="font-serif text-[28px] font-medium">所有草稿 <span class="font-sans text-base text-ink-3">({{ $drafts->count() }})</span></h1>
</header>

@if($drafts->isEmpty())
    <x-ui.card padding="lg" class="text-center text-ink-3">目前沒有草稿。</x-ui.card>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($drafts as $d)
            @include('admin.partials.draft-card', ['d' => $d])
        @endforeach
    </div>
@endif
@endsection
