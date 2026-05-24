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

Alpine.start();
