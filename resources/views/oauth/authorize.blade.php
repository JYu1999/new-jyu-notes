@extends('layouts.editor')

@section('title', $client->name.' 想連接你的部落格')

{{--
    OAuth consent screen for AI connections (JYU-554). Passport passes $client, $user,
    $scopes, $request and $authToken. The ticked permissions and the duration are
    stored by App\Http\Middleware\RecordAiConsent when the owner approves.
--}}
@section('content')
@php
    $redirectHost = parse_url((string) $request->query('redirect_uri'), PHP_URL_HOST) ?: parse_url((string) ($client->redirect_uris[0] ?? ''), PHP_URL_HOST);
@endphp
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-[440px] bg-card border border-line rounded-[var(--radius-card-lg)] shadow-sm p-6 md:p-8">
        <div class="flex flex-col items-center text-center gap-3 mb-6">
            <div class="flex items-center gap-3" aria-hidden="true">
                <span class="inline-flex items-center justify-center size-12 rounded-xl bg-ink text-paper text-lg font-bold">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="text-ink-3"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                <span class="inline-flex items-center justify-center size-12 rounded-xl bg-paper-2 font-serif text-lg font-semibold">J<span class="text-accent">.</span></span>
            </div>
            <h1 class="m-0 font-sans text-xl font-bold tracking-normal">{{ $client->name }} 想連接你的部落格</h1>
            <p class="m-0 text-sm text-ink-2">你目前以 <b>{{ $user->name }}</b> 登入 {{ request()->getHost() }}</p>
        </div>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="approve-form" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <fieldset class="m-0 p-0 border-0 flex flex-col gap-2">
                <legend class="mb-2 text-sm font-semibold">允許 {{ $client->name }}：</legend>
                @foreach(\App\Support\AiPermissions::ALL as $key => $perm)
                    <label class="flex items-start gap-3 p-3 rounded-[var(--radius-control)] border border-line hover:border-line-2 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked($perm['default']) class="mt-0.5 size-[18px] accent-accent">
                        <span class="text-sm"><b>{{ $perm['label'] }}</b>
                            @if($perm['hint'])<br><span class="text-xs text-ink-3">{{ $perm['hint'] }}</span>@endif
                        </span>
                    </label>
                @endforeach
            </fieldset>

            <div class="flex gap-2.5 p-3 rounded-[var(--radius-control)] bg-paper-2 text-xs text-ink-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" class="shrink-0 mt-0.5"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/></svg>
                <span>{{ $client->name }} 不能刪除內容、不能看到你的帳號密碼。授權後可隨時在「後台 › AI 連線」中斷。</span>
            </div>

            <label class="flex items-center justify-between gap-3 text-sm">授權期限
                <select name="days" class="h-10 px-3 rounded-[var(--radius-control)] border border-line-2 bg-transparent">
                    @foreach(\App\Support\AiPermissions::DURATIONS as $days)
                        <option value="{{ $days }}">{{ $days }} 天</option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="flex gap-2 mt-6">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="w-full h-11 rounded-[var(--radius-control)] border border-line-2 text-sm font-semibold text-ink hover:border-accent">拒絕</button>
            </form>
            <button type="submit" form="approve-form" class="flex-1 h-11 rounded-[var(--radius-control)] bg-ink text-paper text-sm font-semibold hover:bg-accent">允許連線</button>
        </div>

        @if($redirectHost)
            <p class="mt-4 mb-0 text-center text-xs text-ink-3">完成後會回到 <span class="font-mono text-ink-2">{{ $redirectHost }}</span></p>
        @endif
    </div>
</div>
@endsection
