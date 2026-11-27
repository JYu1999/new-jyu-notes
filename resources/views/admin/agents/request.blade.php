@extends('layouts.admin')

@section('title', '檢查 AI 的要求')

{{-- One AI request waiting for approval (JYU-556): who, what, which content, the proposed change. --}}
@section('content')
<div class="max-w-[820px]">
    <a href="{{ route('admin.agents.index') }}" class="text-sm text-ink-2 hover:text-accent">← AI 連線</a>
    <h1 class="m-0 mt-2 mb-1 font-serif text-[28px] font-medium">{{ $headline }}</h1>
    <p class="m-0 mb-6 text-sm text-ink-3">
        {{ $r->created_at->locale('zh_TW')->diffForHumans() }} · 工具 <span class="font-mono">{{ $r->tool }}</span>
        @if($r->status !== 'pending') · <span data-request-status class="font-semibold text-ink">{{ ['approved' => '已核准', 'rejected' => '已拒絕', 'failed' => '執行失敗'][$r->status] }}</span>@endif
    </p>

    @if($r->note)
        <blockquote class="m-0 mb-6 pl-4 border-l-2 border-accent text-[15px] text-ink-2">「{{ $r->note }}」</blockquote>
    @endif

    @if($subject)
        <div class="flex flex-wrap items-center gap-3 mb-6 p-4 rounded-[var(--radius-card)] bg-card border border-line">
            <div class="flex-1 min-w-0">
                <div class="text-xs text-ink-3">{{ strtoupper($subject->locale) }} · 目前狀態：{{ ['draft' => '草稿', 'published' => '已發布', 'hidden' => '隱藏'][$subject->status] ?? $subject->status }}</div>
                <div class="font-semibold truncate">{{ $subject->title ?? \Illuminate\Support\Str::limit(strip_tags((string) $subject->body), 60) }}</div>
            </div>
            @if($historyUrl)<a href="{{ $historyUrl }}" class="text-[13px] text-accent hover:text-accent-ink">版本紀錄</a>@endif
        </div>
    @endif

    @if($titleChange)
        <p data-title-change class="m-0 mb-4 text-sm"><span class="text-ink-3">標題：</span><del class="text-danger">{{ $titleChange[0] }}</del> → <ins class="no-underline bg-good/15">{{ $titleChange[1] }}</ins></p>
    @endif

    @if($paragraphs !== null)
        <h2 class="m-0 mb-3 font-sans text-sm font-bold tracking-normal text-ink-2">{{ $r->tool === 'update_content' ? '要改成這樣（只顯示有變更的段落）' : '要寫入的內容' }}</h2>
        <div class="revision-diff flex flex-col gap-3 mb-6 font-serif text-[17px] leading-[1.8]">
            @forelse($paragraphs as $p)
                <div data-proposed-paragraph>
                    <div class="mb-1 font-sans text-xs text-ink-3">{{ $p['context'] }}</div>
                    <p class="m-0 p-3 rounded-[var(--radius-card)] border border-line bg-card whitespace-pre-wrap">{!! $p['html'] !!}</p>
                </div>
            @empty
                <p class="m-0 font-sans text-sm text-ink-3">內文沒有變更。</p>
            @endforelse
        </div>
    @endif

    @if($args)
        <dl class="m-0 mb-6 grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
            @foreach($args as $k => $v)
                <dt class="font-mono text-ink-3">{{ $k }}</dt><dd class="m-0 break-all">{{ is_array($v) ? implode('、', $v) : $v }}</dd>
            @endforeach
        </dl>
    @endif

    @if($r->status === 'pending')
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.agents.requests.reject', $r) }}">
                @csrf
                <button type="submit" class="h-10 px-4 rounded-lg border border-line-2 text-sm hover:border-danger hover:text-danger">拒絕</button>
            </form>
            <form method="POST" action="{{ route('admin.agents.requests.approve', $r) }}">
                @csrf
                <button type="submit" class="h-10 px-4 rounded-lg bg-ink text-paper text-sm font-semibold hover:bg-accent">{{ $approveLabel }}</button>
            </form>
        </div>
    @elseif($r->error)
        <p class="m-0 text-sm text-danger">{{ $r->error }}</p>
    @endif
</div>
@endsection
