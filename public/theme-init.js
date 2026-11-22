// Runs before first paint (blocking, external file so the strict CSP needs no inline script).
// The saved choice (localStorage "theme" = light|dark) wins; otherwise follow the device (prefers-color-scheme).
(function () {
    var saved = null;
    try {
        saved = localStorage.getItem('theme');
    } catch (e) {}
    var light = saved === 'light' || (saved !== 'dark' && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches);
    if (light) document.documentElement.setAttribute('data-theme', 'light');
})();
