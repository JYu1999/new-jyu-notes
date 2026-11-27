<!DOCTYPE html>
@php $theme = \App\Support\ThemePreference::fromRequest(request()); @endphp
<html lang="zh" @if($theme) data-theme="{{ $theme }}" data-theme-choice="{{ $theme }}" @endif>
<head>
    <meta charset="UTF-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="media-base" content="{{ rtrim(media_url(''), '/') }}">
    <title>@yield('title', '編輯') · JYu's Blog</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Focus-mode layout for the post editor (JYU-548): no admin sidebar. --}}
<body class="bg-paper text-ink min-h-screen">
    @yield('content')
</body>
</html>
