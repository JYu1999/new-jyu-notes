@extends('layouts.admin')

@section('title', 'AI 連線')

{{--
    AI connections (JYU-554 / JYU-555): connected AIs (revoke, per-connection limits),
    what AIs may do (allow / ask / deny), connect a new AI, recent activity, open tools.
--}}
@section('content')
@php
    $tz = config('app.display_timezone');
    $radio = 'h-8 px-3 rounded-[7px] text-[13px] text-ink-2 aria-checked:bg-card aria-checked:text-ink aria-checked:font-bold aria-checked:shadow-sm disabled:opacity-35 disabled:cursor-not-allowed';
@endphp
<div class="max-w-[1100px]">
    <h1 class="m-0 mb-2 font-serif text-[32px] font-medium">AI 連線</h1>
    <p class="m-0 mb-8 max-w-[640px] text-[15px] text-ink-2">讓 Claude 這類 AI 助理直接查資料、寫草稿、建翻譯。不用再複製 API Token；每一個動作都會記在下面，重要的操作要你點頭才會生效。</p>

    @if(session('success'))
        <p class="m-0 mb-5 px-4 py-2 rounded-[var(--radius-control)] bg-accent-soft text-accent-ink text-sm">{{ session('success') }}</p>
    @endif
    @if($errors->any())
        <p class="m-0 mb-5 px-4 py-2 rounded-[var(--radius-control)] bg-danger/10 text-danger text-sm">{{ $errors->first() }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_320px] items-start">
        <div class="flex flex-col gap-6 min-w-0">
            {{-- Connected AIs --}}
            <section aria-labelledby="conn-h" class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
                <h2 id="conn-h" class="m-0 mb-3 font-sans text-lg font-bold tracking-normal">已連線的 AI</h2>
                @forelse($connections as $c)
                    @php
                        $active = $c->last_used_at && $c->last_used_at->gt(now()->subDay());
                        $expired = $c->expires_at->isPast();
                        $host = parse_url((string) ($c->client?->redirect_uris[0] ?? ''), PHP_URL_HOST);
                    @endphp
                    <div data-connection="{{ $c->id }}" x-data="{ edit: false }" class="py-3 border-b border-line last:border-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <span aria-hidden="true" class="inline-flex items-center justify-center size-9 rounded-lg bg-ink text-paper font-bold">{{ mb_strtoupper(mb_substr($c->name, 0, 1)) }}</span>
                            <div class="flex-1 min-w-[200px]">
                                <div class="text-[15px] font-semibold">{{ $c->name }}@if($host)<span class="font-normal text-ink-3"> · {{ $host }}</span>@endif</div>
                                <div class="text-xs text-ink-3">
                                    {{ $c->created_at->timezone($tz)->format('m/d') }} 連線 ·
                                    最後使用 {{ $c->last_used_at ? $c->last_used_at->locale('zh_TW')->diffForHumans() : '尚未使用' }} ·
                                    授權到 {{ $c->expires_at->timezone($tz)->format('Y/m/d') }}
                                </div>
                            </div>
                            <span data-connection-state class="inline-flex items-center gap-1.5 text-xs {{ $expired ? 'text-danger' : ($active ? 'text-good' : 'text-ink-3') }}">
                                <span aria-hidden="true" class="size-2 rounded-full {{ $expired ? 'bg-danger' : ($active ? 'bg-good' : 'bg-line-2') }}"></span>{{ $expired ? '已過期' : ($active ? '使用中' : '閒置') }}
                            </span>
                            <button type="button" @click="edit = !edit" :aria-expanded="edit ? 'true' : 'false'" class="h-9 px-3 rounded-lg border border-line-2 text-[13px] hover:border-accent">權限</button>
                            <form method="POST" action="{{ route('admin.agents.connections.revoke', $c) }}" onsubmit="return confirm('中斷「{{ $c->name }}」？它的授權會立刻失效，之後要重新連線。')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="h-9 px-3 rounded-lg border border-line-2 text-[13px] text-danger hover:border-danger">中斷連線</button>
                            </form>
                        </div>

                        {{-- Per-connection limits: only as strict as the global setting or stricter --}}
                        <div x-show="edit" x-cloak class="mt-3 p-3 rounded-[var(--radius-control)] bg-paper-2">
                            <p class="m-0 mb-2 text-xs text-ink-3">只能比「AI 可以做什麼」更嚴格。沒有在授權畫面勾選的權限一律禁止。</p>
                            <form method="POST" action="{{ route('admin.agents.connections.policy', $c) }}" class="flex flex-col gap-1.5">
                                @csrf
                                @method('PUT')
                                @foreach(\App\Support\AiPolicy::ACTIONS as $key => $def)
                                    @php
                                        $allowed = \App\Support\AiPolicy::connectionModes($key, $global);
                                        $current = \App\Support\AiPolicy::effective($c, $key, $global);
                                        $granted = ! $def['grant'] || $c->allows($def['grant']);
                                    @endphp
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm">{{ $def['name'] }}@unless($granted)<span class="text-xs text-ink-3"> · 未授權</span>@endunless</span>
                                        <div role="radiogroup" aria-label="{{ $c->name }}：{{ $def['name'] }}" class="flex gap-0.5 p-0.5 rounded-lg bg-paper">
                                            @foreach(\App\Support\AiPolicy::MODE_LABELS as $mode => $label)
                                                <button type="submit" name="policy[{{ $key }}]" value="{{ $mode }}" role="radio" aria-checked="{{ $current === $mode ? 'true' : 'false' }}"
                                                    @disabled(! $granted || ! in_array($mode, $allowed, true)) class="{{ $radio }}">{{ $label }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="m-0 text-sm text-ink-3">還沒有連線的 AI。用下面的網址連接 Claude。</p>
                @endforelse
            </section>

            {{-- What AIs may do (global) --}}
            <section aria-labelledby="pol-h" class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
                <h2 id="pol-h" class="m-0 mb-1 font-sans text-lg font-bold tracking-normal">AI 可以做什麼</h2>
                <p class="m-0 mb-3 text-sm text-ink-3">套用到所有已連線的 AI。單一連線可以再收緊，不能放寬。「需確認」的動作會等你在這裡核准才執行。</p>
                <form method="POST" action="{{ route('admin.agents.policy') }}" data-policy class="flex flex-col">
                    @csrf
                    @method('PUT')
                    @foreach(\App\Support\AiPolicy::ACTIONS as $key => $def)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-2.5 border-b border-line last:border-0">
                            <div>
                                <div class="text-sm font-semibold">{{ $def['name'] }}</div>
                                @if($def['desc'])<div class="text-xs text-ink-3">{{ $def['desc'] }}</div>@endif
                            </div>
                            <div role="radiogroup" aria-label="{{ $def['name'] }}" class="flex gap-0.5 p-0.5 rounded-lg bg-paper-2">
                                @foreach(\App\Support\AiPolicy::MODE_LABELS as $mode => $label)
                                    <button type="submit" name="policy[{{ $key }}]" value="{{ $mode }}" role="radio" aria-checked="{{ $global[$key] === $mode ? 'true' : 'false' }}"
                                        @disabled(! in_array($mode, $def['modes'], true)) class="{{ $radio }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </form>
            </section>

            {{-- Connect a new AI --}}
            <section aria-labelledby="how-h" class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line" x-data="{ copied: false }">
                <h2 id="how-h" class="m-0 mb-3 font-sans text-lg font-bold tracking-normal">連接新的 AI</h2>
                <div class="flex flex-wrap items-center gap-2 p-2 pl-3 rounded-[var(--radius-control)] border border-line-2 bg-paper">
                    <span data-mcp-url class="flex-1 min-w-0 truncate font-mono text-sm">{{ $mcpUrl }}</span>
                    <button type="button" @click="navigator.clipboard?.writeText(@js($mcpUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="h-9 px-3.5 rounded-lg bg-ink text-paper text-sm font-semibold hover:bg-accent" x-text="copied ? '已複製' : '複製網址'">複製網址</button>
                </div>
                <ol class="m-0 mt-4 p-0 list-none flex flex-col gap-2.5 text-sm text-ink-2">
                    <li class="flex gap-3"><span class="inline-flex items-center justify-center size-6 shrink-0 rounded-full bg-paper-2 text-xs font-bold text-ink">1</span><span>在 Claude 的「設定 › 連接器」新增自訂連接器，貼上這個網址</span></li>
                    <li class="flex gap-3"><span class="inline-flex items-center justify-center size-6 shrink-0 rounded-full bg-paper-2 text-xs font-bold text-ink">2</span><span>跳回這個網站登入，勾選要給的權限與期限</span></li>
                    <li class="flex gap-3"><span class="inline-flex items-center justify-center size-6 shrink-0 rounded-full bg-paper-2 text-xs font-bold text-ink">3</span><span>直接對 Claude 說：「把〈某篇文章〉翻成日文草稿」</span></li>
                </ol>
            </section>
        </div>

        <div class="flex flex-col gap-6 min-w-0">
            {{-- Recent activity --}}
            <section aria-labelledby="act-h" class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
                <div class="flex items-baseline justify-between mb-1">
                    <h2 id="act-h" class="m-0 font-sans text-lg font-bold tracking-normal">AI 最近做了什麼</h2>
                    <a href="{{ route('admin.agents.activity') }}" class="text-[13px] text-accent hover:text-accent-ink">全部 →</a>
                </div>
                <ol class="m-0 p-0 list-none">
                    @forelse($activities as $a)
                        @include('admin.agents._activity', ['a' => $a])
                    @empty
                        <li class="py-3 text-sm text-ink-3">AI 還沒有做過任何事。</li>
                    @endforelse
                </ol>
            </section>

            {{-- Tools for developers --}}
            <details data-tools class="p-5 rounded-[var(--radius-card-lg)] bg-card border border-line">
                <summary class="cursor-pointer text-sm font-semibold">給開發者：開放的工具</summary>
                <ul class="m-0 mt-3 p-0 list-none flex flex-col gap-1.5">
                    @foreach($tools as $t)
                        <li class="flex items-center justify-between gap-2 font-mono text-[13px]">{{ $t['name'] }}
                            <span class="px-1.5 py-0.5 rounded font-sans text-[11px] {{ ['讀' => 'bg-paper-2 text-ink-2', '寫' => 'bg-accent-soft text-accent-ink', '需確認' => 'bg-warn/15 text-warn', '禁止' => 'bg-danger/10 text-danger'][$t['kind']] }}">{{ $t['kind'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        </div>
    </div>
</div>
@endsection
