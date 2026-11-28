@extends('layouts.auth')

@section('title', '管理員登入')

@section('content')
<div class="bg-card border border-line rounded-lg p-8 shadow-sm"
    x-data="passkeyLogin()"
    data-options-url="{{ route('auth.passkey.options') }}"
    data-login-url="{{ route('auth.passkey.login') }}">
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
            <input type="checkbox" name="remember" value="1" x-ref="remember"> 記住我
        </label>
        <button type="submit" class="w-full bg-accent text-white py-2.5 rounded-md hover:bg-accent-ink font-medium">
            登入
        </button>
    </form>

    {{-- JYU-566: passkey login; the password form above stays as the fallback. --}}
    <div x-show="supported" style="display: none" class="mt-5">
        <div class="flex items-center gap-3 mb-5 text-xs text-ink-3" aria-hidden="true">
            <span class="flex-1 border-t border-line"></span>或<span class="flex-1 border-t border-line"></span>
        </div>
        <button type="button" @click="signIn()" :disabled="busy"
            class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-md border border-line-2 bg-paper font-medium hover:border-accent hover:text-accent disabled:opacity-60">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0 1 9.5-4.9"/><circle cx="17" cy="14" r="2.5"/><path d="M17 16.5V22l1.5-1.2L17 19.6l1.5-1.1"/></svg>
            <span x-text="busy ? '等待 passkey…' : '用 Passkey 登入'">用 Passkey 登入</span>
        </button>
        <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-danger" style="display: none"></p>
        <p class="mt-2 text-center text-xs text-ink-3">勾選「記住我」也適用 passkey 登入</p>
    </div>
</div>
<p class="mt-6 text-center text-xs text-ink-3 font-mono">
    <a href="/" class="hover:text-accent">← 回到首頁</a>
</p>
@endsection
