{{-- YouTube 貼上偵測彈窗 — 須置於提供 ytPrompt/embedYoutube/dismissYtPrompt 的 x-data 容器內（markdownMediaInsert 或 youtubePastePrompt），且容器 class 含 relative --}}
<div x-show="ytPrompt" x-cloak role="dialog" aria-label="YouTube 嵌入"
    @keydown.escape.window="dismissYtPrompt()"
    @click.outside="dismissYtPrompt()"
    class="absolute bottom-3 left-3 z-10 flex flex-wrap items-center gap-3 bg-card border border-line rounded-md shadow-lg px-3 py-2 text-sm">
    <span class="text-ink-2">偵測到 YouTube 網址，要轉成嵌入影片嗎？</span>
    {{-- Start time (JYU-548): seconds or m:ss; prefilled from the URL's t= parameter. --}}
    <label class="flex items-center gap-1.5 text-xs text-ink-3">從
        <input type="text" @input="if (ytPrompt) ytPrompt.startText = $event.target.value"
            :value="ytPrompt ? ytPrompt.startText : ''" placeholder="0:00" aria-label="起始時間"
            class="w-16 h-7 px-1.5 rounded border border-line-2 bg-transparent font-mono text-xs text-ink">
        開始</label>
    <button type="button" @click="embedYoutube()"
        class="bg-accent text-white px-2.5 py-1 rounded text-xs font-medium hover:bg-accent-ink">轉成嵌入</button>
    <button type="button" @click="dismissYtPrompt()"
        class="text-xs text-ink-3 hover:text-accent">保留連結</button>
</div>
