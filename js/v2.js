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

  /* ---------- Podcast: pionowa fala przy prawej krawędzi hero (czysta dekoracja) ----------
     Bez wyboru odcinka — wybór odcinków dzieje się przez karty (main.js). Fala tylko reaguje
     na ruch myszy nad hero; w spoczynku nie rysuje (rAF startuje na mousemove i gaśnie po
     ok. 2 s bezruchu). Na dotyku (hover:none/pointer:coarse) zostaje jedna nieruchoma klatka. */
  function initPodcastWave() {
    var hero = document.querySelector('.pod-hero');
    if (!hero) return;
    var coarse = window.matchMedia('(hover: none), (pointer: coarse)').matches;

    var wave = document.createElement('div');
    wave.className = 'v2-wave';
    wave.setAttribute('aria-hidden', 'true');
    wave.innerHTML = '<canvas></canvas>';
    hero.appendChild(wave);

    var canvas = wave.querySelector('canvas');
    var ctx = canvas.getContext('2d');
    var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    var W = 0, H = 0, raf = 0;
    var resize = function () {
      W = wave.clientWidth; H = wave.clientHeight;
      if (!W || !H) return;
      canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      if (!raf) draw(0);
    };

    var N = 56;
    var grad = function () {
      var g = ctx.createLinearGradient(0, 0, 0, H);
      g.addColorStop(0, '#e5185e'); g.addColorStop(0.5, '#8b2c9c'); g.addColorStop(1, '#1d46e0');
      return g;
    };
    var pointerY = null, lensY = 0.5, lensAmt = 0;
    function draw(t) {
      if (!W || !H) return;
      var target = pointerY;
      if (target !== null) lensY += (target - lensY) * 0.12;
      lensAmt += ((target !== null ? 1 : 0) - lensAmt) * 0.08;
      ctx.clearRect(0, 0, W, H);
      var fill = grad();
      var gap = H / N, bw = Math.max(2, gap * 0.55), mid = W * 0.5;
      for (var i = 0; i < N; i++) {
        var y = i / (N - 1);
        var amp = 0.35 + 0.3 * Math.sin(i * 0.37 + t * 1.3) * Math.sin(i * 0.11 - t * 0.6) + 0.25 * Math.sin(i * 0.05 + t * 0.4);
        amp = Math.abs(amp);
        var d = (y - lensY) * H;
        var lens = 1 + 1.35 * lensAmt * Math.exp(-(d * d) / (2 * 70 * 70));
        var w = Math.max(4, amp * W * 0.62 * lens);
        ctx.fillStyle = fill;
        ctx.fillRect(mid - w / 2, i * gap + (gap - bw) / 2, w, bw);
      }
    }

    resize();
    window.addEventListener('resize', resize);
    draw(0); // klatka spoczynkowa — bez pętli rAF, dopóki nic się nie zmienia

    if (coarse) return; // ekran dotykowy: fala zostaje nieruchoma

    var startT = null, idleTimer = null;
    var loop = function (now) {
      if (startT === null) startT = now;
      draw((now - startT) / 1000);
      raf = requestAnimationFrame(loop);
    };
    var stop = function () { if (raf) { cancelAnimationFrame(raf); raf = 0; } };
    var startLoop = function () { if (!raf) { startT = null; raf = requestAnimationFrame(loop); } };
    var scheduleStop = function () {
      clearTimeout(idleTimer);
      idleTimer = setTimeout(stop, 2000);
    };
    hero.addEventListener('mousemove', function (e) {
      var r = wave.getBoundingClientRect();
      if (!r.height) return;
      pointerY = (e.clientY - r.top) / r.height;
      startLoop();
      scheduleStop();
    });
    hero.addEventListener('mouseleave', function () {
      pointerY = null;
      scheduleStop();
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); });
  }

  /* ---------- Dołącz: ścieżka procesu rysowana przy przewijaniu ---------- */
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
    path.setAttribute('stroke-width', '2.5');
    path.setAttribute('stroke-linecap', 'round');
    svg.appendChild(path);
    list.insertBefore(svg, list.firstChild);
    list.classList.add('v2-path-ready');

    var draw = function () {
      var lr = list.getBoundingClientRect();
      var pts = Array.prototype.map.call(dots, function (dot) {
        var r = dot.getBoundingClientRect();
        return { x: r.left - lr.left + r.width / 2, y: r.top - lr.top + r.height / 2 };
      });
      var amp = 26;
      // segment po segmencie: kropka i → kropka i+1, w kolejności z DOM
      var d = 'M' + pts[0].x + ' ' + pts[0].y;
      for (var i = 1; i < pts.length; i++) {
        var p = pts[i - 1], q = pts[i];
        if (Math.abs(q.y - p.y) < 1) {
          // ten sam rząd: łagodny łuk
          d += ' Q' + (p.x + q.x) / 2 + ' ' + (p.y + (i % 2 ? -amp : amp)) + ' ' + q.x + ' ' + q.y;
        } else if (Math.abs(q.x - p.x) < 1) {
          // jedna kolumna (mobile): pionowo
          d += ' L' + q.x + ' ' + q.y;
        } else {
          // nowy rząd w siatce 2×2: łagodnie po skosie, za tekstem kroków (tło tekstu w v2.css)
          var dx = q.x - p.x, dy = q.y - p.y;
          d += ' C' + (p.x + dx * 0.1) + ' ' + (p.y + dy * 0.4) + ' ' + (q.x - dx * 0.1) + ' ' + (q.y - dy * 0.4) + ' ' + q.x + ' ' + q.y;
        }
      }
      path.setAttribute('d', d);
      // gradient w układzie strony: pionowa linia ma zerową szerokość, więc objectBoundingBox by jej nie pokolorował
      var a = pts[0], b = pts[pts.length - 1];
      grad.setAttribute('x1', a.x); grad.setAttribute('y1', a.y);
      grad.setAttribute('x2', b.x); grad.setAttribute('y2', b.y);
      var len = path.getTotalLength();
      path.style.strokeDasharray = len;
      return len;
    };
    var len = draw();
    path.style.strokeDashoffset = len;
    // strona jest krótka: cała linia rysuje się na ok. 300 px przewijania, od 0 i nie dalej niż koniec strony
    var listTop = function () { return list.getBoundingClientRect().top + window.scrollY; };
    var from = function () { return Math.max(0, listTop() - window.innerHeight * 0.8); };
    window.gsap.to(path, {
      strokeDashoffset: 0, ease: 'none',
      scrollTrigger: {
        trigger: list, start: from,
        end: function () { return Math.min(window.ScrollTrigger.maxScroll(window), Math.max(from() + 250, listTop() - window.innerHeight * 0.3)); },
        scrub: 0.5, invalidateOnRefresh: true, onRefresh: function () { draw(); }
      }
    });
  }

  var start = function () {
    loadLibs(0, function (failed) {
      if (failed || !window.gsap || !window.ScrollTrigger || !window.Lenis) {
        document.documentElement.classList.remove('v2-enhanced');
        return;
      }
      window.gsap.registerPlugin(window.ScrollTrigger);
      initScroll();
      initPmsIntro();
      initPmsWords();
      initPodcastWave();
      initJoinPath();
      window.ScrollTrigger.refresh();

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
