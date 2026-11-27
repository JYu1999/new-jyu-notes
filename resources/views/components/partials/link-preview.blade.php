{{-- External link preview card for a tweet (JYU-540). Renders nothing when there's no usable preview. --}}
@php $preview = app(\App\Services\LinkPreviewService::class)->forTweet($tweet); @endphp
@if($preview)
    <a href="{{ $preview->url }}" target="_blank" rel="noopener noreferrer nofollow" data-link-preview
        class="flex flex-col gap-0.5 mt-3 px-4 py-3.5 max-w-[560px] rounded-[var(--radius-control)] border border-line-2 bg-card text-ink hover:border-accent hover:text-ink transition-colors">
        <span class="font-mono text-xs text-ink-3">{{ $preview->domain }}</span>
        <span class="text-[15px] font-semibold leading-snug line-clamp-2">{{ $preview->title }}</span>
    </a>
@endif
