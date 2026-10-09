/* PMG — efekty wersji mobilnej (≤ 960 px). Lustro js/v2.js: tam desktop z GSAP i Lenis, tu telefon i tablet bez bibliotek.
   Działa tylko ≤ 960 px i bez prefers-reduced-motion; wtedy <html> dostaje klasę mobile-fx, a style.css włącza animacje.
   Tylko transform/opacity/color; przewijanie przez CSS animation-timeline (progressive enhancement), bez nasłuchu scroll.
   Menu mobilne jest w main.js (initNav), bo działa także przy ograniczonym ruchu. */
(function () {
  'use strict';

  var mobile = window.matchMedia('(max-width: 960px)');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (!mobile.matches || reduce.matches) return;
  document.documentElement.classList.add('mobile-fx');

  // Czytnik ekranu dostaje tekst jednym ciągiem (span.visually-hidden), słowa w spanach są tylko wizualne (aria-hidden).
  function srText(text) {
    var sr = document.createElement('span');
    sr.className = 'visually-hidden';
    sr.textContent = text;
    return sr;
  }

  /* ---------- PM Session: akapity ciemnieją słowo po słowie przy przewijaniu ----------
     Odpowiednik v2.js initPmsWords: tam GSAP scrub, tu CSS animation-timeline: view() na każdym słowie (style.css).
     Bez obsługi view() akapity zostają nietknięte. Klasa m-word, nie v2-word: v2.css ma dla .v2-word transition. */
  function initPmsWords() {
    if (!(window.CSS && CSS.supports && CSS.supports('animation-timeline: view()'))) return;
    var section = document.querySelector('.pms-about');
    var paras = section ? section.querySelectorAll('.pms-about__text') : [];
    Array.prototype.forEach.call(paras, function (p) {
      var text = p.textContent.trim();
      var vis = document.createElement('span');
      vis.setAttribute('aria-hidden', 'true');
      text.split(/\s+/).forEach(function (w) {
        var s = document.createElement('span');
        s.className = 'm-word';
        s.textContent = w;
        vis.appendChild(s);
        vis.appendChild(document.createTextNode(' '));
      });
      p.textContent = '';
      p.appendChild(srText(text));
      p.appendChild(vis);
    });
  }

  initPmsWords();
})();
