/**
 * Passkeys (JYU-566): 「用 Passkey 登入」, the passkey list on 「帳號與安全」, the
 * identity re-check before adding / deleting, and the one-time offer after a
 * password login. The WebAuthn ceremonies run server-side in laravel/passkeys;
 * @simplewebauthn/browser turns its JSON options into navigator.credentials calls.
 *
 * Elements that only make sense with passkey support start with style="display:none"
 * and are revealed with x-show, so unsupported browsers never see them flash.
 */
import { browserSupportsWebAuthn, startAuthentication, startRegistration } from '@simplewebauthn/browser';

// This device already has a passkey / the admin said 「不用了」: don't offer again.
const HAS_PASSKEY = 'passkeys.thisDevice';
const NO_THANKS = 'passkeys.offerDismissed';

const remember = (key) => { try { localStorage.setItem(key, '1'); } catch { /* private mode */ } };
const recalled = (key) => { try { return localStorage.getItem(key) === '1'; } catch { return false; } };

async function call(url, { method = 'GET', body } = {}) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw Object.assign(new Error(data.message ?? res.statusText), { status: res.status, data });
    }
    return data;
}

/** A sentence the admin can act on, for whatever the browser or server threw. */
function describe(error) {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'AbortError':
            return '已取消，或這台裝置沒有可用的 passkey。';
        case 'InvalidStateError':
            return '這台裝置已經有這個帳號的 passkey 了。';
        case 'SecurityError':
            return '這個網址不能使用 passkey（需要 HTTPS 和正式網域）。';
    }
    switch (error?.status) {
        case 419: return '頁面放太久了，請重新整理後再試一次。';
        case 429: return '試太多次了，請一分鐘後再試。';
        case 422: return error.data?.errors?.password?.[0] ?? 'Passkey 驗證沒有通過，請再試一次或改用密碼。';
    }
    // Unclassified: name the status / error so the next report says what failed.
    const detail = error?.status ? `HTTP ${error.status}` : error?.name;
    return detail ? `出了點問題（${detail}），請再試一次。` : '出了點問題，請再試一次。';
}

/** Registers a passkey on this device. Throws { status: 423 } when identity must be re-confirmed first. */
async function addPasskey({ optionsUrl, storeUrl, name }) {
    const { options } = await call(optionsUrl);
    const credential = await startRegistration({ optionsJSON: options });
    const saved = await call(storeUrl, { method: 'POST', body: { name, credential } });
    remember(HAS_PASSKEY);
    return saved;
}

async function verifyPasskey({ optionsUrl, verifyUrl, extra = {} }) {
    const { options } = await call(optionsUrl);
    const credential = await startAuthentication({ optionsJSON: options });
    const result = await call(verifyUrl, { method: 'POST', body: { credential, ...extra } });
    remember(HAS_PASSKEY);
    return result;
}

/** Login page: 「用 Passkey 登入」. Honours the 「記住我」checkbox (x-ref="remember"). */
window.passkeyLogin = function () {
    return {
        supported: false,
        busy: false,
        error: '',
        init() {
            this.supported = browserSupportsWebAuthn();
        },
        async signIn() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const { redirect } = await verifyPasskey({
                    optionsUrl: this.$root.dataset.optionsUrl,
                    verifyUrl: this.$root.dataset.loginUrl,
                    extra: { remember: !!this.$refs.remember?.checked },
                });
                window.location.assign(redirect);
            } catch (e) {
                this.error = describe(e);
                this.busy = false;
            }
        },
    };
};

/**
 * 「帳號與安全」: add / delete passkeys, re-confirming identity first when the last
 * confirmation is older than the server's window (data-confirmed-until, unix seconds).
 */
window.passkeyManager = function () {
    return {
        supported: false,
        busy: false,
        error: '',
        reauth: false,
        password: '',
        reauthError: '',
        confirmedUntil: 0,
        next: null,
        init() {
            this.supported = browserSupportsWebAuthn();
            this.confirmedUntil = Number(this.$root.dataset.confirmedUntil) || 0;
        },
        get urls() {
            return this.$root.dataset;
        },
        fresh() {
            return Date.now() / 1000 < this.confirmedUntil - 5;
        },
        askIdentity(next) {
            this.next = next;
            this.password = '';
            this.reauthError = '';
            this.reauth = true;
            this.$nextTick(() => this.$refs.password?.focus());
        },
        confirmed() {
            this.confirmedUntil = Date.now() / 1000 + Number(this.urls.confirmTimeout);
            this.reauth = false;
            const next = this.next;
            this.next = null;
            next?.();
        },
        async confirmWithPassword() {
            if (this.busy) return;
            this.busy = true;
            this.reauthError = '';
            try {
                await call(this.urls.confirmPasswordUrl, { method: 'POST', body: { password: this.password } });
                this.busy = false;
                this.confirmed();
            } catch (e) {
                this.reauthError = describe(e);
                this.busy = false;
            }
        },
        async confirmWithPasskey() {
            if (this.busy) return;
            this.busy = true;
            this.reauthError = '';
            try {
                await verifyPasskey({ optionsUrl: this.urls.confirmOptionsUrl, verifyUrl: this.urls.confirmPasskeyUrl });
                this.busy = false;
                this.confirmed();
            } catch (e) {
                this.reauthError = describe(e);
                this.busy = false;
            }
        },
        async add() {
            if (this.busy) return;
            if (!this.fresh()) {
                this.askIdentity(() => this.add());
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                await addPasskey({ optionsUrl: this.urls.optionsUrl, storeUrl: this.urls.storeUrl, name: this.urls.defaultName });
                window.location.reload();
            } catch (e) {
                this.busy = false;
                if (e.status === 423) {
                    this.confirmedUntil = 0;
                    this.askIdentity(() => this.add());
                    return;
                }
                this.error = describe(e);
            }
        },
        remove(form, name) {
            if (!window.confirm(`刪除「${name}」？之後就不能再用它登入。`)) return;
            if (this.fresh()) {
                form.submit();
            } else {
                this.askIdentity(() => form.submit());
            }
        },
    };
};

/** Standalone confirm page (reached when a delete arrived without a fresh confirmation). */
window.passkeyConfirm = function () {
    return {
        supported: false,
        busy: false,
        error: '',
        init() {
            this.supported = browserSupportsWebAuthn();
        },
        async confirm() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const { redirect } = await verifyPasskey({ optionsUrl: this.$root.dataset.optionsUrl, verifyUrl: this.$root.dataset.confirmUrl });
                window.location.assign(redirect);
            } catch (e) {
                this.error = describe(e);
                this.busy = false;
            }
        },
    };
};

/** After a password login: 「下次用 passkey 登入這台裝置？」 once per device. */
window.passkeyOffer = function () {
    return {
        show: false,
        busy: false,
        done: false,
        error: '',
        init() {
            this.show = browserSupportsWebAuthn() && !recalled(HAS_PASSKEY) && !recalled(NO_THANKS);
        },
        dismiss() {
            remember(NO_THANKS);
            this.show = false;
        },
        async add() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            const { optionsUrl, storeUrl, defaultName, accountUrl } = this.$root.dataset;
            try {
                await addPasskey({ optionsUrl, storeUrl, name: defaultName });
                this.done = true;
            } catch (e) {
                if (e.status === 423) {
                    window.location.assign(accountUrl);
                    return;
                }
                this.error = describe(e);
            }
            this.busy = false;
        },
    };
};
