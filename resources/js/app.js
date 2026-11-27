import Alpine from 'alpinejs';
import { parseYoutubeUrl, youtubeShortcode } from './youtube';
import './post-reading';

window.Alpine = Alpine;

// Theme toggle (light/dark) — persists in cookie.
// The initial theme is set before first paint: by the server from the cookie,
// or by the inline script in partials/theme-head.blade.php from the OS setting.
window.toggleTheme = function () {
    const root = document.documentElement;
    const current = root.getAttribute('data-theme')
        ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const next = current === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    root.setAttribute('data-theme-choice', next);
    document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', next);
    document.cookie = `theme=${next}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
};

// ⌘K / Ctrl+K opens search from any page (JYU-533). The header link carries
// [data-search-trigger]; JYU-538 can intercept the same event for an in-page panel.
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
document.addEventListener('DOMContentLoaded', () => {
    if (!isMac) {
        document.querySelectorAll('[data-shortcut-label]').forEach((el) => { el.textContent = 'Ctrl K'; });
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key?.toLowerCase() !== 'k' || !(e.metaKey || e.ctrlKey) || e.altKey || e.shiftKey) return;
    const trigger = document.querySelector('[data-search-trigger]');
    if (!trigger) return;
    e.preventDefault();
    const evt = new CustomEvent('open-search', { cancelable: true });
    if (window.dispatchEvent(evt)) {
        window.location.href = trigger.href;
    }
});

/**
 * Home "Latest" timeline (JYU-534): type tabs + "load more" without a page reload.
 * Links keep working without JS (they reload with ?type= / ?before=).
 * Server returns { html, next } when called with ?partial=1.
 */
window.homeFeed = function ({ url, type, next }) {
    return {
        type,
        next,
        loading: false,
        error: false,
        // Infinite scroll: load the next page when the "load more" button nears the viewport.
        // The button stays as a fallback (no IntersectionObserver, or after an error).
        init() {
            if (!('IntersectionObserver' in window) || !this.$refs.more) return;
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !this.error) this.loadMore();
            }, { rootMargin: '400px 0px' });
            observer.observe(this.$refs.more);
        },
        async fetchPage(params) {
            this.loading = true;
            this.error = false;
            try {
                const qs = new URLSearchParams({ ...params, partial: '1' });
                const res = await fetch(`${url}?${qs}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                if (!res.ok) throw new Error(String(res.status));
                return await res.json();
            } catch (e) {
                this.error = true;
                return null;
            } finally {
                this.loading = false;
            }
        },
        async switchTo(type) {
            if (this.loading || type === this.type) return;
            const data = await this.fetchPage(type === 'all' ? {} : { type });
            if (!data) return;
            this.type = type;
            this.next = data.next;
            this.$refs.list.innerHTML = data.html;
            this.$refs.empty.classList.toggle('hidden', data.html.trim() !== '');
            const u = new URL(window.location.href);
            if (type === 'all') u.searchParams.delete('type'); else u.searchParams.set('type', type);
            u.searchParams.delete('before');
            history.replaceState(null, '', u);
        },
        async loadMore() {
            if (this.loading || !this.next) return;
            const params = { before: this.next };
            if (this.type !== 'all') params.type = this.type;
            const data = await this.fetchPage(params);
            if (!data) return;
            this.$refs.list.insertAdjacentHTML('beforeend', data.html);
            this.next = data.next;
            // If the page is still short (button still near the viewport), keep loading.
            this.$nextTick(() => {
                const more = this.$refs.more;
                if (this.next && more && more.getBoundingClientRect().top < window.innerHeight + 400) this.loadMore();
            });
        },
    };
};

/**
 * AJAX live filter: posts a form's serialized fields to a URL + ?partial=1,
 * replaces the target element's innerHTML with the response, and updates
 * window history so the URL reflects current filter state.
 *
 * Usage:
 *   <div x-data="liveFilter({ url: '/admin/posts', target: '#post-results' })">
 *       <form @input.debounce.300ms="submit($event)" @change="submit($event)">...</form>
 *   </div>
 */
window.liveFilter = function ({ url, target }) {
    return {
        loading: false,
        controller: null,
        submit($event) {
            const formEl = $event?.target?.closest('form') ?? this.$el.querySelector('form');
            if (!formEl) return;

            const fd = new FormData(formEl);
            const params = new URLSearchParams();
            for (const [k, v] of fd.entries()) {
                if (v !== '' && v !== null) params.append(k, v);
            }
            params.set('partial', '1');

            // Cancel in-flight request
            if (this.controller) this.controller.abort();
            this.controller = new AbortController();

            this.loading = true;
            const fullUrl = url + (params.toString() ? '?' + params.toString() : '');

            fetch(fullUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                signal: this.controller.signal,
            })
                .then((r) => r.text())
                .then((html) => {
                    const targetEl = document.querySelector(target);
                    if (targetEl) targetEl.innerHTML = html;

                    // Update URL for shareability (without `partial`)
                    params.delete('partial');
                    const cleanUrl = url + (params.toString() ? '?' + params.toString() : '');
                    window.history.replaceState({}, '', cleanUrl);
                })
                .catch((err) => {
                    if (err.name !== 'AbortError') console.error('liveFilter failed:', err);
                })
                .finally(() => { this.loading = false; });
        },
    };
};

/**
 * Cover image upload widget.
 * Uses /admin/media endpoint which returns JSON { url, path, ... }.
 */
window.coverUpload = function ({ initial }) {
    return {
        path: initial || '',
        uploading: false,
        error: null,
        get mediaBase() {
            return document.querySelector('meta[name=media-base]')?.content ?? '';
        },
        get previewUrl() {
            return this.path ? this.mediaBase + '/' + this.path : '';
        },
        async upload(file) {
            if (!file) return;
            this.error = null;
            this.uploading = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const res = await fetch('/admin/media', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                if (!res.ok) {
                    const txt = await res.text();
                    throw new Error('上傳失敗 (' + res.status + '): ' + txt.slice(0, 100));
                }
                const data = await res.json();
                this.path = data.path;
            } catch (e) {
                this.error = e.message || '上傳失敗';
            } finally {
                this.uploading = false;
            }
        },
        clear() {
            this.path = '';
        },
    };
};

/**
 * Tweet media upload widget (Twitter-style, max 4 items).
 * Each item: { path, type: 'image'|'video', alt }.
 * Uses /admin/media endpoint which returns JSON { url, path, mime_type, ... }.
 */
window.tweetMediaUpload = function ({ initial, max = 4 }) {
    return {
        items: (initial || []).map((m) => ({ path: m.path, type: m.type, alt: m.alt ?? '', sensitive: !!m.sensitive })),
        uploading: 0,
        error: null,
        get mediaBase() {
            return document.querySelector('meta[name=media-base]')?.content ?? '';
        },
        get full() {
            return this.items.length >= max;
        },
        url(item) {
            return this.mediaBase + '/' + item.path;
        },
        async add(fileList) {
            this.error = null;
            const incoming = Array.from(fileList);
            const files = incoming.slice(0, max - this.items.length - this.uploading);
            if (incoming.length > files.length) {
                this.error = `最多 ${max} 個媒體`;
            }
            await Promise.all(files.map((f) => this.uploadOne(f)));
        },
        async uploadOne(file) {
            this.uploading++;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const res = await fetch('/admin/media', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                if (!res.ok) {
                    const txt = await res.text();
                    throw new Error('上傳失敗 (' + res.status + '): ' + txt.slice(0, 100));
                }
                const data = await res.json();
                this.items.push({
                    path: data.path,
                    type: data.mime_type.startsWith('video/') ? 'video' : 'image',
                    alt: '',
                    sensitive: false,
                });
            } catch (e) {
                this.error = e.message || '上傳失敗';
            } finally {
                this.uploading--;
            }
        },
        remove(index) {
            this.items.splice(index, 1);
        },
    };
};

/**
 * Shared YouTube paste-detection behavior, spread into Alpine components.
 * Expects x-ref="body" (textarea) in scope; pairs with the
 * admin/partials/youtube-embed-prompt blade partial
 * (ytPrompt / embedYoutube / dismissYtPrompt 名稱須一致).
 */
const youtubePasteBehavior = {
    ytPrompt: null, // { url, id, start } — 待確認的 YouTube embed
    detectYoutubePaste(event) {
        const text = event.clipboardData?.getData('text/plain') ?? '';
        const parsed = parseYoutubeUrl(text);
        if (!parsed) return; // 一般文字貼上不攔截
        // 不 preventDefault：照常貼上原始連結（保留原生 undo）。
        // 貼上本身會觸發 textarea 的 input 事件，須等它過了再開彈窗，
        // 否則 @input 的 dismiss 會立刻把彈窗關掉。
        setTimeout(() => {
            this.ytPrompt = { url: text.trim(), ...parsed };
        }, 0);
    },
    embedYoutube() {
        if (!this.ytPrompt) return;
        const ta = this.$refs.body;
        const idx = ta.value.lastIndexOf(this.ytPrompt.url);
        if (idx !== -1) {
            const code = youtubeShortcode(this.ytPrompt);
            ta.value = ta.value.slice(0, idx) + code + ta.value.slice(idx + this.ytPrompt.url.length);
            const pos = idx + code.length;
            ta.setSelectionRange(pos, pos);
            ta.focus();
        }
        this.ytPrompt = null;
    },
    dismissYtPrompt() {
        this.ytPrompt = null;
    },
};

const mentionBehavior = {
    mentionActive: false,
    mentionExcludeType: null,
    mentionExcludeId: null,
    mentionQuery: '',
    mentionResults: [],
    mentionIndex: 0,
    mentionStart: -1,
    _mentionTimer: null,
    _mentionController: null,

    // 取得查詢用 locale：新增頁讀 select，編輯頁用初始值
    mentionLocale() {
        const sel = document.querySelector('select[name=locale]');
        return sel ? sel.value : (this.locale || 'zh');
    },

    // 每次 textarea input 觸發：偵測游標前是否有有效的 @query
    detectMention() {
        const ta = this.$refs.body;
        const pos = ta.selectionStart;
        const text = ta.value.slice(0, pos);
        const at = text.lastIndexOf('@');
        if (at === -1) return this.closeMention();

        // @ 必須位於行首或空白後（避免 email 如 jyu@furuke.com 誤觸）
        const before = at === 0 ? '\n' : text[at - 1];
        if (!/\s/.test(before)) return this.closeMention();

        const query = text.slice(at + 1);
        // 允許空白以支援多關鍵字搜尋（如「監控 導入」）；換行或過長則視為一般文字，結束 mention。
        if (/\n/.test(query) || query.length > 50) return this.closeMention();

        this.mentionStart = at;
        this.mentionQuery = query;
        this.mentionActive = true;
        this.searchMentions(query);
    },

    searchMentions(q) {
        clearTimeout(this._mentionTimer);
        if (this._mentionController) this._mentionController.abort();
        // 空查詢（剛打完 @）不 early-return：交給後端回傳最近文章供參考。
        this._mentionTimer = setTimeout(async () => {
            if (!this.mentionActive) return;
            this._mentionController = new AbortController();
            const params = new URLSearchParams({
                q,
                locale: this.mentionLocale(),
                exclude_type: this.mentionExcludeType ?? '',
                exclude_id: this.mentionExcludeId ?? '',
            });
            try {
                const res = await fetch(`/admin/mentions/search?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: this._mentionController.signal,
                });
                if (!res.ok) { this.mentionResults = []; return; }
                this.mentionResults = await res.json();
                this.mentionIndex = 0;
            } catch (e) {
                if (e.name !== 'AbortError') this.mentionResults = [];
            }
        }, 250);
    },

    onMentionKeydown(e) {
        if (!this.mentionActive || this.mentionResults.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.mentionIndex = (this.mentionIndex + 1) % this.mentionResults.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.mentionIndex = (this.mentionIndex - 1 + this.mentionResults.length) % this.mentionResults.length;
        } else if (e.key === 'Enter') {
            e.preventDefault();
            this.pickMention(this.mentionResults[this.mentionIndex]);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            this.closeMention();
        }
    },

    pickMention(item) {
        if (!item) return;
        const ta = this.$refs.body;
        const pos = ta.selectionStart;
        // 跳脫標題中的 [ ]，避免破壞 Markdown 連結語法（如標題含 [2024]）
        const safeTitle = item.label.replace(/[\[\]]/g, '\\$&');
        const link = `[${safeTitle}](${item.url})`;
        ta.value = ta.value.slice(0, this.mentionStart) + link + ta.value.slice(pos);
        const caret = this.mentionStart + link.length;
        ta.setSelectionRange(caret, caret);
        ta.focus();
        this.closeMention();
    },

    closeMention() {
        if (this._mentionController) this._mentionController.abort();
        this.mentionActive = false;
        this.mentionResults = [];
        this.mentionQuery = '';
        this.mentionStart = -1;
    },
};

/**
 * YouTube paste prompt for plain textareas without media upload
 * (e.g. the tweet composer). Expects x-ref="body" in scope.
 */
window.youtubePastePrompt = function () {
    return {
        ...youtubePasteBehavior,
        handlePaste(event) {
            this.detectYoutubePaste(event);
        },
    };
};

/**
 * Markdown textarea media insert: toolbar button + drag-drop + paste.
 * Uploads to /admin/media, inserts markdown (image) or <video> tag at cursor.
 * Expects x-ref="body" (textarea) and x-ref="file" (hidden file input) in scope.
 */
window.markdownMediaInsert = function ({ locale = 'zh', postId = null } = {}) {
    return {
        ...youtubePasteBehavior,
        ...mentionBehavior,
        locale,
        postId,
        mentionExcludeType: 'post',
        mentionExcludeId: postId,
        uploading: 0,
        error: null,
        dragging: false,
        pick() {
            this.$refs.file.click();
        },
        handleFiles(fileList) {
            this.error = null;
            Array.from(fileList).forEach((f) => this.uploadAndInsert(f));
        },
        handlePaste(event) {
            const files = Array.from(event.clipboardData?.files ?? []);
            if (files.length) {
                event.preventDefault();
                this.handleFiles(files);
                return;
            }
            this.detectYoutubePaste(event);
        },
        async uploadAndInsert(file) {
            const token = crypto.randomUUID().slice(0, 8);
            const placeholder = `![上傳中：${file.name}…](#${token})`;
            this.insertAtCursor(placeholder + '\n');
            this.uploading++;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const res = await fetch('/admin/media', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                if (!res.ok) {
                    const txt = await res.text();
                    throw new Error('上傳失敗 (' + res.status + '): ' + txt.slice(0, 100));
                }
                const data = await res.json();
                const md = data.mime_type.startsWith('video/')
                    ? `<video class="local-video" controls src="${data.url}" preload="metadata"></video>`
                    : `![](${data.url})`;
                this.replaceText(placeholder, md);
            } catch (e) {
                this.replaceText(placeholder + '\n', '');
                this.error = e.message || '上傳失敗';
            } finally {
                this.uploading--;
            }
        },
        insertAtCursor(text) {
            const ta = this.$refs.body;
            const start = ta.selectionStart ?? ta.value.length;
            const end = ta.selectionEnd ?? start;
            ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
            const pos = start + text.length;
            ta.setSelectionRange(pos, pos);
            ta.focus();
        },
        replaceText(from, to) {
            // String.replace 以字串為 pattern 時是字面取代第一個出現，無 regex 風險
            const ta = this.$refs.body;
            ta.value = ta.value.replace(from, to);
        },
    };
};

/**
 * Tweet composer: YouTube paste prompt + @ mention autocomplete.
 * Expects x-ref="body" (textarea) in scope.
 */
window.tweetComposer = function ({ locale = 'zh', tweetId = null } = {}) {
    return {
        ...youtubePasteBehavior,
        ...mentionBehavior,
        locale,
        mentionExcludeType: 'tweet',
        mentionExcludeId: tweetId,
        handlePaste(event) {
            this.detectYoutubePaste(event);
        },
    };
};

/**
 * Infinite scroll: when the sentinel comes into view, fetch the next page
 * and append the returned tweet items to a target list. The server response
 * is expected to be a wrapper div with `data-has-more` and `data-next-page`.
 */
window.infiniteScroll = function ({ url, startPage, hasMore, listSelector }) {
    return {
        page: startPage,
        hasMore: hasMore,
        loading: false,
        observer: null,
        setupObserver(sentinel) {
            if (!this.hasMore) return;
            this.observer = new IntersectionObserver(
                (entries) => {
                    if (entries[0].isIntersecting && !this.loading && this.hasMore) {
                        this.loadMore();
                    }
                },
                { rootMargin: '300px' },
            );
            this.observer.observe(sentinel);
        },
        async loadMore() {
            if (this.loading || !this.hasMore) return;
            this.loading = true;
            const nextPage = this.page + 1;
            try {
                const res = await fetch(url + '?page=' + nextPage + '&partial=1', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const html = (await res.text()).trim();

                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                const wrapper = tmp.firstElementChild;
                if (!wrapper) {
                    this.hasMore = false;
                    return;
                }

                const list = document.querySelector(listSelector);
                while (wrapper.firstChild) {
                    list.appendChild(wrapper.firstChild);
                }

                this.hasMore = wrapper.dataset.hasMore === '1';
                this.page = nextPage;
                if (!this.hasMore && this.observer) this.observer.disconnect();
            } catch (e) {
                console.error('infiniteScroll failed:', e);
            } finally {
                this.loading = false;
            }
        },
    };
};

/**
 * Tweets page "load older" (JYU-537). The button is a real link to the next
 * page (keeps filters), so it works without JS; with JS the next page's items
 * are appended in place.
 */
window.tweetList = function () {
    return {
        loading: false,
        error: false,
        async loadOlder(button) {
            if (this.loading || !button.href) return;
            this.loading = true;
            this.error = false;
            try {
                const url = new URL(button.href);
                url.searchParams.set('partial', '1');
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
                if (!res.ok) throw new Error(String(res.status));
                const tmp = document.createElement('div');
                tmp.innerHTML = (await res.text()).trim();
                const page = tmp.firstElementChild;
                if (!page) return;

                // Don't repeat the month heading when a month spans two pages.
                const shown = this.$refs.list.querySelectorAll('[data-month]');
                const lastMonth = shown[shown.length - 1]?.dataset.month;
                const firstHeading = page.querySelector('[data-month]');
                if (firstHeading && firstHeading.dataset.month === lastMonth) firstHeading.remove();

                this.$refs.list.firstElementChild.append(...page.childNodes);

                if (page.dataset.hasMore === '1' && page.dataset.nextUrl) {
                    const next = new URL(page.dataset.nextUrl);
                    next.searchParams.delete('partial');
                    button.href = next.toString();
                } else {
                    button.remove();
                }
            } catch (e) {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },
    };
};

/**
 * Shared fullscreen image lightbox. A single overlay lives in the public
 * layout; any image can open it via $store.lightbox.show(src, alt).
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('lightbox', {
        open: false,
        src: '',
        alt: '',
        show(src, alt = '') {
            this.src = src;
            this.alt = alt;
            this.open = true;
            document.documentElement.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            this.src = '';
            document.documentElement.style.overflow = '';
        },
    });
});

Alpine.start();
