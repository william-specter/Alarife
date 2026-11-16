/* Alarife accessible carousel — progressive enhancement, no dependencies. */
(function () {
    'use strict';

    function Carousel(root) {
        this.root = root;
        this.slides = Array.prototype.slice.call(root.querySelectorAll('.carousel-slide'));
        this.dots = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-dot]'));
        this.prev = root.querySelector('[data-carousel-prev]');
        this.next = root.querySelector('[data-carousel-next]');
        this.pauseButton = root.querySelector('[data-carousel-pause]');
        this.index = 0;
        this.autoplay = root.getAttribute('data-autoplay') === 'true';
        this.paused = false;
        this.timer = null;
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.live = null;
    }

    Carousel.prototype.init = function () {
        if (this.slides.length < 2) return;

        var self = this;

        this.live = document.createElement('p');
        this.live.setAttribute('aria-live', 'polite');
        this.live.className = 'sr-only';
        this.root.appendChild(this.live);

        if (this.prev) {
            this.prev.addEventListener('click', function () { self.go(self.index - 1, true); });
        }
        if (this.next) {
            this.next.addEventListener('click', function () { self.go(self.index + 1, true); });
        }
        this.dots.forEach(function (dot, index) {
            dot.addEventListener('click', function () { self.go(index, true); });
        });

        this.root.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') { self.go(self.index - 1, true); event.preventDefault(); }
            if (event.key === 'ArrowRight') { self.go(self.index + 1, true); event.preventDefault(); }
        });

        if (this.autoplay && !this.reduceMotion) {
            this.start();
            this.root.addEventListener('mouseenter', function () { self.stop(); });
            this.root.addEventListener('mouseleave', function () { if (!self.paused) self.start(); });
            this.root.addEventListener('focusin', function () { self.stop(); });
            this.root.addEventListener('focusout', function () { if (!self.paused) self.start(); });
        }

        if (this.pauseButton) {
            if (this.reduceMotion) {
                this.pauseButton.hidden = true;
            }
            this.pauseButton.addEventListener('click', function () {
                self.paused = !self.paused;
                self.pauseButton.textContent = self.paused ? 'Play' : 'Pause';
                if (self.paused) { self.stop(); } else { self.start(); }
            });
        }

        var startX = null;
        this.root.addEventListener('touchstart', function (event) {
            startX = event.touches[0].clientX;
        }, { passive: true });
        this.root.addEventListener('touchend', function (event) {
            if (startX === null) return;
            var delta = event.changedTouches[0].clientX - startX;
            if (Math.abs(delta) > 40) {
                self.go(self.index + (delta < 0 ? 1 : -1), true);
            }
            startX = null;
        }, { passive: true });
    };

    Carousel.prototype.start = function () {
        var self = this;
        this.stop();
        this.timer = window.setInterval(function () { self.go(self.index + 1, false); }, 6500);
    };

    Carousel.prototype.stop = function () {
        if (this.timer) { window.clearInterval(this.timer); this.timer = null; }
    };

    Carousel.prototype.go = function (index, announce) {
        var count = this.slides.length;
        this.index = ((index % count) + count) % count;

        this.slides.forEach(function (slide, i) {
            var active = i === this.index;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        }, this);

        this.dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === this.index);
            if (i === this.index) { dot.setAttribute('aria-current', 'true'); } else { dot.removeAttribute('aria-current'); }
        }, this);

        if (announce && this.live) {
            this.live.textContent = 'Cover ' + (this.index + 1) + ' of ' + count;
        }
    };

    function init() {
        document.querySelectorAll('[data-carousel]').forEach(function (root) {
            new Carousel(root).init();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
