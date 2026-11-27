{{--
    Theme bootstrap — include as the FIRST thing in <head>, before any stylesheet.
    $theme comes from App\Support\ThemePreference (cookie set by the toggle).
      - chosen theme: already on <html data-theme> from the server → first paint is correct.
      - no choice: this inline script copies the OS setting onto <html> before CSS paints,
        and keeps following it if the OS switches while the page is open.
--}}
<meta name="color-scheme" content="{{ $theme ?? 'light dark' }}">
<script>
    (function () {
        var root = document.documentElement;
        if (root.hasAttribute('data-theme-choice')) return;
        var query = window.matchMedia('(prefers-color-scheme: dark)');
        var apply = function () {
            if (!root.hasAttribute('data-theme-choice')) {
                root.setAttribute('data-theme', query.matches ? 'dark' : 'light');
            }
        };
        apply();
        if (query.addEventListener) query.addEventListener('change', apply);
    })();
</script>
