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

  // Czytnik ekranu dostaje tekst jednym ciągiem (span.visually-hidden), słowa w spanach są tylko wizualne (aria-hidden).
  function srText(text) {
    var sr = document.createElement('span');
    sr.className = 'visually-hidden';
    sr.textContent = text;
    return sr;
  }

  /* ---------- PM Session „Czym jest”: zdanie wchodzi słowo po słowie ----------
     Odpowiednik v2.js initPmsIntro bez pinu i przesuwu poziomego: podział na słowa jak tam, a wejście (i „echo” ostatniego słowa)
     robi CSS (style.css). Do podziału tytuł jest ukryty przez CSS z bezpiecznikiem 2,5 s, więc bez skryptu treść i tak się pokaże. */
  function initPmsIntro() {
    var section = document.querySelector('[data-pms-intro]');
    var title = section && section.querySelector('.pms-intro__title');
    if (!title) return;

    var line = document.createElement('span');
    line.className = 'pms-intro__line';
    line.setAttribute('aria-hidden', 'true');
    var n = 0;
    var addWord = function (inner) {
      if (line.childNodes.length) line.appendChild(document.createTextNode(' '));
      var w = document.createElement('span');
      w.className = 'pms-intro__word';
      w.style.setProperty('--i', String(n++)); // numer słowa → opóźnienie wejścia w CSS
      w.appendChild(inner);
      line.appendChild(w);
    };
    var sr = srText(title.textContent.replace(/\s+/g, ' ').trim());
    Array.prototype.slice.call(title.childNodes).forEach(function (node) {
      if (node.nodeType === 3) {
        node.textContent.split(/\s+/).forEach(function (part) {
          if (!part) return;
          var s = document.createElement('span');
          s.textContent = part;
          addWord(s);
        });
      } else if (node.nodeType === 1) {
        addWord(node.cloneNode(true)); // akcent („projektami”) to jedno słowo
      }
    });
    title.textContent = '';
    title.appendChild(sr);
    title.appendChild(line);
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
