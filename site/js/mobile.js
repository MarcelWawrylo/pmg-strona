/* PMG — efekty wersji mobilnej (≤ 960 px). Lustro js/v2.js: tam desktop z GSAP i Lenis, tu telefon i tablet bez bibliotek.
   Działa tylko ≤ 960 px i bez prefers-reduced-motion; wtedy <html> dostaje klasę mobile-fx, a style.css włącza animacje.
   Tylko transform/opacity/color; przewijanie przez CSS animation-timeline (progressive enhancement) albo IntersectionObserver,
   bez nasłuchu scroll. Menu mobilne jest w main.js (initNav), bo działa także przy ograniczonym ruchu. */
(function () {
  'use strict';

  var mobile = window.matchMedia('(max-width: 960px)');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (!mobile.matches || reduce.matches) return;
  document.documentElement.classList.add('mobile-fx');
})();
