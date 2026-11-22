// Runs before first paint (blocking, external file so the strict CSP needs no inline script).
// Preference: "light" or "dark" when chosen (localStorage), otherwise follow the device (prefers-color-scheme).
(function () {
    var root = document.documentElement;
    var pref = 'system';
    try {
        var saved = localStorage.getItem('theme');
        if (saved === 'light' || saved === 'dark') pref = saved;
    } catch (e) {}
    var light = pref === 'light' || (pref === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches);
    if (light) root.setAttribute('data-theme', 'light');
    root.setAttribute('data-theme-pref', pref);
})();
