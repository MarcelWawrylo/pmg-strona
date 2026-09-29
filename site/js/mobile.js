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

  /* ---------- PM Session „Czym jest”: reszta zdania odsłaniana przewijaniem ----------
     Odpowiednik v2.js initPmsIntro bez GSAP i przypinania: „Konferencja naukowa” (.pms-intro__lead) zostaje nietknięta,
     a „poświęcona zarządzaniu projektami” (.pms-intro__rest) dzielimy na słowa jak tam (spacje jako węzły tekstowe, bez
     aria-hidden i bez duplikatu tekstu — textContent się nie zmienia). Odsłanianie słów po kolei robi CSS (style.css). */
  function initPmsIntro() {
    var section = document.querySelector('[data-pms-intro]');
    var rest = section && section.querySelector('.pms-intro__rest');
    if (!rest) return;

    var n = 0;
    var parts = rest.textContent.split(/(\s+)/);
    rest.textContent = '';
    parts.forEach(function (part) {
      if (!part) return;
      if (/^\s+$/.test(part)) { rest.appendChild(document.createTextNode(part)); return; }
      var w = document.createElement('span');
      w.className = 'pms-intro__word';
      w.style.setProperty('--i', String(n++)); // numer słowa → przesunięcie odsłaniania w CSS
      w.textContent = part;
      rest.appendChild(w);
    });
    section.classList.add('is-words');
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

  initPmsIntro();
  initPmsWords();
})();
