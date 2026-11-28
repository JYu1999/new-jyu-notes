{{--
    JYU-566: shown once after a password login, only when this browser supports
    passkeys and this device has neither a passkey nor said 「不用了」(localStorage).
--}}
<div x-data="passkeyOffer()" x-show="show" style="display: none" data-passkey-offer
    data-options-url="{{ route('admin.account.passkeys.options') }}"
    data-store-url="{{ route('admin.account.passkeys.store') }}"
    data-default-name="{{ \App\Support\PasskeyDeviceName::fromUserAgent(request()->userAgent()) }}"
    data-account-url="{{ route('admin.account.show') }}"
    class="mx-6 mt-6 p-4 rounded-[var(--radius-card-lg)] bg-card border border-accent/40 flex flex-wrap items-center gap-3">
    <template x-if="!done">
        <div class="flex flex-1 flex-wrap items-center gap-3 min-w-0">
            <div class="flex-1 min-w-[220px]">
                <p class="m-0 text-[15px] font-semibold">下次用 passkey 登入這台裝置？</p>
                <p class="m-0 text-xs text-ink-3">用 Touch ID、Face ID 或手機解鎖，按一下就登入，不用再打密碼。</p>
                <p x-show="error" x-text="error" role="alert" class="m-0 mt-1 text-xs text-danger"></p>
            </div>
            <button type="button" @click="dismiss()" class="h-9 px-3 rounded-lg text-[13px] text-ink-3 hover:text-ink">不用了</button>
            <button type="button" @click="add()" :disabled="busy" class="h-9 px-3.5 rounded-lg bg-ink text-paper text-[13px] font-semibold hover:bg-accent disabled:opacity-60"
                x-text="busy ? '等待 passkey…' : '新增 passkey'">新增 passkey</button>
        </div>
    </template>
    <template x-if="done">
        <div class="flex flex-1 flex-wrap items-center gap-3">
            <p class="m-0 flex-1 text-[15px]">已新增 passkey，下次在這台裝置按「用 Passkey 登入」就好。</p>
            <a href="{{ route('admin.account.show') }}" class="text-[13px] text-accent hover:text-accent-ink">管理 passkey</a>
            <button type="button" @click="show = false" class="h-9 px-3 rounded-lg text-[13px] text-ink-3 hover:text-ink">關閉</button>
        </div>
    </template>
</div>
