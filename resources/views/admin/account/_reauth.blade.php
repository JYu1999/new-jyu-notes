{{-- JYU-566: identity re-check before adding / deleting a passkey (inside passkeyManager()). --}}
<div x-show="reauth" style="display: none" class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog" aria-modal="true" aria-labelledby="reauth-h" @keydown.escape.window="reauth = false">
    <div class="absolute inset-0 bg-black/40" @click="reauth = false"></div>
    <div class="relative w-full max-w-sm p-6 rounded-[var(--radius-card-lg)] bg-card border border-line shadow-lg">
        <h2 id="reauth-h" class="m-0 mb-1 font-sans text-lg font-bold tracking-normal">先確認是你</h2>
        <p class="m-0 mb-4 text-sm text-ink-3">新增或刪除 passkey 前，要再驗證一次身分。</p>

        <form @submit.prevent="confirmWithPassword()" class="flex flex-col gap-3">
            <label for="reauth-password" class="text-sm text-ink-2">密碼</label>
            <input id="reauth-password" x-ref="password" x-model="password" type="password" name="password" required autocomplete="current-password"
                class="w-full px-3 py-2 bg-paper border border-line rounded-md focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft">
            <p x-show="reauthError" x-text="reauthError" role="alert" class="m-0 text-sm text-danger"></p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="reauth = false" class="h-9 px-3 rounded-lg text-[13px] text-ink-3 hover:text-ink">取消</button>
                <button type="submit" :disabled="busy" class="h-9 px-3.5 rounded-lg bg-ink text-paper text-[13px] font-semibold hover:bg-accent disabled:opacity-60">確認</button>
            </div>
        </form>

        @if($hasPasskeys)
            <div x-show="supported" class="mt-4 pt-4 border-t border-line">
                <button type="button" @click="confirmWithPasskey()" :disabled="busy"
                    class="w-full h-10 rounded-lg border border-line-2 text-sm font-medium hover:border-accent hover:text-accent disabled:opacity-60">用 passkey 確認</button>
            </div>
        @endif
    </div>
</div>
