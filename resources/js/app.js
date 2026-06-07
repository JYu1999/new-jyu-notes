import Alpine from 'alpinejs';

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
        items: (initial || []).map((m) => ({ path: m.path, type: m.type, alt: m.alt ?? '' })),
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
 * Markdown textarea media insert: toolbar button + drag-drop + paste.
 * Uploads to /admin/media, inserts markdown (image) or <video> tag at cursor.
 * Expects x-ref="body" (textarea) and x-ref="file" (hidden file input) in scope.
 */
window.markdownMediaInsert = function () {
    return {
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
            if (!files.length) return; // 一般文字貼上不攔截
            event.preventDefault();
            this.handleFiles(files);
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

Alpine.start();
