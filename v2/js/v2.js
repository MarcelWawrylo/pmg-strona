/* PMG — wersja 2 („płynna”). Rozszerza ../js/main.js.
   Tryb pełny tylko ≥ 961 px i bez prefers-reduced-motion: wtedy doładowuje Lenis + GSAP + ScrollTrigger (CDN, SRI).
   Na mobile i przy ograniczonym ruchu nic się nie ładuje — strona działa jak v1. */
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

  /* ---------- Strona główna: logo PMG w WebGL — fala koloru przez słupki, głębia za kursorem ---------- */
  var FRAG = [
    'precision mediump float;',
    'uniform vec2 uRes; uniform float uTime; uniform vec2 uTilt; uniform float uFrame;',
    'float box(vec2 p, vec2 b, float r){ vec2 q = abs(p) - b + r; return length(max(q, 0.0)) + min(max(q.x, q.y), 0.0) - r; }',
    'vec3 brand(float t){ t = fract(t); vec3 a = vec3(0.898,0.094,0.369), b = vec3(0.545,0.173,0.612), c = vec3(0.114,0.275,0.878);',
    '  float k = abs(t * 2.0 - 1.0); return k < 0.5 ? mix(c, b, k * 2.0) : mix(b, a, (k - 0.5) * 2.0); }',
    'float bars(vec2 p){ float d = 1e5;',
    '  float x0[6]; x0[0]=35.0; x0[1]=73.0; x0[2]=111.0; x0[3]=149.0; x0[4]=190.0; x0[5]=228.0;',
    '  float h[6]; h[0]=.44; h[1]=.64; h[2]=.82; h[3]=1.0; h[4]=.88; h[5]=.66;',
    '  float dl[6]; dl[0]=0.0; dl[1]=.3; dl[2]=.525; dl[3]=.75; dl[4]=.975; dl[5]=1.2;',
    '  for (int i = 0; i < 6; i++) { float w = i == 3 ? 29.0 : 26.0;',
    '    float s = .35 + .65 * (.5 - .5 * cos((uTime - dl[i]) * 6.2831853 / 2.4));',
    '    float hh = 190.0 * h[i] * s; vec2 c = vec2(x0[i] + w * .5, 63.0 + hh * .5);',
    '    d = min(d, box(p - c, vec2(w * .5, hh * .5), min(13.0, hh * .5))); } return d; }',
    'float frame(vec2 p){ float d = abs(box(p - vec2(150.0), vec2(144.0), 0.0)) - 1.5;',
    '  if (p.x > 280.0 && p.y > 85.0 && p.y < 215.0) d = 1e5; return d; }',
    'void main(){ vec2 p = gl_FragCoord.xy / uRes * 300.0;',
    '  vec3 col = vec3(0.0); float a = 0.0;',
    '  vec3 wave = brand(p.x / 420.0 - uTime * .12 + .08 * sin(p.y / 40.0 + uTime));',
    '  for (int k = 8; k >= 1; k--) { vec2 o = uTilt * float(k) * 1.4;',
    '    float e = smoothstep(.8, -.8, bars(p + o));',
    '    if (e > 0.0) { col = mix(col, wave * (.35 + .03 * float(8 - k)), e); a = max(a, e); } }',
    '  float f = smoothstep(.8, -.8, bars(p)); float light = .92 + .12 * (p.y - 63.0) / 190.0;',
    '  col = mix(col, wave * light, f); a = max(a, f);',
    '  float fr = smoothstep(.8, -.8, frame(p)) * uFrame; col = mix(col, brand(p.x / 600.0 + p.y / 900.0 - uTime * .05 + .35), fr); a = max(a, fr);',
    '  gl_FragColor = vec4(col * a, a); }'
  ].join('\n');

  function initHeroGL() {
    var host = document.querySelector('.hero-logo');
    if (!host) return;
    var wrap = document.createElement('div');
    wrap.className = 'v2-gl-logo';
    var canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    wrap.appendChild(canvas);
    var gl = canvas.getContext('webgl', { premultipliedAlpha: true, antialias: false, alpha: true });
    if (!gl) return; // brak WebGL → logo CSS z v1
    var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    canvas.width = canvas.height = Math.round(300 * dpr);
    var sh = function (type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; };
    var prog = gl.createProgram();
    gl.attachShader(prog, sh(gl.VERTEX_SHADER, 'attribute vec2 a; void main(){ gl_Position = vec4(a, 0.0, 1.0); }'));
    gl.attachShader(prog, sh(gl.FRAGMENT_SHADER, FRAG));
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return;
    gl.useProgram(prog);
    gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    var loc = gl.getAttribLocation(prog, 'a');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
    var u = function (n) { return gl.getUniformLocation(prog, n); };
    var uRes = u('uRes'), uTime = u('uTime'), uTilt = u('uTilt'), uFrame = u('uFrame');
    gl.uniform2f(uRes, canvas.width, canvas.height);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);

    host.classList.add('hero-logo--gl');
    host.insertBefore(wrap, host.firstChild);

    var target = { x: 0, y: 0 }, tilt = { x: 0, y: 0 }, start = performance.now();
    var hero = host.closest('section') || document.body;
    hero.addEventListener('pointermove', function (e) {
      var r = host.getBoundingClientRect();
      target.x = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / 500));
      target.y = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2)) / 400));
    });
    hero.addEventListener('pointerleave', function () { target.x = target.y = 0; });

    visibilityLoop(canvas, function (now) {
      var t = (now - start) / 1000;
      tilt.x += (target.x - tilt.x) * 0.06;
      tilt.y += (target.y - tilt.y) * 0.06;
      wrap.style.transform = 'rotateY(' + (tilt.x * 14).toFixed(2) + 'deg) rotateX(' + (-tilt.y * 12).toFixed(2) + 'deg)';
      gl.uniform1f(uTime, t);
      gl.uniform2f(uTilt, -tilt.x - 0.35, tilt.y - 0.35);
      gl.uniform1f(uFrame, Math.max(0, Math.min(1, (t - 1.2) / 1.2)));
      gl.clearColor(0, 0, 0, 0);
      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.drawArrays(gl.TRIANGLES, 0, 3);
    });
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
    window.gsap.to(words, {
      color: '#141414', stagger: 0.12, ease: 'none',
      scrollTrigger: { trigger: section, start: 'center center', end: '+=110%', scrub: 0.6, pin: true, pinSpacing: true }
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
    svg.innerHTML = '<defs><linearGradient id="v2-path-g" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#e5185e"/><stop offset=".5" stop-color="#8b2c9c"/><stop offset="1" stop-color="#1d46e0"/></linearGradient></defs>';
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
      var a = dots[0].getBoundingClientRect(), b = dots[dots.length - 1].getBoundingClientRect();
      var x1 = a.left - lr.left + a.width / 2, y1 = a.top - lr.top + a.height / 2;
      var x2 = b.left - lr.left + b.width / 2, y2 = b.top - lr.top + b.height / 2;
      var span = x2 - x1, amp = 26;
      // wijąca się linia przez wszystkie kropki
      var d = 'M' + x1 + ' ' + y1;
      var segs = 4;
      for (var i = 1; i <= segs; i++) {
        var xe = x1 + span * i / segs, ye = y1 + (y2 - y1) * i / segs;
        var xc = x1 + span * (i - 0.5) / segs, yc = ye + (i % 2 ? -amp : amp);
        d += ' Q' + xc + ' ' + yc + ' ' + xe + ' ' + ye;
      }
      path.setAttribute('d', d);
      var len = path.getTotalLength();
      path.style.strokeDasharray = len;
      return len;
    };
    var len = draw();
    path.style.strokeDashoffset = len;
    window.gsap.to(path, {
      strokeDashoffset: 0, ease: 'none',
      scrollTrigger: { trigger: list, start: 'top 85%', end: 'bottom 45%', scrub: 0.5, invalidateOnRefresh: true, onRefresh: function () { draw(); } }
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
      initHeroGL();
      initPmsWords();
      initPodcastWave();
      initJoinPath();
      window.ScrollTrigger.refresh();
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
