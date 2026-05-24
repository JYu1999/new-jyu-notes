@extends('layouts.public')

@section('title', 'Tweet · ' . config('app.name'))

@section('content')
<div class="max-w-2xl mx-auto px-6 py-12">
    <a href="{{ route('public.tweets.index', app()->getLocale()) }}" class="text-sm text-accent hover:text-accent-ink font-mono mb-6 inline-block">← 回到 tweets</a>
    <article class="bg-card border border-line rounded-lg p-6">
        <div class="text-xs uppercase tracking-widest text-ink-3 font-mono mb-3">
            {{ $tweet->published_at?->format('Y/m/d H:i') }}
        </div>

        <div class="prose-blog">
            {!! app(\App\Support\MarkdownRenderer::class)->render($tweet->body) !!}
        </div>

        @if($tweet->media)
            <div class="mt-4 grid {{ count($tweet->media) > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                @foreach($tweet->media as $m)
                    @if(($m['type'] ?? 'image') === 'image')
                        <img src="{{ asset('storage/' . $m['path']) }}" alt="{{ $m['alt'] ?? '' }}" class="rounded-md w-full">
                    @else
                        <video src="{{ asset('storage/' . $m['path']) }}" controls class="rounded-md w-full"></video>
                    @endif
                @endforeach
            </div>
        @endif

        @if($tweet->tags->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2">
                @foreach($tweet->tags as $tag)
                    @php $tagSlug = $tag->slug(app()->getLocale()); @endphp
                    @if($tagSlug)
                        <a href="{{ route('public.tags.show', [app()->getLocale(), $tagSlug]) }}" class="font-mono text-xs px-3 py-1 bg-paper-2 rounded-full text-ink-3 hover:text-accent">
                            #{{ $tag->name(app()->getLocale()) }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </article>
</div>
@endsection
