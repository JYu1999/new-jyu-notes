@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-16">
    <h1 class="font-serif text-3xl font-semibold mb-2">Changelog</h1>
    <p class="text-ink-3 text-sm mb-10">What's new on the site.</p>

    @forelse($groups as $date => $items)
        <section class="mb-10">
            <h2 class="font-mono text-sm text-ink-3 uppercase tracking-wide mb-3 border-b border-line pb-2">
                {{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}
            </h2>
            <ul class="space-y-2">
                @foreach($items as $item)
                    <li class="flex gap-2 text-ink-2">
                        <span class="text-accent">–</span>
                        <span>{{ $item->title }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-ink-3">No entries yet.</p>
    @endforelse
</div>
@endsection
