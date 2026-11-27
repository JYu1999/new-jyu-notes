@extends('layouts.admin')

@section('title', 'AI 連線')

{{-- AI connections (JYU-554): connect Claude and other MCP clients without copying API tokens. --}}
@section('content')
<div class="max-w-[960px]">
    <h1 class="m-0 mb-2 font-serif text-[32px] font-medium">AI 連線</h1>
    <p class="m-0 mb-8 max-w-[640px] text-[15px] text-ink-2">讓 Claude 這類 AI 助理直接查資料、寫草稿、建翻譯。不用再複製 API Token；每一個修改都會留在版本紀錄裡，發布或隱藏要你點頭才會生效。</p>

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
@endsection
