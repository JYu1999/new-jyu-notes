import Alpine from 'alpinejs';
import { parseYoutubeUrl, youtubeShortcode } from './youtube';
import { applyCommand, filterCommands, slashQueryAt } from './slash-commands';
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
        // Uploads in flight with their progress 0–100 (JYU-551).
        pending: [],
        error: null,
        get mediaBase() {
            return document.querySelector('meta[name=media-base]')?.content ?? '';
        },
        get full() {
            return this.items.length + this.uploading >= max;
        },
        url(item) {
            return this.mediaBase + '/' + item.path;
        },
        async add(fileList) {
            this.error = null;
            const incoming = Array.from(fileList);
            const files = incoming.slice(0, Math.max(0, max - this.items.length - this.uploading));
            if (incoming.length > files.length) {
                this.error = `最多 ${max} 個媒體`;
            }
            await Promise.all(files.map((f) => this.uploadOne(f)));
        },
        // XHR instead of fetch so the upload progress can be shown.
        postFile(file, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/admin/media');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100)); };
                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(new Error('上傳失敗')); }
                    } else {
                        reject(new Error('上傳失敗 (' + xhr.status + '): ' + String(xhr.responseText).slice(0, 100)));
                    }
                };
                xhr.onerror = () => reject(new Error('上傳失敗，請檢查網路'));
                const fd = new FormData();
                fd.append('file', file);
                xhr.send(fd);
            });
        },
        async uploadOne(file) {
            this.uploading++;
            const job = { id: Math.random().toString(36).slice(2), name: file.name, progress: 0 };
            this.pending.push(job);
            try {
                const data = await this.postFile(file, (p) => {
                    const j = this.pending.find((x) => x.id === job.id);
                    if (j) j.progress = p;
                });
                this.items.push({
                    path: data.path,
                    type: data.mime_type.startsWith('video/') ? 'video' : 'image',
                    alt: '',
                    sensitive: false,
                });
                this.$dispatch?.('media-changed');
            } catch (e) {
                this.error = e.message || '上傳失敗';
            } finally {
                this.pending = this.pending.filter((x) => x.id !== job.id);
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
            // startText is editable in the prompt (JYU-548): "84", "1:24" or "1:02:03".
            const start = parsed.start || 0;
            const startText = start ? `${Math.floor(start / 60)}:${String(start % 60).padStart(2, '0')}` : '';
            this.ytPrompt = { url: text.trim(), ...parsed, startText };
        }, 0);
    },
    embedYoutube() {
        if (!this.ytPrompt) return;
        const ta = this.$refs.body;
        const idx = ta.value.lastIndexOf(this.ytPrompt.url);
        if (idx !== -1) {
            const parts = String(this.ytPrompt.startText ?? '').trim().split(':').filter(Boolean).map(Number);
            const start = parts.length && parts.every((n) => Number.isFinite(n) && n >= 0)
                ? parts.reduce((acc, n) => acc * 60 + n, 0)
                : 0;
            const code = youtubeShortcode({ id: this.ytPrompt.id, start: Math.floor(start) });
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
 * "/" command menu for the post editor (JYU-552). Spread into markdownMediaInsert;
 * expects x-ref="body" and the pick() / mention helpers from that component.
 */
/** Pixel offset of the caret's line bottom inside a textarea, via a mirror element. */
function caretBottom(ta) {
    const style = getComputedStyle(ta);
    const mirror = document.createElement('div');
    ['font', 'letterSpacing', 'lineHeight', 'padding', 'border', 'boxSizing', 'whiteSpace', 'wordWrap', 'overflowWrap', 'tabSize']
        .forEach((p) => { mirror.style[p] = style[p]; });
    Object.assign(mirror.style, { position: 'absolute', visibility: 'hidden', whiteSpace: 'pre-wrap', width: `${ta.clientWidth}px` });
    mirror.textContent = ta.value.slice(0, ta.selectionStart);
    const marker = document.createElement('span');
    marker.textContent = '​';
    mirror.appendChild(marker);
    document.body.appendChild(mirror);
    const bottom = marker.offsetTop + marker.offsetHeight;
    mirror.remove();
    return ta.offsetTop + bottom - ta.scrollTop;
}

const slashCommandBehavior = {
    slashTop: 0,
    slashActive: false,
    slashStart: -1,
    slashQuery: '',
    slashIndex: 0,
    // A plain array (not a getter): this object is spread into components.
    slashResults: [],
    detectSlash() {
        const ta = this.$refs.body;
        const found = ta.selectionStart === ta.selectionEnd ? slashQueryAt(ta.value, ta.selectionStart) : null;
        if (!found) return this.closeSlash();
        if (!this.slashActive || found.query !== this.slashQuery) this.slashIndex = 0;
        this.slashActive = true;
        this.slashStart = found.start;
        this.slashQuery = found.query;
        this.slashResults = filterCommands(found.query);
        this.slashTop = caretBottom(ta);
    },
    closeSlash() {
        this.slashActive = false;
        this.slashQuery = '';
        this.slashStart = -1;
        this.slashIndex = 0;
        this.slashResults = [];
    },
    /** Returns true when the key was handled by the menu. */
    onSlashKeydown(e) {
        if (!this.slashActive) return false;
        const n = this.slashResults.length;
        if (e.key === 'Escape') {
            e.preventDefault();
            this.closeSlash();
            return true;
        }
        if (!n) return false;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.slashIndex = (this.slashIndex + 1) % n;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.slashIndex = (this.slashIndex - 1 + n) % n;
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            this.runSlash(this.slashResults[this.slashIndex]);
        } else {
            return false;
        }
        return true;
    },
    runSlash(cmd) {
        if (!cmd) return;
        const ta = this.$refs.body;
        const { value, caret } = applyCommand(cmd.id, ta.value, this.slashStart, ta.selectionStart);
        this.closeSlash();
        ta.value = value;
        ta.setSelectionRange(caret, caret);
        ta.focus();

        if (cmd.id === 'image') {
            this.pick();
        } else if (cmd.id === 'youtube') {
            const url = window.prompt('貼上 YouTube 網址');
            const parsed = url ? parseYoutubeUrl(url) : null;
            if (parsed) {
                const code = youtubeShortcode({ id: parsed.id, start: parsed.start || 0 }) + '\n';
                ta.setRangeText(code, caret, caret, 'end');
            }
            ta.focus();
        } else if (cmd.id === 'mention') {
            ta.setRangeText('@', caret, caret, 'end');
        }
        // Let the editor see the change (autosave, counts) and open @ suggestions.
        ta.dispatchEvent(new Event('input', { bubbles: true }));
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
        ...slashCommandBehavior,
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
 * Full-screen tweet composer (JYU-551): tag chips, sensitive switch and draft
 * autosave. The first autosave creates a draft; from then on the form updates it,
 * so 發布 publishes that same tweet.
 */
window.tweetCompose = function ({ autosaveUrl, tags = [] }) {
    return {
        tags,
        selected: [],
        tagsOpen: false,
        sensitive: false,
        tweetId: null,
        saveState: 'idle',
        savedAt: null,
        submitting: false,
        timer: null,
        tick: 0,
        init() {
            setInterval(() => { this.tick++; }, 15000);
            window.addEventListener('beforeunload', (e) => {
                if (!this.submitting && (this.saveState === 'dirty' || this.saveState === 'saving')) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },
        get selectedTags() {
            return this.selected.map((id) => this.tags.find((t) => t.id === id)).filter(Boolean);
        },
        toggleTag(id) {
            this.selected = this.selected.includes(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
            this.$nextTick(() => this.changed());
        },
        get saveLabel() {
            this.tick;
            switch (this.saveState) {
                case 'saved': return `草稿已自動儲存 · ${relativeTime(this.savedAt)}`;
                case 'saving': return '儲存中…';
                case 'error': return '自動儲存失敗';
                case 'dirty': return '有變更，稍後自動儲存';
                default: return '尚未儲存';
            }
        },
        changed() {
            if (this.submitting) return;
            this.saveState = 'dirty';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.autosave(), 3000);
        },
        async autosave() {
            if (this.submitting) return;
            const fd = new FormData(this.$root);
            ['_method', 'status', 'redirect_to', 'tags_sent'].forEach((k) => fd.delete(k));
            if (this.tweetId) fd.set('tweet_id', this.tweetId);
            this.saveState = 'saving';
            try {
                const res = await fetch(autosaveUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: fd,
                });
                if (res.status === 422 || res.status === 409) { this.saveState = 'idle'; return; }
                if (!res.ok) throw new Error(String(res.status));
                const data = await res.json();
                if (!this.tweetId) {
                    this.tweetId = data.id;
                    this.$root.action = data.update_url;
                    const m = document.createElement('input');
                    m.type = 'hidden'; m.name = '_method'; m.value = 'PUT';
                    this.$root.prepend(m);
                }
                this.savedAt = new Date();
                this.saveState = 'saved';
            } catch (e) {
                this.saveState = 'error';
            }
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
 * ⌘K search panel (JYU-538). Opens on the `open-search` event (⌘K / Ctrl+K) and on
 * clicks of [data-search-trigger] links; results come from /{locale}/search/suggest.
 */
const RECENT_KEY = 'search-recent';
window.searchPanel = function ({ url, pageUrl, noResults, found, labels }) {
    return {
        open: false,
        q: '',
        scope: 'locale',
        type: 'all',
        results: [],
        counts: { all: 0, post: 0, tweet: 0 },
        active: 0,
        loading: false,
        searched: false,
        recent: [],
        noResults,
        found,
        returnFocus: null,
        controller: null,
        tabs: ['all', 'post', 'tweet'].map((key) => ({ key, label: labels[key] })),
        init() {
            try {
                this.recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]').slice(0, 5);
            } catch (e) { this.recent = []; }

            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-search-trigger]');
                if (!trigger || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                e.preventDefault();
                this.show();
            });
        },
        get filtered() {
            return this.type === 'all' ? this.results : this.results.filter((r) => r.type === this.type);
        },
        show() {
            if (this.open) return;
            this.returnFocus = document.activeElement;
            this.open = true;
            document.documentElement.style.overflow = 'hidden';
            // x-show + x-transition reveal the panel on the next frame; focus once it's visible.
            const focusInput = (tries = 10) => {
                const input = this.$refs.input;
                input.focus();
                if (document.activeElement !== input && tries > 0) requestAnimationFrame(() => focusInput(tries - 1));
            };
            this.$nextTick(() => requestAnimationFrame(() => focusInput()));
        },
        close() {
            if (!this.open) return;
            this.open = false;
            document.documentElement.style.overflow = '';
            this.returnFocus?.focus?.();
        },
        async search() {
            const q = this.q.trim();
            this.active = 0;
            if (!q) {
                this.results = [];
                this.counts = { all: 0, post: 0, tweet: 0 };
                this.searched = false;
                return;
            }
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            try {
                const params = new URLSearchParams({ q });
                if (this.scope === 'all') params.set('scope', 'all');
                const res = await fetch(`${url}?${params}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: this.controller.signal,
                });
                if (!res.ok) throw new Error(String(res.status));
                const data = await res.json();
                if (q !== this.q.trim()) return; // a newer query is on its way
                this.results = data.results;
                this.counts = data.counts;
                this.searched = true;
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.results = [];
                    this.searched = true;
                }
            } finally {
                this.loading = false;
            }
        },
        toggleScope() {
            this.scope = this.scope === 'all' ? 'locale' : 'all';
            this.search();
            this.$refs.input.focus();
        },
        remember() {
            const q = this.q.trim();
            if (!q) return;
            this.recent = [q, ...this.recent.filter((r) => r !== q)].slice(0, 5);
            try { localStorage.setItem(RECENT_KEY, JSON.stringify(this.recent)); } catch (e) { /* ignore */ }
        },
        clearRecent() {
            this.recent = [];
            try { localStorage.removeItem(RECENT_KEY); } catch (e) { /* ignore */ }
        },
        onKey(e) {
            const list = this.filtered;
            if (e.key === 'Escape') {
                e.preventDefault();
                this.close();
            } else if (e.key === 'ArrowDown' && list.length) {
                e.preventDefault();
                this.active = (this.active + 1) % list.length;
                this.scrollActive();
            } else if (e.key === 'ArrowUp' && list.length) {
                e.preventDefault();
                this.active = (this.active - 1 + list.length) % list.length;
                this.scrollActive();
            } else if (e.key === 'Enter' && e.target === this.$refs.input) {
                e.preventDefault();
                const hit = list[this.active];
                this.remember();
                if (hit) {
                    window.location.href = hit.url;
                } else if (this.q.trim()) {
                    window.location.href = `${pageUrl}?q=${encodeURIComponent(this.q.trim())}`;
                }
            } else if (e.key === 'Tab') {
                e.preventDefault();
                const keys = this.tabs.map((t) => t.key);
                const i = keys.indexOf(this.type);
                this.type = keys[(i + (e.shiftKey ? keys.length - 1 : 1)) % keys.length];
                this.active = 0;
            }
        },
        scrollActive() {
            this.$nextTick(() => document.getElementById('sp-opt-' + this.active)?.scrollIntoView({ block: 'nearest' }));
        },
    };
};

/**
 * Focus-mode post editor (JYU-548): autosave, write / split / preview modes,
 * format toolbar, live counts and keyboard shortcuts. The body textarea keeps
 * its own markdownMediaInsert scope (uploads, @ mentions, YouTube prompt).
 */
const CJK = /[぀-ヿ㐀-鿿豈-﫿가-힯]/g;
const READING = { zh: ['char', 400], ja: ['char', 500], en: ['word', 230], vi: ['word', 230], id: ['word', 230] };
const INTERNAL_LINK = /\]\((?:https?:\/\/[^)\/\s]+)?\/(?:zh|en|ja|vi|id)\/(?:posts|tweets)\/([^)\s]+)\)/g;

function relativeTime(date) {
    if (!date) return '';
    const s = Math.round((Date.now() - date.getTime()) / 1000);
    if (s < 45) return '剛剛';
    if (s < 3600) return `${Math.round(s / 60)} 分鐘前`;
    return date.toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' });
}

window.postEditor = function ({ postId, status, locale, publishWhen = 'now', autosaveUrl, previewUrl }) {
    return {
        postId,
        status,
        locale,
        publishWhen,
        mode: 'write',
        panel: window.innerWidth >= 1024,
        saveState: postId ? 'saved' : 'idle',
        savedAt: null,
        submitting: false,
        timer: null,
        previewTimer: null,
        previewHtml: '',
        previewLoading: false,
        title: '',
        refCount: 0,
        statsLabel: '',
        tick: 0,
        init() {
            this.readFields();
            setInterval(() => { this.tick++; }, 15000);
            this.$root.addEventListener('submit', () => { this.submitting = true; });
            window.addEventListener('beforeunload', (e) => {
                if (!this.submitting && (this.saveState === 'dirty' || this.saveState === 'saving')) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },
        field(name) {
            return this.$root.elements.namedItem(name);
        },
        readFields() {
            this.title = this.field('title')?.value ?? '';
            this.updateStats(this.field('body')?.value ?? '');
        },
        get saveLabel() {
            this.tick; // re-evaluate relative time periodically
            switch (this.saveState) {
                case 'saved': return this.savedAt ? `已自動儲存 · ${relativeTime(this.savedAt)}` : '已儲存';
                case 'saving': return '儲存中…';
                case 'error': return '自動儲存失敗，請按 ⌘S';
                case 'dirty': return this.status === 'draft' ? '有變更，稍後自動儲存' : '有未儲存的變更 · ⌘S 儲存';
                default: return '尚未儲存';
            }
        },
        changed() {
            this.readFields();
            this.saveState = 'dirty';
            clearTimeout(this.timer);
            // Only drafts autosave: saving a published post would put changes live.
            if (this.status === 'draft') this.timer = setTimeout(() => this.autosave(), 3000);
            if (this.mode !== 'write') this.schedulePreview();
        },
        async autosave() {
            if (this.submitting || this.status !== 'draft') return;
            const fd = new FormData(this.$root);
            fd.delete('_method');
            fd.delete('status');
            if (this.postId) fd.set('post_id', this.postId);
            this.saveState = 'saving';
            try {
                const res = await fetch(autosaveUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: fd,
                });
                if (res.status === 409 || res.status === 422) { this.saveState = 'dirty'; return; }
                if (!res.ok) throw new Error(String(res.status));
                const data = await res.json();
                if (!this.postId) {
                    // First save of a new post: from now on this form updates it.
                    this.postId = data.id;
                    this.$root.action = data.update_url;
                    const m = document.createElement('input');
                    m.type = 'hidden'; m.name = '_method'; m.value = 'PUT';
                    this.$root.prepend(m);
                    history.replaceState(null, '', data.edit_url);
                }
                const slug = this.field('slug');
                if (slug && !slug.value) slug.value = data.slug;
                this.savedAt = new Date();
                this.saveState = 'saved';
            } catch (e) {
                this.saveState = 'error';
            }
        },
        save() {
            clearTimeout(this.timer);
            this.saveState = 'saving';
            this.$root.requestSubmit();
        },
        get publishLabel() {
            if (this.publishWhen === 'schedule') return '排程';
            return this.status === 'published' ? '更新' : '發布';
        },
        setStatus(value) {
            this.status = value;
            const sel = this.field('status');
            if (sel) sel.value = value;
        },
        publishClicked(event) {
            // Scheduling (JYU-549): the post stays a draft until the scheduled time.
            if (this.publishWhen === 'schedule') {
                const at = this.$refs.scheduledAt;
                if (at && !at.value) {
                    event.preventDefault();
                    this.chooseSchedule();
                    return;
                }
                this.setStatus('draft');
                return;
            }
            if (this.status !== 'published') this.setStatus('published');
        },
        chooseSchedule() {
            this.publishWhen = 'schedule';
            this.panel = true;
            this.$nextTick(() => {
                const at = this.$refs.scheduledAt;
                if (!at) return;
                at.focus();
                try { at.showPicker?.(); } catch (e) { /* not allowed without a user gesture */ }
            });
        },
        hideAndSave() {
            this.publishWhen = 'now';
            this.setStatus('hidden');
            this.$nextTick(() => this.save());
        },
        setMode(mode) {
            this.mode = mode;
            if (mode !== 'write') this.loadPreview();
        },
        togglePreview() {
            this.setMode(this.mode === 'preview' ? 'write' : 'preview');
        },
        schedulePreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => this.loadPreview(), 500);
        },
        async loadPreview() {
            this.previewLoading = true;
            try {
                const res = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ body: this.field('body')?.value ?? '' }),
                });
                if (res.ok) this.previewHtml = (await res.json()).html;
            } finally {
                this.previewLoading = false;
            }
        },
        updateStats(body) {
            const text = body
                .replace(/```[\s\S]*?```/g, ' ')
                .replace(/\{\{<.*?>\}\}/g, ' ')
                .replace(/!\[[^\]]*\]\([^)]*\)/g, ' ')
                .replace(/\]\([^)]*\)/g, ']')
                .replace(/<[^>]+>/g, ' ');
            const cjk = (text.match(CJK) || []).length;
            const words = (text.replace(CJK, ' ').match(/[A-Za-z0-9À-ɏḀ-ỿ]+(?:['’-][A-Za-z0-9À-ɏ]+)*/g) || []).length;
            const [kind, speed] = READING[this.currentLocale()] ?? READING.zh;
            // Each CJK character and each Latin word counts as one unit.
            const units = cjk + words;
            const minutes = Math.max(1, Math.ceil(units / speed));
            this.statsLabel = `${units.toLocaleString()} 字 · 約 ${minutes} 分鐘${kind === 'char' ? '（中文以字數計）' : ''}`;
            this.refCount = new Set([...body.matchAll(INTERNAL_LINK)].map((m) => m[1])).size;
        },
        currentLocale() {
            return this.field('locale')?.value ?? this.locale;
        },
        format(kind, ta) {
            const { selectionStart: s, selectionEnd: e, value } = ta;
            const sel = value.slice(s, e);
            const lineStart = value.lastIndexOf('\n', s - 1) + 1;
            let next = value;
            let caretStart = s;
            let caretEnd = e;
            const wrap = (left, right, placeholder) => {
                const inner = sel || placeholder;
                next = value.slice(0, s) + left + inner + right + value.slice(e);
                caretStart = s + left.length;
                caretEnd = caretStart + inner.length;
            };
            const prefixLines = (prefix) => {
                const end = e > s && value[e - 1] === '\n' ? e - 1 : e;
                const block = value.slice(lineStart, end);
                const lines = block.split('\n');
                const all = lines.every((l) => l.startsWith(prefix));
                const changed = lines.map((l) => (all ? l.slice(prefix.length) : prefix + l.replace(/^#+\s|^>\s/, ''))).join('\n');
                next = value.slice(0, lineStart) + changed + value.slice(end);
                caretStart = lineStart;
                caretEnd = lineStart + changed.length;
            };
            switch (kind) {
                case 'bold': wrap('**', '**', '粗體文字'); break;
                case 'italic': wrap('_', '_', '斜體文字'); break;
                case 'heading': prefixLines('## '); break;
                case 'quote': prefixLines('> '); break;
                case 'code':
                    if (sel.includes('\n')) wrap('```\n', '\n```', '');
                    else wrap('`', '`', 'code');
                    break;
                case 'mention': {
                    const before = s > 0 && !/\s/.test(value[s - 1]) ? ' @' : '@';
                    next = value.slice(0, s) + before + value.slice(e);
                    caretStart = caretEnd = s + before.length;
                    break;
                }
            }
            ta.value = next;
            ta.focus();
            ta.setSelectionRange(caretStart, caretEnd);
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
};

/**
 * Featured order (JYU-547): drag rows of [data-group] (or ↑ / ↓) and save right away.
 * After every change the numbers and the "not on the home page" divider are recomputed.
 */
window.featuredSort = function ({ url, ids, onHome = 3 }) {
    return {
        ids,
        dragging: null,
        status: '',
        rows() {
            return Array.from(this.$refs.list.querySelectorAll(':scope > li[data-group]'));
        },
        dragStart(e) {
            this.dragging = e.currentTarget;
            e.dataTransfer.effectAllowed = 'move';
            e.currentTarget.classList.add('opacity-50');
        },
        dragOver(e) {
            const over = e.currentTarget;
            if (!this.dragging || over === this.dragging) return;
            const rect = over.getBoundingClientRect();
            const after = e.clientY > rect.top + rect.height / 2;
            over.parentNode.insertBefore(this.dragging, after ? over.nextSibling : over);
        },
        dragEnd() {
            this.dragging?.classList.remove('opacity-50');
            this.dragging = null;
        },
        drop() {
            this.dragEnd();
            this.save();
        },
        move(li, delta) {
            const rows = this.rows();
            const i = rows.indexOf(li);
            const j = i + delta;
            if (j < 0 || j >= rows.length) return;
            delta < 0 ? li.parentNode.insertBefore(li, rows[j]) : li.parentNode.insertBefore(rows[j], li);
            this.save();
            li.querySelector(delta < 0 ? '[aria-label^="上移"]' : '[aria-label^="下移"]')?.focus();
        },
        refresh() {
            const rows = this.rows();
            rows.forEach((li, i) => {
                li.querySelector('[data-position]').textContent = i + 1;
                const divider = li.querySelector('[data-divider]');
                const show = i === onHome - 1 && rows.length > onHome;
                divider.classList.toggle('hidden', !show);
                divider.classList.toggle('flex', show);
            });
        },
        async save() {
            this.refresh();
            const groupIds = this.rows().map((li) => Number(li.dataset.group));
            if (JSON.stringify(groupIds) === JSON.stringify(this.ids)) return;
            this.status = 'saving';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ group_ids: groupIds }),
                });
                if (!res.ok) throw new Error(String(res.status));
                this.ids = groupIds;
                this.status = 'saved';
            } catch (e) {
                this.status = 'error';
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
