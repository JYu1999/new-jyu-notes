{{-- One AI activity (JYU-555). Writes link to the version diff they produced. --}}
@php
    $types = ['post' => 'posts', 'tweet' => 'tweets', 'page' => 'pages'];
    $href = $a->subject && isset($types[$a->subject_type])
        ? route('admin.revisions.index', array_filter(['type' => $types[$a->subject_type], 'id' => $a->subject_id, 'rev' => $a->content_revision_id]))
        : null;
    // Queued items follow their request: waiting / approved / rejected (JYU-556).
    $state = $a->status === 'queued'
        ? (['pending' => '等待確認', 'approved' => '已核准', 'rejected' => '已拒絕', 'failed' => '核准後執行失敗'][$a->actionRequest?->status] ?? '等待確認')
        : (['failed' => '失敗'][$a->status] ?? null);
@endphp
<li data-activity class="flex flex-col gap-0.5 py-2.5 border-b border-line last:border-0">
    @if($href && $a->content_revision_id)
        <a href="{{ $href }}" class="text-sm text-ink hover:text-accent">{{ $a->summary }}</a>
    @else
        <span class="text-sm text-ink">{{ $a->summary }}</span>
    @endif
    <span class="text-xs text-ink-3">
        {{ $a->aiConnection?->name ?? 'AI' }} · {{ $a->created_at->timezone(config('app.display_timezone'))->format($a->created_at->isToday() ? 'H:i' : 'm/d H:i') }}
        @if($state)<span class="{{ $a->status === 'failed' ? 'text-danger' : 'text-warn' }}"> · {{ $state }}</span>@endif
    </span>
</li>
