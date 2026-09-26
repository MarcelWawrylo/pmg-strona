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

  // wspólne: pauza pętli animacji, gdy element poza ekranem lub karta ukryta
  function visibilityLoop(el, frame) {
    var onScreen = false, raf = 0;
    var tick = function (t) { frame(t); raf = requestAnimationFrame(tick); };
    var update = function () {
      var run = onScreen && !document.hidden;
      if (run && !raf) raf = requestAnimationFrame(tick);
      if (!run && raf) { cancelAnimationFrame(raf); raf = 0; }
    };
    new IntersectionObserver(function (e) { onScreen = e[0].isIntersecting; update(); }).observe(el);
    document.addEventListener('visibilitychange', update);
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

  /* ---------- Podcast: fala odcinków z „lupą” ---------- */
  function initPodcastWave() {
    var hero = document.querySelector('.pod-hero');
    var eps = Array.prototype.slice.call(document.querySelectorAll('.pod-ep'));
    if (!hero || !eps.length) return;
    var section = document.createElement('section');
    section.className = 'v2-wave';
    section.setAttribute('aria-labelledby', 'v2-wave-h');
    var container = document.createElement('div');
    container.className = 'container';
    container.innerHTML = '<h2 id="v2-wave-h" class="visually-hidden">Fala odcinków</h2>' +
      '<div class="v2-wave__stage"><canvas aria-hidden="true"></canvas><div class="v2-wave__hits"></div></div>' +
      '<p class="v2-wave__hint">Najedź na falę albo przejdź do niej klawiszem Tab — każdy fragment to jeden odcinek.</p>';
    section.appendChild(container);
    hero.parentNode.insertBefore(section, hero.nextSibling);

    var hits = container.querySelector('.v2-wave__hits');
    eps.forEach(function (ep, i) {
      var title = (ep.querySelector('.pod-ep__title') || ep).textContent.trim();
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'v2-wave__hit';
      var label = document.createElement('span');
      label.textContent = '#' + (i + 1) + ' ' + title.split(':')[0];
      btn.appendChild(label);
      btn.setAttribute('aria-label', 'Otwórz odcinek #' + (i + 1) + ': ' + title);
      btn.addEventListener('click', function () { var b = ep.querySelector('.pod-ep__btn'); if (b) b.click(); });
      btn.addEventListener('focus', function () { focusX = (i + 0.5) / eps.length; });
      btn.addEventListener('blur', function () { focusX = null; });
      hits.appendChild(btn);
    });

    var stage = container.querySelector('.v2-wave__stage');
    var canvas = stage.querySelector('canvas');
    var ctx = canvas.getContext('2d');
    var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    var W = 0, H = 0, pointerX = null, focusX = null, lensX = 0.5, lensAmt = 0;
    var resize = function () {
      W = stage.clientWidth; H = stage.clientHeight;
      canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener('resize', resize);
    stage.addEventListener('pointermove', function (e) { pointerX = (e.clientX - stage.getBoundingClientRect().left) / W; });
    stage.addEventListener('pointerleave', function () { pointerX = null; });

    var N = 140;
    var grad = function () {
      var g = ctx.createLinearGradient(0, 0, W, 0);
      g.addColorStop(0, '#e5185e'); g.addColorStop(0.45, '#8b2c9c'); g.addColorStop(1, '#1d46e0');
      return g;
    };
    visibilityLoop(stage, function (now) {
      var t = now / 1000;
      var target = pointerX !== null ? pointerX : focusX;
      if (target !== null) lensX += (target - lensX) * 0.12;
      lensAmt += ((target !== null ? 1 : 0) - lensAmt) * 0.08;
      ctx.clearRect(0, 0, W, H);
      var fill = grad();
      var gap = W / N, bw = Math.max(2, gap * 0.55), mid = H * 0.46;
      var active = Math.floor(lensX * eps.length);
      for (var i = 0; i < N; i++) {
        var x = i / (N - 1);
        var amp = 0.35 + 0.3 * Math.sin(i * 0.37 + t * 1.3) * Math.sin(i * 0.11 - t * 0.6) + 0.25 * Math.sin(i * 0.05 + t * 0.4);
        amp = Math.abs(amp);
        var d = (x - lensX) * W;
        var lens = 1 + 1.35 * lensAmt * Math.exp(-(d * d) / (2 * 70 * 70));
        var h = Math.max(4, amp * H * 0.36 * lens);
        var seg = Math.min(eps.length - 1, Math.floor(x * eps.length));
        ctx.globalAlpha = lensAmt > 0.05 && seg !== active ? 0.45 : 1;
        ctx.fillStyle = fill;
        ctx.fillRect(i * gap + (gap - bw) / 2, mid - h / 2, bw, h);
      }
      ctx.globalAlpha = 1;
    });
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
