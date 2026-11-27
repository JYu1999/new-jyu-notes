@if($backlinks->isNotEmpty())
    @php $loc = app()->getLocale(); @endphp
    <section class="mt-12 pt-6 border-t border-line">
        <x-ui.section-heading size="md" class="mb-4">{{ __('public.mentioned_in') }}</x-ui.section-heading>
        <div class="grid gap-3">
            @foreach($backlinks as $bl)
                @if($bl instanceof \App\Models\Tweet)
                    <x-ui.card :href="route('public.tweets.show', [$bl->locale, $bl->id])">
                        <div class="text-xs text-ink-3 font-mono mb-1">Tweet · {{ $bl->published_at?->format('Y/m/d') }}</div>
                        <div class="text-sm text-ink-2 line-clamp-2">{{ $bl->preview(120) }}</div>
                    </x-ui.card>
                @else
                    <x-ui.card :href="route('public.posts.show', [$bl->locale, $bl->slug])">
                        <div class="font-serif text-base font-semibold mb-1">{{ $bl->title }}</div>
                        @if($bl->excerpt)
                            <div class="text-xs text-ink-3 line-clamp-2">{{ $bl->excerpt }}</div>
                        @endif
                    </x-ui.card>
                @endif
            @endforeach
        </div>
    </section>
@endif
