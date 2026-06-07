/**
 * Parse a pasted string that is exactly one YouTube URL.
 * Supports youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID
 * (optionally with www. / m. host prefix and extra query params like si=).
 * Returns { id, start } or null. `start` is the t= timestamp in total
 * seconds (0 when absent or unparseable).
 */
export function parseYoutubeUrl(text) {
    const trimmed = (text ?? '').trim();
    if (!trimmed || /\s/.test(trimmed)) return null; // 夾在長文字中的 URL 不算

    let url;
    try {
        url = new URL(trimmed);
    } catch {
        return null;
    }
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;

    const host = url.hostname.replace(/^(www\.|m\.)/, '');
    let id = null;
    if (host === 'youtu.be') {
        id = url.pathname.slice(1).split('/')[0] || null;
    } else if (host === 'youtube.com') {
        if (url.pathname === '/watch') {
            id = url.searchParams.get('v');
        } else if (url.pathname.startsWith('/shorts/')) {
            id = url.pathname.split('/')[2] || null;
        }
    }
    if (!id || !/^[A-Za-z0-9_-]{11}$/.test(id)) return null;

    return { id, start: parseTimestamp(url.searchParams.get('t')) };
}

// "90" / "90s" / "2m5s" / "1h2m3s" → 總秒數；無法解析 → 0
function parseTimestamp(t) {
    if (!t) return 0;
    if (/^\d+s?$/.test(t)) return parseInt(t, 10);
    const m = t.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
    if (!m) return 0;
    return (+(m[1] ?? 0)) * 3600 + (+(m[2] ?? 0)) * 60 + (+(m[3] ?? 0));
}

/** Build the Hugo-style shortcode stored in markdown. */
export function youtubeShortcode({ id, start }) {
    return start > 0
        ? `{{< youtube id="${id}" start="${start}" >}}`
        : `{{< youtube id="${id}" >}}`;
}
