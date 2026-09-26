/* PMG — animacje strony („płynna” wersja). Rozszerza js/main.js.
   Tryb pełny tylko ≥ 961 px i bez prefers-reduced-motion: wtedy doładowuje Lenis + GSAP + ScrollTrigger (CDN, SRI).
   Na mobile i przy ograniczonym ruchu nic się nie ładuje — strona działa na samym main.js. */
(function () {
  'use strict';

  var desktop = window.matchMedia('(min-width: 961px)');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (!desktop.matches || reduce.matches) return;
  document.documentElement.classList.add('v2-enhanced');

  var LIBS = [
    ['https://cdn.jsdelivr.net/npm/lenis@1.3.4/dist/lenis.min.js', 'sha384-FKTX0CNJ8ngN1oGMBReVu7mvjTJyjFiD5etb1NKnYxc+8eFI2O0KWnksTN+oTFcu'],
    ['https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js', 'sha384-HOvlOYPIs/zjoIkWUGXkVmXsjr8GuZLV+Q+rcPwmJOVZVpvTSXQChiN4t9Euv9Vc'],
    ['https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js', 'sha384-P8VzCVnT9NBUkMrpcIZrJbA7EBjJvh/fJS6PmP+4nLIM284DtsImIv8D0fFjIkeh']
  ];

  function loadLibs(i, done) {
    if (i >= LIBS.length) return done();
    var s = document.createElement('script');
    s.src = LIBS[i][0];
    s.integrity = LIBS[i][1];
    s.crossOrigin = 'anonymous';
    s.onload = function () { loadLibs(i + 1, done); };
    s.onerror = function () { done(true); }; // bez bibliotek strona zostaje w trybie v1
    document.head.appendChild(s);
  }

  /* ---------- Płynny scroll ---------- */
  function initScroll() {
    var lenis = new window.Lenis({ lerp: 0.11, smoothWheel: true });
    lenis.on('scroll', window.ScrollTrigger.update);
    window.gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
    window.gsap.ticker.lagSmoothing(0);
    // okna dialogowe (lightbox, odcinki) — zatrzymaj płynny scroll
    new MutationObserver(function () {
      if (document.body.classList.contains('has-dialog')) lenis.stop(); else lenis.start();
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    document.querySelectorAll('dialog').forEach(function (d) { d.setAttribute('data-lenis-prevent', ''); });
    return lenis;
  }

  /* ---------- PM Session: zdanie kolorowane słowo po słowie (sekcja przypięta) ---------- */
  function initPmsWords() {
    var section = document.querySelector('.pms-about');
    var p = section && section.querySelector('.pms-about__text');
    if (!p) return;
    var text = p.textContent.trim();
    p.textContent = '';
    var sr = document.createElement('span');
    sr.className = 'visually-hidden';
    sr.textContent = text;
    var vis = document.createElement('span');
    vis.setAttribute('aria-hidden', 'true');
    text.split(/\s+/).forEach(function (w, i) {
      var s = document.createElement('span');
      s.className = 'v2-word';
      s.textContent = w;
      vis.appendChild(s);
      vis.appendChild(document.createTextNode(' '));
    });
    p.appendChild(sr);
    p.appendChild(vis);
    section.classList.add('v2-words-ready');
    var words = vis.querySelectorAll('.v2-word');
    // 17.09 — audyt: pin+scrub dawał ok. 1100px pustego przewijania i kontrast .28 ponizej progu
    // WCAG (wymog dostepnosci uczelni publicznej z CLAUDE.md). Zwykly, niepiniowany scroll-linked
    // stagger: krotszy dystans, bez blokowania scrolla, start koloru na tle spelniajacym kontrast.
    window.gsap.to(words, {
      color: '#141414', stagger: 0.06, ease: 'none',
      scrollTrigger: { trigger: section, start: 'top 75%', end: 'top 30%', scrub: 0.6 }
    });
  }

  /* ---------- PM Session „Czym jest”: zdanie jedzie poziomo, napędzane pionowym przewijaniem ---------- */
  // Sekcja ma 4× wysokość okna, wrapper w środku jest przypięty przez position: sticky (v2.css).
  // Zdanie to pierwszy ekran strony, więc wejście słów i tła gra od razu po otwarciu (a nie na początku scrubu),
  // żeby pierwszy ekran nie był pusty. Scrub: 0–0.15 pauza, 0.15–0.9 przesuw w poziomie, 0.8–1 ostatnie słowo 0.3 → 1.
  function initPmsIntro() {
    var section = document.querySelector('[data-pms-intro]');
    var title = section && section.querySelector('.pms-intro__title');
    if (!title) return;
    var gsap = window.gsap;

    // czytnik ekranu dostaje całe zdanie jednym tekstem; słowa w spanach są tylko wizualne
    var sr = document.createElement('span');
    sr.className = 'visually-hidden';
    sr.textContent = title.textContent.replace(/\s+/g, ' ').trim();
    var line = document.createElement('span');
    line.className = 'pms-intro__line';
    line.setAttribute('aria-hidden', 'true');
    var addWord = function (inner) {
      if (line.childNodes.length) line.appendChild(document.createTextNode(' '));
      var w = document.createElement('span');
      w.className = 'pms-intro__word';
      w.appendChild(inner);
      line.appendChild(w);
    };
    Array.prototype.slice.call(title.childNodes).forEach(function (node) {
      if (node.nodeType === 3) {
        node.textContent.split(/\s+/).forEach(function (part) {
          if (!part) return;
          var s = document.createElement('span');
          s.textContent = part;
          addWord(s);
        });
      } else if (node.nodeType === 1) {
        addWord(node.cloneNode(true));
      }
    });
    title.textContent = '';
    title.appendChild(sr);
    title.appendChild(line);
    section.classList.add('is-scrub');

    var words = line.querySelectorAll('.pms-intro__word');
    var lastInner = words[words.length - 1].firstChild;
    var bg = section.querySelector('.pms-intro__bg');
    var START_X = 100;
    var endX = function () { return -(line.offsetWidth - window.innerWidth + START_X); };

    // wejście (czasowe, raz): tło i słowa kolejno z dołu
    gsap.set(lastInner, { opacity: 0.3 });
    if (bg) gsap.fromTo(bg, { opacity: 0, scale: 0.5 }, { opacity: 1, scale: 1, duration: 1.1, ease: 'power2.out' });
    gsap.fromTo(words, { opacity: 0, y: 64 }, { opacity: 1, y: 0, duration: 0.8, ease: 'power3.out', stagger: 0.07, delay: 0.15 });

    // scrub: przesuw całego zdania i dojście ostatniego słowa
    var tl = gsap.timeline({
      scrollTrigger: { trigger: section, start: 'top top', end: 'bottom bottom', scrub: 0.5, invalidateOnRefresh: true }
    });
    tl.fromTo(line, { x: START_X }, { x: endX, ease: 'none', duration: 0.75 }, 0.15)
      .fromTo(lastInner, { opacity: 0.3 }, { opacity: 1, ease: 'none', duration: 0.2 }, 0.8);
    tl.set({}, {}, 1);

    // szerokość zdania zależy od fontu Space Grotesk: przelicz po jego wczytaniu
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { window.ScrollTrigger.refresh(); });
  }

  /* ---------- Dołącz: pozioma ścieżka procesu rysowana przy przewijaniu, z grotem strzałki ----------
     Kroki są ułożone w jednym poziomym rzędzie (v2.css, ≥ 961 px), więc kropki leżą w jednej linii —
     ścieżka to proste odcinki kropka → kropka w kolejności z DOM. Grot to osobny trójkąt SVG,
     przesuwany i obracany tak, żeby zawsze siedział na aktualnym końcu rysowanej linii (scrub). */
  function initJoinPath() {
    var list = document.querySelector('.join-page-steps');
    if (!list) return;
    var dots = list.querySelectorAll('.join-page-steps__dot');
    if (dots.length < 2) return;
    var NS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('class', 'v2-path');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = '<defs><linearGradient id="v2-path-g" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#e5185e"/><stop offset=".5" stop-color="#8b2c9c"/><stop offset="1" stop-color="#1d46e0"/></linearGradient></defs>';
    var grad = svg.querySelector('linearGradient');
    var path = document.createElementNS(NS, 'path');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'url(#v2-path-g)');
    path.setAttribute('stroke-width', '6');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(path);
    var arrow = document.createElementNS(NS, 'path');
    arrow.setAttribute('d', 'M-3 -9 L13 0 L-3 9 Z');
    arrow.setAttribute('fill', '#1d46e0');
    arrow.style.opacity = '0';
    svg.appendChild(arrow);
    list.insertBefore(svg, list.firstChild);
    list.classList.add('v2-path-ready');

    var len = 0;
    // linia jedzie o tyle dalej za ostatnią kropkę, żeby grot nie chował się pod nią, tylko był
    // wyraźnie widoczny za końcem ścieżki (w tym samym kierunku co ostatni odcinek); wartość rośnie
    // wraz z promieniem kropki (32px / promień 16 — było 20px / promień 10), żeby zachować ten sam
    // odstęp grotu za krawędzią kropki
    var ARROW_EXTEND = 34;
    var draw = function () {
      var lr = list.getBoundingClientRect();
      var pts = Array.prototype.map.call(dots, function (dot) {
        var r = dot.getBoundingClientRect();
        return { x: r.left - lr.left + r.width / 2, y: r.top - lr.top + r.height / 2 };
      });
      var last = pts[pts.length - 1], prev = pts[pts.length - 2];
      var dx = last.x - prev.x, dy = last.y - prev.y;
      var dist = Math.sqrt(dx * dx + dy * dy) || 1;
      pts.push({ x: last.x + (dx / dist) * ARROW_EXTEND, y: last.y + (dy / dist) * ARROW_EXTEND });
      // odcinki kropka → kropka, w kolejności z DOM (przy poziomym rzędzie wychodzi prosta linia)
      var d = 'M' + pts[0].x + ' ' + pts[0].y;
      for (var i = 1; i < pts.length; i++) d += ' L' + pts[i].x + ' ' + pts[i].y;
      path.setAttribute('d', d);
      var a = pts[0], b = pts[pts.length - 1];
      grad.setAttribute('x1', a.x); grad.setAttribute('y1', a.y);
      grad.setAttribute('x2', b.x); grad.setAttribute('y2', b.y);
      len = path.getTotalLength();
      path.style.strokeDasharray = len;
      return len;
    };
    len = draw();
    path.style.strokeDashoffset = len;

    // grot na aktualnym końcu narysowanego odcinka: pozycja i kąt liczone z getPointAtLength
    var positionArrow = function () {
      if (!len) { arrow.style.opacity = '0'; return; }
      var offset = parseFloat(path.style.strokeDashoffset) || 0;
      var drawn = Math.max(0, Math.min(len, len - offset));
      if (drawn <= 0.5) { arrow.style.opacity = '0'; return; }
      var p = path.getPointAtLength(drawn);
      var back = path.getPointAtLength(Math.max(0, drawn - 1));
      var angle = Math.atan2(p.y - back.y, p.x - back.x) * 180 / Math.PI;
      arrow.setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ') rotate(' + angle + ')');
      arrow.style.opacity = String(Math.min(1, drawn / 10));
    };
    positionArrow();

    // strona jest krótka: cała linia rysuje się na ok. 300 px przewijania, od 0 i nie dalej niż koniec strony
    var listTop = function () { return list.getBoundingClientRect().top + window.scrollY; };
    var from = function () { return Math.max(0, listTop() - window.innerHeight * 0.8); };
    window.gsap.to(path, {
      strokeDashoffset: 0, ease: 'none',
      onUpdate: positionArrow,
      scrollTrigger: {
        trigger: list, start: from,
        end: function () { return Math.min(window.ScrollTrigger.maxScroll(window), Math.max(from() + 250, listTop() - window.innerHeight * 0.3)); },
        scrub: 0.5, invalidateOnRefresh: true,
        onRefresh: function () { len = draw(); positionArrow(); }
      }
    });
  }

  /* ---------- Case Koła: sekcje (O partnerze → … → Galeria) wjeżdżają po kolei przy przewijaniu ----------
     <main data-case-reveal> na 4 podstronach case. Każda .case-section ma własny ScrollTrigger (raz, bez
     scrub i pin), dzieci jej kontenera wchodzą z dołu z przesunięciem w czasie. Stan ukryty ustawia tylko
     gsap.from w matchMedia — bez JS, < 961 px i przy reduced-motion treść jest widoczna od razu, a po
     zejściu poniżej 961 px matchMedia cofa style. [data-reveal] z main.js w tych sekcjach jest wyłączony
     w v2.css (.v2-enhanced), żeby ten sam element nie animował się dwa razy. */
  function initCaseReveal() {
    var sections = document.querySelectorAll('main[data-case-reveal] > .case-section');
    if (!sections.length) return;
    var gsap = window.gsap;
    gsap.matchMedia().add('(min-width: 961px) and (prefers-reduced-motion: no-preference)', function () {
      Array.prototype.forEach.call(sections, function (section) {
        var box = section.firstElementChild;
        if (!box) return;
        // .measure > .result > h2, p… — schodź przez pojedyncze opakowania, ale nie do liścia (Solvro: .measure > p)
        while (box.children.length === 1 && box.firstElementChild.children.length) box = box.firstElementChild;
        var items = Array.prototype.slice.call(box.children);
        // galeria: nagłówek i każde zdjęcie osobno, nie cała siatka naraz
        items = items.reduce(function (acc, el) {
          return acc.concat(el.classList.contains('gallery') ? Array.prototype.slice.call(el.children) : [el]);
        }, []);
        gsap.from(items, {
          y: 40, autoAlpha: 0, duration: 0.9, stagger: 0.1, ease: 'expo.out',
          scrollTrigger: { trigger: section, start: 'top 85%', once: true }
        });
      });
    });
  }

  /* ---------- Kotwice pod przyklejonym headerem (np. o-nas.html#sekcje z kafla na Dołącz) ----------
     Lenis podmienia natywny scroll, więc samo html{scroll-padding-top} z CSS (używane bez JS) tu nie
     wystarczy: po starcie Lenis, gdy adres ma hash, dosuwamy do celu z offsetem o wysokość headera.
     Czekamy na wczytanie fontów i obrazów — inaczej wysokości elementów (a więc i pozycja celu) jeszcze
     „skaczą”. */
  function scrollToHash(lenis) {
    if (!location.hash) return;
    var el;
    try { el = document.querySelector(location.hash); } catch (e) { return; }
    if (!el) return;
    var nav = document.querySelector('.site-nav');
    var headerHeight = nav ? nav.getBoundingClientRect().height : 96;
    window.ScrollTrigger.refresh();
    lenis.scrollTo(el, { offset: -(headerHeight + 16), immediate: true });
    window.ScrollTrigger.refresh();
  }
  function scrollToHashWhenReady(lenis) {
    if (!location.hash) return;
    var imagesReady = new Promise(function (resolve) {
      if (document.readyState === 'complete') resolve(); else window.addEventListener('load', resolve, { once: true });
    });
    var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    Promise.all([imagesReady, fontsReady]).then(function () { scrollToHash(lenis); });
  }

  var start = function () {
    loadLibs(0, function (failed) {
      if (failed || !window.gsap || !window.ScrollTrigger || !window.Lenis) {
        document.documentElement.classList.remove('v2-enhanced');
        return;
      }
      window.gsap.registerPlugin(window.ScrollTrigger);
      var lenis = initScroll();
      initPmsIntro();
      initPmsWords();
      initJoinPath();
      initCaseReveal();
      window.ScrollTrigger.refresh();
      scrollToHashWhenReady(lenis);

      // Wysokości sekcji zależą od zawinięcia tekstu czcionkami (Space Grotesk/Manrope) i obrazów —
      // po ich wczytaniu układ może się jeszcze przesunąć (np. Dołącz: „Proces rekrutacyjny” jest
      // teraz niżej, pod „Poznaj nasze sekcje”), więc przeliczamy ScrollTrigger jeszcze raz, gdy oba
      // są gotowe.
      var fontsReady2 = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
      var pageLoaded = new Promise(function (resolve) {
        if (document.readyState === 'complete') resolve(); else window.addEventListener('load', resolve, { once: true });
      });
      Promise.all([fontsReady2, pageLoaded]).then(function () { window.ScrollTrigger.refresh(); });

      // Przelicz pinning/scrub po zmianie rozmiaru okna (np. obrót tabletu, zmiana szerokości
      // przeglądarki) — bez tego przypięte sekcje (PMS, Dołącz) mogą się rozjechać.
      var resizeTimer = null;
      window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () { window.ScrollTrigger.refresh(); }, 200);
      });
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
