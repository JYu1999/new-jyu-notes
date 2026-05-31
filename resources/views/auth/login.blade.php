@extends('layouts.auth')

@section('title', '管理員登入')

@section('content')
<div class="bg-card border border-line rounded-lg p-8 shadow-sm">
    <h1 class="font-serif text-xl font-semibold mb-6 text-center">管理員登入</h1>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md px-4 py-3 mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm text-ink-2 mb-1.5">Email</label>
            <input id="email" type="email" name="email" required value="{{ old('email') }}" autofocus
                class="w-full px-3 py-2 bg-paper border border-line rounded-md focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft">
        </div>
        <div>
            <label for="password" class="block text-sm text-ink-2 mb-1.5">密碼</label>
            <input id="password" type="password" name="password" required
                class="w-full px-3 py-2 bg-paper border border-line rounded-md focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft">
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-2">
            <input type="checkbox" name="remember" value="1"> 記住我
        </label>
        <button type="submit" class="w-full bg-accent text-white py-2.5 rounded-md hover:bg-accent-ink font-medium">
            登入
        </button>
    </form>
</div>
<p class="mt-6 text-center text-xs text-ink-3 font-mono">
    <a href="/" class="hover:text-accent">← 回到首頁</a>
</p>
@endsection
