@extends('layouts.public')

@section('title', $page->title . ' · ' . config('app.name'))

@section('content')
<article class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="font-serif text-3xl md:text-4xl font-semibold leading-tight tracking-tight mb-5">{{ $page->title }}</h1>

    @if($page->cover_image_path)
        <img src="{{ media_url($page->cover_image_path) }}" alt=""
            class="w-full rounded-lg mb-10 h-auto max-h-[400px] object-cover">
    @endif

    <div class="prose-blog">
        {!! app(\App\Support\MarkdownRenderer::class)->render($page->body) !!}
    </div>
</article>
@endsection
