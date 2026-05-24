<div data-has-more="{{ $tweets->hasMorePages() ? '1' : '0' }}" data-next-page="{{ $tweets->currentPage() + 1 }}">
    @foreach($tweets as $tweet)
        <div class="relative">
            <span class="absolute -left-[1.85rem] top-3 w-2 h-2 rounded-full bg-accent border-2 border-paper"></span>
            <x-tweet-card :tweet="$tweet" />
        </div>
    @endforeach
</div>
