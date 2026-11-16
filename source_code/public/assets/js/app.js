/* Alarife reader runtime — navigation, links, outline and print. */
(function () {
    'use strict';

    function initNav() {
        document.querySelectorAll('[data-nav-toggle]').forEach(function (toggle) {
            var targetId = toggle.getAttribute('aria-controls');
            var nav = targetId ? document.getElementById(targetId) : null;
            if (!nav) return;

            toggle.addEventListener('click', function () {
                var open = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                    nav.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        });
    }

    function initCopy() {
        document.querySelectorAll('[data-copy-link]').forEach(function (button) {
            button.addEventListener('click', function () {
                var url = window.location.href;
                var done = function () {
                    var previous = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function () { button.textContent = previous; }, 1600);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copy this link', url); });
                } else {
                    window.prompt('Copy this link', url);
                }
            });
        });
    }

    function initPrint() {
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () { window.print(); });
        });
    }

    function initOutline() {
        var links = Array.prototype.slice.call(document.querySelectorAll('.toc a[href^="#"]'));
        if (!links.length || !('IntersectionObserver' in window)) return;

        var headings = links.map(function (link) {
            return document.getElementById(link.getAttribute('href').slice(1));
        }).filter(Boolean);

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                links.forEach(function (link) {
                    link.removeAttribute('aria-current');
                    if (link.getAttribute('href') === '#' + entry.target.id) {
                        link.setAttribute('aria-current', 'true');
                    }
                });
            });
        }, { rootMargin: '-20% 0px -70% 0px' });

        headings.forEach(function (heading) { observer.observe(heading); });
    }

    function initSlashFocus() {
        document.addEventListener('keydown', function (event) {
            if (event.key !== '/' || event.metaKey || event.ctrlKey) return;
            var tag = (event.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || event.target.isContentEditable) return;
            var input = document.querySelector('[data-search-input], .search-form input[type="search"]');
            if (input) {
                event.preventDefault();
                input.focus();
            }
        });
    }

    function init() {
        initNav();
        initCopy();
        initPrint();
        initOutline();
        initSlashFocus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
