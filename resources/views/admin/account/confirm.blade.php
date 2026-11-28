@extends('layouts.admin')

@section('title', '先確認是你')

{{--
    JYU-566: the full-page identity check, used when a passkey delete arrives
    without a fresh confirmation (the account page normally asks in a dialog).
--}}
@section('content')
<div class="max-w-sm" x-data="passkeyConfirm()"
    data-options-url="{{ route('admin.account.confirm.passkey-options') }}"
    data-confirm-url="{{ route('admin.account.confirm.passkey') }}">
    <h1 class="m-0 mb-2 font-serif text-[32px] font-medium">先確認是你</h1>
    <p class="m-0 mb-6 text-[15px] text-ink-2">新增或刪除 passkey 前，要再驗證一次身分。</p>

    <div class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
        <form method="POST" action="{{ route('admin.account.confirm.password') }}" class="flex flex-col gap-3">
            @csrf
            <label for="password" class="text-sm text-ink-2">密碼</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="current-password"
                class="w-full px-3 py-2 bg-paper border border-line rounded-md focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft">
            @error('password')
                <p class="m-0 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.account.show') }}" class="inline-flex items-center h-9 px-3 rounded-lg text-[13px] text-ink-3 hover:text-ink">取消</a>
                <button type="submit" class="h-9 px-3.5 rounded-lg bg-ink text-paper text-[13px] font-semibold hover:bg-accent">確認</button>
            </div>
        </form>

        @if($hasPasskeys)
            <div x-show="supported" class="mt-4 pt-4 border-t border-line">
                <button type="button" @click="confirm()" :disabled="busy"
                    class="w-full h-10 rounded-lg border border-line-2 text-sm font-medium hover:border-accent hover:text-accent disabled:opacity-60">用 passkey 確認</button>
                <p x-show="error" x-text="error" role="alert" style="display: none" class="m-0 mt-2 text-sm text-danger"></p>
            </div>
        @endif
    </div>
</div>
@endsection
