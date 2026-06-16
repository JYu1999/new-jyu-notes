@if($backlinks->isNotEmpty())
    @php $loc = app()->getLocale(); @endphp
    <section class="mt-12 pt-6 border-t border-line">
        <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-4">🔗 {{ __('public.mentioned_in') }}</div>
        <div class="grid gap-3">
            @foreach($backlinks as $bl)
                @if($bl instanceof \App\Models\Tweet)
                    <a href="{{ route('public.tweets.show', [$bl->locale, $bl->id]) }}"
                        class="block p-4 border border-line rounded-lg hover:border-accent transition-colors">
                        <div class="text-[10px] uppercase tracking-widest text-ink-3 font-mono mb-1">Tweet · {{ $bl->published_at?->format('Y/m/d') }}</div>
                        <div class="text-sm text-ink-2 line-clamp-2">{{ $bl->preview(120) }}</div>
                    </a>
                @else
                    <a href="{{ route('public.posts.show', [$bl->locale, $bl->slug]) }}"
                        class="block p-4 border border-line rounded-lg hover:border-accent transition-colors">
                        <div class="font-serif text-base font-semibold mb-1">{{ $bl->title }}</div>
                        @if($bl->excerpt)
                            <div class="text-xs text-ink-3 line-clamp-2">{{ $bl->excerpt }}</div>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>
    </section>
@endif
