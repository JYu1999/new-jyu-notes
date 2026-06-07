{{-- YouTube 貼上偵測彈窗 — 須置於 x-data="markdownMediaInsert()" 且 class 含 relative 的容器內 --}}
<div x-show="ytPrompt" x-cloak
    @keydown.escape.window="dismissYtPrompt()"
    @click.outside="dismissYtPrompt()"
    class="absolute bottom-3 left-3 z-10 flex items-center gap-3 bg-card border border-line rounded-md shadow-lg px-3 py-2 text-sm">
    <span class="text-ink-3">📺 偵測到 YouTube 連結</span>
    <button type="button" @click="embedYoutube()"
        class="bg-accent text-white px-2.5 py-1 rounded text-xs font-medium hover:bg-accent-ink">Embed 影片</button>
    <button type="button" @click="dismissYtPrompt()"
        class="text-xs text-ink-3 hover:text-accent">保留連結</button>
</div>
