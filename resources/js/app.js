// Small, dependency-free interactions for the public site. No cookies; preferences live in localStorage.

const root = document.documentElement;

// Theme toggle (dark is the default).
function setTheme(theme) {
    if (theme === 'light') {
        root.setAttribute('data-theme', 'light');
    } else {
        root.removeAttribute('data-theme');
    }
    try {
        localStorage.setItem('theme', theme);
    } catch (e) {}
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const isLight = theme === 'light';
        button.setAttribute('aria-pressed', String(isLight));
        button.setAttribute('aria-label', isLight ? 'Switch to dark theme' : 'Switch to light theme');
    });
}

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => setTheme(root.getAttribute('data-theme') === 'light' ? 'dark' : 'light'));
});
setTheme(root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');

// Mouse-following spotlight: only for fine pointers and when motion is welcome.
const spotlight = document.querySelector('.spotlight');
const allowMotion = window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
if (spotlight && allowMotion.matches) {
    let frame = null;
    window.addEventListener(
        'pointermove',
        (event) => {
            if (frame) return;
            frame = requestAnimationFrame(() => {
                frame = null;
                spotlight.style.setProperty('--spot-x', `${event.clientX}px`);
                spotlight.style.setProperty('--spot-y', `${event.clientY}px`);
            });
        },
        { passive: true },
    );
}

// Home section nav: the section whose top has passed 30% of the viewport is active; the last one at the bottom.
const navLinks = [...document.querySelectorAll('[data-section-link]')];
if (navLinks.length) {
    const sections = navLinks.map((link) => document.getElementById(link.dataset.sectionLink)).filter(Boolean);
    let frame = null;
    const update = () => {
        frame = null;
        const line = window.innerHeight * 0.3;
        const scroller = document.scrollingElement || root;
        let current = sections[0]?.id;
        if (scroller.scrollTop + window.innerHeight >= scroller.scrollHeight - 4) {
            current = sections[sections.length - 1]?.id;
        } else {
            sections.forEach((section) => {
                if (section.getBoundingClientRect().top <= line) current = section.id;
            });
        }
        navLinks.forEach((link) => {
            if (link.dataset.sectionLink === current) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
    };
    const schedule = () => {
        if (!frame) frame = requestAnimationFrame(update);
    };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    update();
}

// Code blocks in posts: wrap each <pre> with a bar showing the language and a copy button.
document.querySelectorAll('.prose pre').forEach((pre) => {
    const wrapper = document.createElement('figure');
    wrapper.className = 'code-block';
    const bar = document.createElement('div');
    bar.className = 'code-block-bar';
    const label = document.createElement('figcaption');
    label.textContent = pre.dataset.lang || 'code';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-btn';
    button.textContent = 'Copy';
    button.setAttribute('aria-label', 'Copy code');
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(pre.innerText);
            button.textContent = 'Copied';
        } catch (e) {
            button.textContent = 'Press Ctrl+C';
        }
        setTimeout(() => (button.textContent = 'Copy'), 2000);
    });
    bar.append(label, button);
    pre.replaceWith(wrapper);
    wrapper.append(bar, pre);
});
