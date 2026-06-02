@extends('layouts.admin')

@section('title', 'API Tokens')

@section('content')
<header class="mb-6">
    <h1 class="font-serif text-2xl font-semibold">API Tokens</h1>
    <p class="text-sm text-ink-3 mt-1">產生給 AI Agent 使用的 API token，可設期限與權限範圍。</p>
</header>

@if(session('newToken'))
    <div class="bg-accent-soft border border-accent rounded-md p-4 mb-6">
        <p class="text-sm font-medium mb-2">「{{ session('newTokenName') }}」的 token（只會顯示這一次，請立即複製）：</p>
        <div class="flex items-center gap-2">
            <code class="flex-1 bg-paper border border-line rounded px-3 py-2 text-xs break-all">{{ session('newToken') }}</code>
            <button type="button"
                onclick="navigator.clipboard.writeText('{{ session('newToken') }}')"
                class="bg-accent text-white px-3 py-2 rounded text-sm">複製</button>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="bg-danger-soft border border-danger rounded-md p-3 mb-6 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid lg:grid-cols-[1fr_360px] gap-6">
    {{-- Existing tokens --}}
    <div class="space-y-3">
        <h2 class="text-xs text-ink-3 font-mono uppercase tracking-wide">現有 Token</h2>
        @forelse($tokens as $token)
            <div class="bg-card border border-line rounded-md p-4 flex items-start justify-between gap-3">
                <div class="text-sm">
                    <div class="font-medium">{{ $token->name }}</div>
                    <div class="text-ink-3 text-xs mt-1 font-mono break-all">{{ implode(', ', $token->abilities ?? []) }}</div>
                    <div class="text-ink-3 text-xs mt-1">
                        到期：{{ $token->expires_at?->format('Y-m-d H:i') ?? '永不' }}
                        · 最後使用：{{ $token->last_used_at?->diffForHumans() ?? '未使用' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}"
                    onsubmit="return confirm('撤銷這個 token？')">
                    @csrf @method('DELETE')
                    <button class="text-danger hover:underline text-sm">撤銷</button>
                </form>
            </div>
        @empty
            <p class="text-ink-3 text-sm py-6">尚無 token。</p>
        @endforelse
    </div>

    {{-- Create form --}}
    <aside class="bg-card border border-line rounded-md p-4">
        <h2 class="text-xs text-ink-3 font-mono uppercase tracking-wide mb-3">產生新 Token</h2>
        <form method="POST" action="{{ route('admin.tokens.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-ink-3 mb-1">名稱</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
            </div>

            <div>
                <label class="block text-xs text-ink-3 mb-1">到期</label>
                <select name="expires_in" x-data x-on:change="$refs.customWrap.style.display = ($event.target.value === 'custom' ? 'block' : 'none')"
                    class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm">
                    <option value="1h">1 小時</option>
                    <option value="8h" selected>8 小時</option>
                    <option value="24h">24 小時</option>
                    <option value="7d">7 天</option>
                    <option value="custom">自訂…</option>
                </select>
                <div x-ref="customWrap" style="display:none" class="mt-2">
                    <input type="datetime-local" name="expires_at"
                        class="w-full bg-paper border border-line rounded px-2 py-1.5 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs text-ink-3 mb-2">權限</label>
                <div class="space-y-3">
                    @foreach(\App\Support\Abilities::matrix() as $resource => $actions)
                        <div>
                            <div class="text-xs font-mono uppercase text-ink-2 mb-1">{{ $resource }}</div>
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                @foreach($actions as $action)
                                    <label class="inline-flex items-center gap-1 text-xs cursor-pointer">
                                        <input type="checkbox" name="abilities[]" value="{{ $resource }}:{{ $action }}">
                                        <span>{{ $action }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <button class="w-full bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium">
                產生 Token
            </button>
        </form>
    </aside>
</div>
@endsection
