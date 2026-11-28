@extends('layouts.admin')

@section('title', '帳號與安全')

{{--
    帳號與安全 (JYU-566): reached from the account block at the bottom of the sidebar.
    For now only passkeys; 修改密碼 / 登出其他裝置 can become further sections here.
--}}
@section('content')
@php
    $tz = config('app.display_timezone');
    $today = now($tz);
    $day = fn ($at) => $at->copy()->timezone($tz)->format($at->copy()->timezone($tz)->year === $today->year ? 'm/d' : 'Y/m/d');
    $when = function ($at) use ($tz, $today, $day) {
        $local = $at->copy()->timezone($tz);

        return match (true) {
            $local->isSameDay($today) => '今天 '.$local->format('H:i'),
            $local->isSameDay($today->copy()->subDay()) => '昨天 '.$local->format('H:i'),
            default => $day($at),
        };
    };
    $confirmedUntil = session('auth.password_confirmed_at', 0) + config('passkeys.confirm_timeout');
    $button = 'h-9 px-3 rounded-lg border border-line-2 text-[13px]';
@endphp
<div class="max-w-[760px]" x-data="passkeyManager()"
    data-options-url="{{ route('admin.account.passkeys.options') }}"
    data-store-url="{{ route('admin.account.passkeys.store') }}"
    data-confirm-password-url="{{ route('admin.account.confirm.password') }}"
    data-confirm-options-url="{{ route('admin.account.confirm.passkey-options') }}"
    data-confirm-passkey-url="{{ route('admin.account.confirm.passkey') }}"
    data-confirm-timeout="{{ config('passkeys.confirm_timeout') }}"
    data-confirmed-until="{{ $confirmedUntil }}"
    data-default-name="{{ $defaultPasskeyName }}">
    <h1 class="m-0 mb-8 font-serif text-[32px] font-medium">帳號與安全</h1>

    @if($errors->any())
        <p class="m-0 mb-5 px-4 py-2 rounded-[var(--radius-control)] bg-danger/10 text-danger text-sm">{{ $errors->first() }}</p>
    @endif

    <div class="flex flex-col gap-6">
        {{-- Account (read-only) --}}
        <section aria-labelledby="me-h" class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
            <h2 id="me-h" class="sr-only">帳號</h2>
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="flex items-center justify-center size-10 rounded-full bg-ink text-paper font-bold">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <div class="text-[15px] font-semibold truncate">{{ $user->name }}</div>
                    <div class="text-sm text-ink-3 truncate">{{ $user->email }}</div>
                </div>
            </div>
        </section>

        {{-- Passkeys --}}
        <section aria-labelledby="pk-h" class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div>
                    <h2 id="pk-h" class="m-0 mb-1 font-sans text-lg font-bold tracking-normal">Passkey</h2>
                    <p class="m-0 text-sm text-ink-3">用 Touch ID、Face ID 或手機登入，不用打密碼。</p>
                </div>
                <div class="text-right">
                    <button type="button" @click="add()" :disabled="busy || !supported"
                        class="h-9 px-3.5 rounded-lg bg-ink text-paper text-[13px] font-semibold hover:bg-accent disabled:opacity-50"
                        x-text="busy && !reauth ? '等待 passkey…' : '+ 新增 passkey'">+ 新增 passkey</button>
                    <p class="m-0 mt-1 text-xs text-ink-3" x-show="supported">會命名為「{{ $defaultPasskeyName }}」，之後可以改名</p>
                    <p class="m-0 mt-1 text-xs text-ink-3" x-show="!supported" style="display: none">這個瀏覽器不支援 passkey</p>
                </div>
            </div>
            <p x-show="error" x-text="error" role="alert" style="display: none" class="m-0 mb-3 px-3 py-2 rounded-[var(--radius-control)] bg-danger/10 text-danger text-sm"></p>

            @forelse($passkeys as $passkey)
                <div data-passkey="{{ $passkey->id }}" x-data="{ editing: false }" class="py-3 border-b border-line last:border-0">
                    <div x-show="!editing" class="flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <div class="text-[15px] font-semibold">{{ $passkey->name }}</div>
                            <div class="text-xs text-ink-3">
                                新增 {{ $day($passkey->created_at) }} ·
                                {{ $passkey->last_used_at ? '最後使用 '.$when($passkey->last_used_at) : '尚未使用' }}@if($passkey->authenticator) · {{ $passkey->authenticator }}@endif
                            </div>
                        </div>
                        <button type="button" @click="editing = true; $nextTick(() => $refs.name.select())" class="{{ $button }} hover:border-accent">改名</button>
                        <form method="POST" action="{{ route('admin.account.passkeys.destroy', $passkey) }}" @submit.prevent="remove($el, @js($passkey->name))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="{{ $button }} text-danger hover:border-danger">刪除</button>
                        </form>
                    </div>
                    <form x-show="editing" style="display: none" method="POST" action="{{ route('admin.account.passkeys.update', $passkey) }}" class="flex flex-wrap items-center gap-2" @keydown.escape="editing = false">
                        @csrf
                        @method('PATCH')
                        <label for="pk-name-{{ $passkey->id }}" class="sr-only">Passkey 名稱</label>
                        <input id="pk-name-{{ $passkey->id }}" x-ref="name" name="name" value="{{ $passkey->name }}" required maxlength="60"
                            class="flex-1 min-w-[200px] h-9 px-3 bg-paper border border-line-2 rounded-lg text-sm focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft">
                        <button type="button" @click="editing = false" class="{{ $button }}">取消</button>
                        <button type="submit" class="h-9 px-3.5 rounded-lg bg-ink text-paper text-[13px] font-semibold hover:bg-accent">儲存</button>
                    </form>
                </div>
            @empty
                <p class="m-0 py-3 text-sm text-ink-3">還沒有 passkey。新增一把，下次登入只要按一下；密碼登入仍然可以用。</p>
            @endforelse
        </section>
    </div>

    @include('admin.account._reauth', ['hasPasskeys' => $passkeys->isNotEmpty()])
</div>
@endsection
