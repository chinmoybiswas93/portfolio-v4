// Small, dependency-free interactions for the public site. No cookies; preferences live in localStorage.

const root = document.documentElement;

// Theme: one toggle. A click saves light/dark in localStorage (no cookies); until then the device setting applies.
const systemLight = window.matchMedia('(prefers-color-scheme: light)');

function savedTheme() {
    try {
        const value = localStorage.getItem('theme');
        return value === 'light' || value === 'dark' ? value : null;
    } catch (e) {
        return null;
    }
}

function applyTheme(theme) {
    if (theme === 'light') root.setAttribute('data-theme', 'light');
    else root.removeAttribute('data-theme');
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', theme === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
    });
}

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        try {
            localStorage.setItem('theme', next);
        } catch (e) {}
        applyTheme(next);
    });
});
systemLight.addEventListener('change', () => {
    if (savedTheme() === null) applyTheme(systemLight.matches ? 'light' : 'dark');
});
applyTheme(root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');

// Page transitions: a thin top progress bar while the next page loads (View Transitions do the fade in CSS).
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || link.origin !== location.origin) return;
    if (link.pathname === location.pathname && link.hash) return;
    root.classList.add('is-navigating');
});
window.addEventListener('pageshow', () => root.classList.remove('is-navigating'));

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

// Post page: "Copy link" share button (clipboard only, nothing is sent anywhere).
document.querySelectorAll('[data-copy-link]').forEach((button) => {
    button.addEventListener('click', async () => {
        const status = document.querySelector('[data-copy-status]');
        try {
            await navigator.clipboard.writeText(button.dataset.copyLink);
            const label = button.querySelector('[data-copy-label]') ?? button;
            label.textContent = 'Copied';
            if (status) status.textContent = 'Link copied';
            setTimeout(() => (label.textContent = 'Copy link'), 1600);
        } catch (e) {
            window.prompt('Copy this link', button.dataset.copyLink);
        }
    });
});

// Post page: highlight the section being read in the "On this page" contents.
const tocLinks = [...document.querySelectorAll('[data-toc-link]')];
if (tocLinks.length) {
    const headings = tocLinks.map((link) => document.getElementById(link.dataset.tocLink)).filter(Boolean);
    let frame = null;
    const update = () => {
        frame = null;
        let current = headings[0]?.id;
        headings.forEach((heading) => {
            if (heading.getBoundingClientRect().top <= window.innerHeight * 0.25) current = heading.id;
        });
        tocLinks.forEach((link) => {
            if (link.dataset.tocLink === current) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    };
    window.addEventListener('scroll', () => { if (!frame) frame = requestAnimationFrame(update); }, { passive: true });
    update();
}

// Case study gallery lightbox: native <dialog> (focus handling and Esc built in), arrows and ← → keys.
const lightbox = document.querySelector('[data-lightbox]');
if (lightbox && typeof lightbox.showModal === 'function') {
    const slides = JSON.parse(lightbox.dataset.slides || '[]');
    const img = lightbox.querySelector('[data-lightbox-img]');
    const caption = lightbox.querySelector('[data-lightbox-caption]');
    const count = lightbox.querySelector('[data-lightbox-count]');
    let index = 0;
    let opener = null;
    const show = (i) => {
        index = (i + slides.length) % slides.length;
        img.src = slides[index].src;
        img.alt = slides[index].alt;
        caption.textContent = slides[index].alt;
        count.textContent = `${index + 1} / ${slides.length}`;
    };
    document.querySelectorAll('[data-lightbox-open]').forEach((button) => {
        button.addEventListener('click', () => {
            opener = button;
            show(Number(button.dataset.lightboxOpen));
            lightbox.showModal();
        });
    });
    lightbox.querySelector('[data-lightbox-close]').addEventListener('click', () => lightbox.close());
    lightbox.querySelector('[data-lightbox-prev]')?.addEventListener('click', () => show(index - 1));
    lightbox.querySelector('[data-lightbox-next]')?.addEventListener('click', () => show(index + 1));
    lightbox.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') show(index - 1);
        if (event.key === 'ArrowRight') show(index + 1);
    });
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) lightbox.close();
    });
    lightbox.addEventListener('close', () => opener?.focus());
}
