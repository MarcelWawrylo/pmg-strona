/* PMG — animacje strony („płynna” wersja). Rozszerza js/main.js.
   Tryb pełny tylko ≥ 961 px i bez prefers-reduced-motion: wtedy doładowuje Lenis + GSAP + ScrollTrigger (CDN, SRI).
   Na mobile i przy ograniczonym ruchu nic się nie ładuje — strona działa na samym main.js. */
(function () {
  'use strict';

  var desktop = window.matchMedia('(min-width: 961px)');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (!desktop.matches || reduce.matches) return;
  document.documentElement.classList.add('v2-enhanced');

  /* ---------- Case Koła: animowane logo (czysty CSS w v2.css, niezależnie od GSAP/CDN) ----------
     .is-anim ustawia sekwencję w klatce początkowej (wstrzymaną przez .is-anim-wait); gdy logo wejdzie
     w widok, zdejmujemy .is-anim-wait i sekwencja rusza — raz na wczytanie strony. */
  (function initCaseLogo() {
    var logo = document.querySelector('.case-logo');
    if (!logo || !('IntersectionObserver' in window)) return;
    logo.classList.add('is-anim', 'is-anim-wait');
    var io = new IntersectionObserver(function (entries) {
      if (!entries.some(function (e) { return e.isIntersecting; })) return;
      io.disconnect();
      logo.classList.remove('is-anim-wait');
    }, { threshold: 0.3 });
    io.observe(logo);
  })();

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

  /* ---------- PM Session: akapity kolorowane słowo po słowie (oba akapity „Co to PM Session?”, jedna sekwencja) ---------- */
  function initPmsWords() {
    var section = document.querySelector('.pms-about');
    var paras = section ? Array.prototype.slice.call(section.querySelectorAll('.pms-about__text')) : [];
    if (!paras.length) return;
    var words = [];
    // każdy akapit: pełny tekst dla czytnika ekranu (visually-hidden) + słowa w spanach tylko wizualnie (aria-hidden)
    paras.forEach(function (p) {
      var text = p.textContent.trim();
      p.textContent = '';
      var sr = document.createElement('span');
      sr.className = 'visually-hidden';
      sr.textContent = text;
      var vis = document.createElement('span');
      vis.setAttribute('aria-hidden', 'true');
      text.split(/\s+/).forEach(function (w) {
        var s = document.createElement('span');
        s.className = 'v2-word';
        s.textContent = w;
        vis.appendChild(s);
        vis.appendChild(document.createTextNode(' '));
        words.push(s);
      });
      p.appendChild(sr);
      p.appendChild(vis);
    });
    section.classList.add('v2-words-ready');
    // 17.09 — audyt: pin+scrub dawał ok. 1100px pustego przewijania i kontrast .28 ponizej progu
    // WCAG (wymog dostepnosci uczelni publicznej z CLAUDE.md). Zwykly, niepiniowany scroll-linked
    // stagger: krotszy dystans, bez blokowania scrolla, start koloru na tle spelniajacym kontrast.
    // Dwa akapity kolejno: od wejścia pierwszego (góra na 75% okna) do chwili, gdy dół drugiego jest w połowie okna.
    window.gsap.to(words, {
      color: '#141414', stagger: 0.06, ease: 'none',
      scrollTrigger: { trigger: paras[0], start: 'top 75%', endTrigger: paras[paras.length - 1], end: 'bottom 50%', scrub: 0.6 }
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
    // trójkąt grotu, czubek w lokalnym (0,0) — po translate(...) na punkt ścieżki czubek ląduje
    // dokładnie na tym punkcie (nie przed nim, nie za nim)
    arrow.setAttribute('d', 'M-16 -9 L0 0 L-16 9 Z');
    arrow.setAttribute('fill', '#1d46e0');
    arrow.style.opacity = '0';
    svg.appendChild(arrow);
    list.insertBefore(svg, list.firstChild);
    list.classList.add('v2-path-ready');

    var len = 0;
    var draw = function () {
      var lr = list.getBoundingClientRect();
      var pts = Array.prototype.map.call(dots, function (dot) {
        var r = dot.getBoundingClientRect();
        return { x: r.left - lr.left + r.width / 2, y: r.top - lr.top + r.height / 2 };
      });
      var last = pts[pts.length - 1], prev = pts[pts.length - 2];
      var dx = last.x - prev.x, dy = last.y - prev.y;
      var dist = Math.sqrt(dx * dx + dy * dy) || 1;
      // ścieżka (i więc grot, który jedzie z jej końcem) zatrzymuje się na krawędzi ostatniej kropki
      // (środek kropki minus promień w kierunku nadchodzącej linii), nie na jej środku i nie za nią
      var lastR = dots[dots.length - 1].getBoundingClientRect().width / 2;
      pts[pts.length - 1] = { x: last.x - (dx / dist) * lastR, y: last.y - (dy / dist) * lastR };
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
    var state = { p: 0 }; // postęp 0→1 tweenowany przez GSAP, niezależny od długości ścieżki (resize zmienia len)

    // grot na aktualnym końcu narysowanego odcinka: pozycja i kąt liczone z getPointAtLength.
    // Widoczna linia kończy się ARROW_TRIM px przed czubkiem, pod grotem (16 px długości): inaczej jej zaokrąglona
    // końcówka (promień 3 px przy stroke-width 6) wystaje przed ostry czubek i wygląda jak kwadratowa.
    var ARROW_TRIM = 13;
    var positionArrow = function () {
      if (!len) { arrow.style.opacity = '0'; return; }
      var offset = len * (1 - state.p);
      var drawn = Math.max(0, Math.min(len, len - offset));
      path.style.strokeDashoffset = len - Math.max(0, drawn - ARROW_TRIM);
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
    window.gsap.to(state, {
      p: 1, ease: 'none',
      onUpdate: positionArrow,
      scrollTrigger: {
        trigger: list, start: from,
        // zakres rysowania ×1/1.18 („18% szybciej”), start bez zmian
        end: function () { var e = Math.min(window.ScrollTrigger.maxScroll(window), Math.max(from() + 250, listTop() - window.innerHeight * 0.3)); return from() + (e - from()) / 1.18; },
        scrub: 0.5, invalidateOnRefresh: true,
        onRefresh: function () { len = draw(); positionArrow(); }
      }
    });
  }

  /* ---------- Case Koła: sekcje (O partnerze → Wyzwanie → Co zrobiliśmy → Rezultat → Galeria) po kolei ----------
     <main data-case-reveal> na 4 podstronach case. Każda .case-section „przylatuje” z dołu (y: 72 → 0) i dokleja się
     pod poprzednią. Do tego czasu jest ukryta przez v2.css (visibility: hidden, tylko z .v2-enhanced ≥ 961 px bez
     reduced-motion), więc bez JS, bez CDN, < 961 px i przy reduced-motion wszystko jest widoczne od razu.
     - Kolejka: sekcje startują w kolejności DOM co GAP s, nawet gdy kilka wejdzie w widok naraz (szybki scroll).
     - Bez nakładania: w trakcie ruchu dół sekcji jest przycięty (clip-path) o tyle, o ile jest przesunięta, więc
       widoczna część nie wychodzi poza jej miejsce w układzie; układ (także galerii) się nie zmienia.
     - Raz pokazana zostaje (.is-case-in), bez chowania przy przewijaniu w górę.
     - Sekcje, które są już nad oknem (hash, odświeżenie w połowie strony, skok na dół), pokazują się od razu.
     [data-reveal] z main.js w tych sekcjach jest wyłączony w v2.css, żeby nic nie animowało się dwa razy. */
  function initCaseReveal() {
    var sections = Array.prototype.slice.call(document.querySelectorAll('main[data-case-reveal] > .case-section:not(.section--gallery)'));
    if (!sections.length) return;
    var gsap = window.gsap;
    var Y = 72, DURATION = 1, GAP = 0.22, START = 0.85; // START: górna krawędź sekcji na 85% wysokości okna

    // elementy do lekkiego przesunięcia w czasie wewnątrz sekcji (nagłówek → treść; sekcja galerii bez animacji)
    var itemsOf = function (section) {
      var box = section.firstElementChild;
      if (!box) return [];
      // .measure > .result > h2, p… — schodź przez pojedyncze opakowania, ale nie do liścia (Solvro: .measure > p)
      while (box.children.length === 1 && box.firstElementChild.children.length) box = box.firstElementChild;
      return Array.prototype.slice.call(box.children);
    };

    gsap.matchMedia().add('(min-width: 961px) and (prefers-reduced-motion: no-preference)', function () {
      // 0 czeka, 1 w kolejce, 2 w ruchu, 3 pokazana
      var state = sections.map(function (s) { return s.classList.contains('is-case-in') ? 3 : 0; });
      var items = sections.map(itemsOf);
      var pending = [], timelines = [], nextStart = 0, watcher;

      var finish = function (i) {
        if (state[i] === 3) return;
        state[i] = 3;
        sections[i].classList.add('is-case-in');
        gsap.set(sections[i], { clearProps: 'transform,opacity,visibility,clipPath' });
        gsap.set(items[i], { clearProps: 'transform,opacity,visibility' });
        if (state.every(function (s) { return s === 3; }) && watcher) { watcher.kill(); watcher = null; }
      };
      var play = function (i) {
        pending[i] = null;
        state[i] = 2;
        var clip = { b: Y };
        var tl = timelines[i] = gsap.timeline({ onComplete: function () { timelines[i] = null; finish(i); } });
        tl.fromTo(sections[i], { y: Y }, { y: 0, duration: DURATION, ease: 'expo.out' }, 0)
          .fromTo(clip, { b: Y }, {
            b: 0, duration: DURATION, ease: 'expo.out',
            onUpdate: function () { sections[i].style.clipPath = 'inset(0px 0px ' + clip.b.toFixed(2) + 'px 0px)'; }
          }, 0)
          .fromTo(sections[i], { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.5, ease: 'power2.out' }, 0)
          .from(items[i], { y: 24, autoAlpha: 0, duration: 0.8, stagger: 0.08, ease: 'power3.out' }, 0.1);
      };
      var enqueue = function (i) {
        state[i] = 1;
        var now = gsap.ticker.time;
        var at = Math.max(now, nextStart);
        nextStart = at + GAP;
        pending[i] = gsap.delayedCall(at - now, play, [i]);
      };
      // sekcja i jest już nad oknem → ona i wszystkie przed nią od razu w pełni widoczne
      var showNow = function (i) {
        for (var j = 0; j <= i; j++) {
          if (state[j] === 3) continue;
          if (pending[j]) { pending[j].kill(); pending[j] = null; }
          if (timelines[j]) timelines[j].progress(1); else finish(j);
        }
      };
      var sweep = function () {
        var vh = window.innerHeight, last = -1, above = -1;
        sections.forEach(function (s, i) {
          if (state[i] === 3) return;
          var r = s.getBoundingClientRect();
          var shift = Number(gsap.getProperty(s, 'y')) || 0; // miejsce w układzie, bez przesunięcia animacji
          if (r.top - shift < vh * START) last = i;
          if (r.bottom - shift <= 0) above = i;
        });
        if (above >= 0) showNow(above);
        for (var i = 0; i <= last; i++) if (state[i] === 0) enqueue(i);
      };

      watcher = window.ScrollTrigger.create({ start: 0, end: 'max', onUpdate: sweep, onRefresh: sweep });
      sweep();

      return function () {
        if (watcher) watcher.kill();
        pending.forEach(function (d) { if (d) d.kill(); });
        timelines.forEach(function (t) { if (t) t.kill(); });
        sections.forEach(function (s, i) {
          gsap.set(s, { clearProps: 'transform,opacity,visibility,clipPath' });
          gsap.set(items[i], { clearProps: 'transform,opacity,visibility' });
        });
      };
    });
  }

  /* ---------- Kotwice pod przyklejonym headerem (np. o-nas.html#sekcje z kafla na Dołącz) ----------
     Lenis podmienia natywny scroll, więc samo html{scroll-padding-top} z CSS (używane bez JS) tu nie
     wystarczy: po starcie Lenis, gdy adres ma hash, dosuwamy do celu z offsetem o wysokość headera.
     Czekamy na wczytanie fontów i obrazów — inaczej wysokości elementów (a więc i pozycja celu) jeszcze
     „skaczą”. */
  function headerOffset() {
    var nav = document.querySelector('.site-nav');
    var headerHeight = nav ? nav.getBoundingClientRect().height : 96;
    return -(headerHeight + 16);
  }
  function scrollToHash(lenis) {
    if (!location.hash) return;
    var el;
    try { el = document.querySelector(location.hash); } catch (e) { return; }
    if (!el) return;
    window.ScrollTrigger.refresh();
    lenis.scrollTo(el, { offset: headerOffset(), immediate: true });
    window.ScrollTrigger.refresh();
    // po przewinięciu nav kurczy się do pigułki (niższej o ok. 30 px) — gdy skończy przejście,
    // przelicz odstęp z jej nową wysokością, żeby cel stał tuż pod headerem, a nie ~50 px niżej
    setTimeout(function () {
      lenis.scrollTo(el, { offset: headerOffset(), immediate: true });
      window.ScrollTrigger.refresh();
    }, 500);
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
      // klik w kotwicę na tej samej stronie (main.js, initAnchorScroll) korzysta z Lenis zamiast
      // scrollIntoView, żeby nie mieć dwóch mechanizmów przewijania naraz na ≥ 961 px
      if (window.PMG) {
        window.PMG.smoothScrollTo = function (el, onComplete) {
          window.ScrollTrigger.refresh();
          lenis.scrollTo(el, { offset: headerOffset(), onComplete: onComplete });
        };
      }
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

      // Wysokość strony zmienia się też bez zmiany okna: nav po przewinięciu kurczy się do pigułki
      // (strona jest wtedy niższa o ok. 40 px). Bez przeliczenia koniec scrubu może wypaść za dołem
      // strony (np. Dołącz: linia procesu nie dochodzi do końca). Przeliczamy, gdy zmieni się wysokość body.
      if ('ResizeObserver' in window) {
        var bodyH = document.body.offsetHeight, heightTimer = null;
        new ResizeObserver(function () {
          var h = document.body.offsetHeight;
          if (Math.abs(h - bodyH) < 2) return;
          bodyH = h;
          clearTimeout(heightTimer);
          heightTimer = setTimeout(function () { window.ScrollTrigger.refresh(); }, 150);
        }).observe(document.body);
      }
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
