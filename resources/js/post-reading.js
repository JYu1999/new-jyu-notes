/**
 * Post page reading tools (JYU-535):
 *  - readingProgress(): header progress bar
 *  - postReader(): scrollspy for the table of contents, "section N / M",
 *    text size (A− / A+, saved in localStorage), back to top, copy link
 *  - code blocks get a toolbar with the language and a "copy" button
 */

const FONT_SIZES = [16, 17, 19, 21, 23];
const DEFAULT_STEP = 2;
const FONT_KEY = 'post-font-step';
const HEADER_OFFSET = 96;

function articleBounds() {
    const body = document.querySelector('[data-post-body]');
    if (!body) return null;
    const rect = body.getBoundingClientRect();
    return { top: rect.top + window.scrollY, height: body.offsetHeight };
}

function copyText(text) {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
    }
    return legacyCopy(text);
}

// Fallback for non-secure contexts or when the Clipboard API is refused.
function legacyCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
    } finally {
        ta.remove();
    }
    return Promise.resolve();
}

window.readingProgress = function () {
    return {
        percent: 0,
        init() {
            const update = () => {
                const b = articleBounds();
                if (!b) return;
                const scrolled = window.scrollY + window.innerHeight - b.top;
                this.percent = Math.max(0, Math.min(100, Math.round((scrolled / b.height) * 100)));
            };
            update();
            window.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update, { passive: true });
        },
    };
};

window.postReader = function ({ headings }) {
    return {
        headings,
        active: headings[0] ?? null,
        sheet: false,
        step: DEFAULT_STEP,
        template: '',
        init() {
            try {
                const saved = parseInt(localStorage.getItem(FONT_KEY), 10);
                if (FONT_SIZES[saved]) this.step = saved;
            } catch (e) { /* storage unavailable */ }

            this.template = this.$root.querySelector('[data-progress-template]')?.dataset.progressTemplate ?? '';

            if (this.headings.length) {
                const onScroll = () => this.spy();
                window.addEventListener('scroll', onScroll, { passive: true });
                if (window.location.hash && this.headings.includes(window.location.hash.slice(1))) {
                    this.active = window.location.hash.slice(1);
                } else {
                    this.spy();
                }
            }
        },
        // The active section is the last heading above the reading line.
        spy() {
            let current = this.headings[0];
            for (const id of this.headings) {
                const el = document.getElementById(id);
                if (el && el.getBoundingClientRect().top - HEADER_OFFSET - 8 <= 0) current = id;
            }
            this.active = current;
        },
        get progressLabel() {
            if (!this.template || !this.headings.length) return '';
            const n = Math.max(1, this.headings.indexOf(this.active) + 1);
            return this.template.replace(':n', n).replace(':m', this.headings.length);
        },
        scrollTo(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const top = el.getBoundingClientRect().top + window.scrollY - HEADER_OFFSET;
            window.scrollTo({ top, behavior: 'smooth' });
            history.replaceState(null, '', '#' + id);
            this.active = id;
        },
        toTop() {
            const top = document.getElementById('post-top');
            window.scrollTo({ top: top ? Math.max(0, top.offsetTop - HEADER_OFFSET) : 0, behavior: 'smooth' });
            history.replaceState(null, '', window.location.pathname + window.location.search);
        },
        fontStep(delta) {
            this.step = Math.max(0, Math.min(FONT_SIZES.length - 1, this.step + delta));
            document.querySelector('[data-post-body]')?.style.setProperty('--post-font-size', FONT_SIZES[this.step] + 'px');
            try {
                localStorage.setItem(FONT_KEY, String(this.step));
            } catch (e) { /* storage unavailable */ }
        },
        copyLink(button) {
            const url = window.location.origin + window.location.pathname;
            const label = button.querySelector('[data-label]');
            const original = label?.textContent;
            copyText(url).then(() => {
                if (!label) return;
                label.textContent = button.dataset.doneLabel;
                setTimeout(() => { label.textContent = original; }, 2000);
            });
        },
    };
};

// Wrap each code block in a toolbar with its language and a copy button.
function enhanceCodeBlocks() {
    const body = document.querySelector('[data-post-body]');
    if (!body) return;
    const { copyLabel, copiedLabel, copyCodeLabel } = body.dataset;

    body.querySelectorAll('pre').forEach((pre) => {
        if (pre.parentElement?.classList.contains('code-block')) return;
        const code = pre.querySelector('code');
        const lang = (code?.className.match(/language-([\w+-]+)/) ?? [])[1] ?? '';

        const figure = document.createElement('figure');
        figure.className = 'code-block';
        const bar = document.createElement('figcaption');
        bar.className = 'code-block-bar';
        const name = document.createElement('span');
        name.textContent = lang;
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = copyLabel;
        button.setAttribute('aria-label', copyCodeLabel);
        button.addEventListener('click', () => {
            copyText((code ?? pre).innerText).then(() => {
                button.textContent = copiedLabel;
                setTimeout(() => { button.textContent = copyLabel; }, 2000);
            });
        });
        bar.append(name, button);

        pre.replaceWith(figure);
        figure.append(bar, pre);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceCodeBlocks);
} else {
    enhanceCodeBlocks();
}
