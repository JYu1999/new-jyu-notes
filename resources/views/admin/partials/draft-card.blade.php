{{-- A draft in "繼續寫" / all drafts (JYU-542). $d from App\Services\DraftService. --}}
<a href="{{ $d['edit_url'] }}" data-draft
    class="flex flex-col gap-2 p-4 min-h-[132px] rounded-[var(--radius-card)] bg-card border border-line text-ink hover:border-line-2 hover:text-ink transition-colors">
    <span class="font-mono text-[11px] text-ink-3">{{ $d['kind'] }} · {{ strtoupper($d['locale']) }} · {{ $d['updated_at']->locale('zh_TW')->diffForHumans() }}</span>
    <span class="{{ $d['type'] === 'tweet' ? 'text-[15px] leading-normal text-ink-2' : 'font-semibold text-base leading-snug' }} line-clamp-3">{{ $d['title'] }}</span>
    @if($d['detail'])
        <span class="mt-auto text-[13px] text-ink-2">{{ $d['detail'] }}</span>
    @endif
</a>
