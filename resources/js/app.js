import Alpine from 'alpinejs';
import { parseYoutubeUrl, youtubeShortcode } from './youtube';

window.Alpine = Alpine;

// Theme toggle (light/dark) — persists in cookie
function initTheme() {
    const stored = document.cookie
        .split('; ')
        .find((r) => r.startsWith('theme='))
        ?.split('=')[1];
    if (stored) {
        document.documentElement.setAttribute('data-theme', stored);
    }
}
initTheme();

window.toggleTheme = function () {
    const current = document.documentElement.getAttribute('data-theme') ?? 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.cookie = `theme=${next}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
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
    mentionQuery: '',
    mentionResults: [],
    mentionIndex: 0,
    mentionStart: -1,
    _mentionTimer: null,

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
        if (/\s/.test(query)) return this.closeMention(); // 出現空白即結束 mention

        this.mentionStart = at;
        this.mentionQuery = query;
        this.mentionActive = true;
        this.searchMentions(query);
    },

    searchMentions(q) {
        clearTimeout(this._mentionTimer);
        if (q === '') { this.mentionResults = []; return; }
        this._mentionTimer = setTimeout(async () => {
            if (!this.mentionActive) return;
            const params = new URLSearchParams({
                q,
                locale: this.mentionLocale(),
                exclude: this.postId ?? '',
            });
            try {
                const res = await fetch(`/admin/posts/search?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!res.ok) { this.mentionResults = []; return; }
                this.mentionResults = await res.json();
                this.mentionIndex = 0;
            } catch (e) {
                this.mentionResults = [];
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
        const link = `[${item.title}](${item.url})`;
        ta.value = ta.value.slice(0, this.mentionStart) + link + ta.value.slice(pos);
        const caret = this.mentionStart + link.length;
        ta.setSelectionRange(caret, caret);
        ta.focus();
        this.closeMention();
        ta.dispatchEvent(new Event('input')); // 讓 Alpine 同步 value
    },

    closeMention() {
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
 * Post TOC scrollspy.
 *
 * Watches all article headings via IntersectionObserver and exposes
 * `active` (current heading id) for the sidebar to highlight.
 * Provides `scrollTo(id)` that scrolls smoothly and updates URL hash.
 */
window.postToc = function () {
    return {
        active: null,
        observer: null,
        init() {
            // Collect all headings the TOC references.
            const ids = Array.from(document.querySelectorAll('[data-toc-id]'))
                .map((a) => a.getAttribute('data-toc-id'));
            const headings = ids
                .map((id) => document.getElementById(id))
                .filter(Boolean);
            if (!headings.length) return;

            this.observer = new IntersectionObserver(
                (entries) => {
                    // Pick the topmost entry that is currently intersecting; fall back to closest.
                    const visible = entries.filter((e) => e.isIntersecting);
                    if (visible.length > 0) {
                        // Sort by document position
                        visible.sort((a, b) =>
                            a.target.compareDocumentPosition(b.target) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
                        );
                        this.active = visible[0].target.id;
                    }
                },
                { rootMargin: '-80px 0px -70% 0px', threshold: 0 }
            );
            headings.forEach((h) => this.observer.observe(h));

            // Initialise from hash if present.
            if (window.location.hash) {
                this.active = window.location.hash.slice(1);
            } else if (headings[0]) {
                this.active = headings[0].id;
            }
        },
        scrollTo(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const top = el.getBoundingClientRect().top + window.scrollY - 80;
            window.scrollTo({ top, behavior: 'smooth' });
            history.replaceState(null, '', '#' + id);
            this.active = id;
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
