{{--
    Tweet list items grouped by month (JYU-537). Used for the first page and for
    "load older" pages; the JS drops a month heading that repeats the last one on screen.
--}}
<div data-has-more="{{ $tweets->hasMorePages() ? '1' : '0' }}" data-next-url="{{ $tweets->nextPageUrl() }}">
    @php $currentMonth = null; @endphp
    @foreach($tweets as $tweet)
        @php $month = $tweet->published_at?->format('Y-m'); @endphp
        @if($month !== $currentMonth)
            @php $currentMonth = $month; @endphp
            <h2 data-month="{{ $month }}" class="m-0 mt-10 first:mt-0 font-sans text-[13px] font-bold tracking-[0.06em] text-ink-3">{{ $tweet->published_at?->translatedFormat(__('public.tweets_month_format')) }}</h2>
        @endif
        <x-tweet-card :tweet="$tweet" variant="list" />
    @endforeach
</div>
