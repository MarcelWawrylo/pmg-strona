/* PMG — wspólne zachowania strony (v1). Vanilla JS, bez zależności.
   Każdy moduł uruchamia się tylko, gdy na stronie jest jego element. */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ---------- Nawigacja: kurczenie przy scrollu + menu mobilne ---------- */
  function initNav() {
    var nav = $('[data-nav]');
    if (!nav) return;
    var themeSwitch = nav.hasAttribute('data-nav-theme-switch') ? $(nav.getAttribute('data-nav-theme-switch')) : null;

    var onScroll = function () {
      var y = window.scrollY || document.documentElement.scrollTop || 0;
      nav.classList.toggle('is-scrolled', y > 24);
      if (themeSwitch) {
        // Dołącz: ciemny nav nad ciemnym hero, jasny po zjechaniu z hero
        var bottom = themeSwitch.getBoundingClientRect().bottom;
        nav.classList.toggle('site-nav--dark', bottom > nav.offsetHeight);
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var toggle = $('.site-nav__toggle', nav);
    var menu = $('.site-nav__menu', nav);
    if (!toggle || !menu) return;
    var setOpen = function (open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
    };
    toggle.addEventListener('click', function () { setOpen(!nav.classList.contains('is-open')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
    });
    var mq = window.matchMedia('(min-width: 961px)');
    var onMq = function () { if (mq.matches) setOpen(false); };
    if (mq.addEventListener) mq.addEventListener('change', onMq); else mq.addListener(onMq);
  }

  /* ---------- Przycisk „Wróć na górę” ---------- */
  function initToTop() {
    $$('[data-totop]').forEach(function (b) {
      b.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
        var target = $('#main') || document.body;
        target.focus({ preventScroll: true });
      });
    });
  }

  /* ---------- Odsłanianie przy scrollu ---------- */
  function initReveal() {
    var items = $$('[data-reveal]');
    var tl = $$('[data-tl-item]');
    if (!('IntersectionObserver' in window) || reduceMotion.matches) {
      items.concat(tl).forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.1 });
    items.forEach(function (el) { io.observe(el); });

    var io2 = new IntersectionObserver(function (entries) {
      entries.forEach(function (e, i) {
        if (e.isIntersecting) {
          setTimeout(function () { e.target.classList.add('is-in'); }, i * 90);
          io2.unobserve(e.target);
        }
      });
    }, { threshold: 0.15 });
    tl.forEach(function (el) { io2.observe(el); });
  }

  /* ---------- Dialogi: wspólna obsługa zamykania i przywracania focusu ---------- */
  function wireDialog(dialog, onClose) {
    var opener = null;
    dialog.addEventListener('close', function () {
      document.body.classList.remove('has-dialog');
      if (onClose) onClose();
      if (opener) opener.focus();
    });
    // klik w tło zamyka
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    $$('[data-dialog-close]', dialog).forEach(function (b) {
      b.addEventListener('click', function () { dialog.close(); });
    });
    return function open(from) {
      opener = from || document.activeElement;
      document.body.classList.add('has-dialog');
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
      var closeBtn = $('[data-dialog-close]', dialog);
      if (closeBtn) closeBtn.focus();
    };
  }
  window.PMG = { wireDialog: wireDialog, $: $, $$: $$, reduceMotion: reduceMotion };

  /* ---------- Lightbox galerii ---------- */
  function initLightbox() {
    var lb = $('[data-lightbox]');
    if (!lb) return;
    var img = $('[data-lightbox-img]', lb);
    var cap = $('[data-lightbox-caption]', lb);
    var open = wireDialog(lb, function () { img.removeAttribute('src'); });
    $$('[data-lightbox-trigger]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        img.src = btn.getAttribute('data-full');
        img.alt = btn.getAttribute('data-caption') || '';
        cap.textContent = btn.getAttribute('data-caption') || '';
        open(btn);
      });
    });
  }

  /* ---------- Kontakt: formularz bez backendu → link mailto (rozwiązanie tymczasowe) ---------- */
  function initContactForm() {
    var form = $('[data-contact-form]');
    if (!form) return;
    var error = $('[data-form-error]', form);
    var status = $('[data-form-status]', form);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fields = $$('.field__input', form);
      var firstInvalid = null;
      fields.forEach(function (f) {
        f.value = f.value.trim();
        var ok = f.checkValidity();
        f.setAttribute('aria-invalid', ok ? 'false' : 'true');
        if (!ok && !firstInvalid) firstInvalid = f;
      });
      if (firstInvalid) {
        error.hidden = false;
        firstInvalid.focus();
        return;
      }
      error.hidden = true;
      var v = function (n) { return form.elements[n].value; };
      var bodyText = v('message') + '\n\n—\n' + v('name') + '\n' + v('email');
      var href = 'mailto:' + form.getAttribute('data-mailto') +
        '?subject=' + encodeURIComponent(v('subject')) +
        '&body=' + encodeURIComponent(bodyText);
      status.textContent = 'Otwieramy Twój program pocztowy z gotową wiadomością…';
      window.location.href = href;
    });
  }

  /* ---------- Rozwijane karty: kafelki aktualności, prelegenci PMS (hover rozwija, klik/dotyk przypina) ---------- */
  function initNewsTiles() {
    $$('[data-news], [data-expand]').forEach(function (tile) {
      var btn = $('.news-tile__toggle, .expand-toggle', tile);
      if (!btn) return;
      btn.addEventListener('click', function () {
        var open = !tile.classList.contains('is-open');
        tile.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  document.documentElement.classList.add('js');
  var init = function () {
    initNewsTiles();
    initNav();
    initToTop();
    initReveal();
    initLightbox();
    initContactForm();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

/* ---------- Aktualności ---------- */
/* Aktualności: przełączanie widoku lista / artykuł na podstawie location.hash (#wpis-<id>).
   Bez JS: lista i wszystkie artykuły pod spodem, linki kotwiczne działają. Plik tymczasowy do scalenia z main.js. */
(function () {
  'use strict';
  var init = function () {
    var PMG = window.PMG || {};
    var $ = PMG.$ || function (s, r) { return (r || document).querySelector(s); };
    var $$ = PMG.$$ || function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var list = $('[data-blog-list]');
    var wrap = $('[data-blog-articles]');
    if (!list || !wrap) return;
    var articles = $$('.blog-article', wrap);
    var baseTitle = document.title;
    var listTitle = $('#blog-title', list);

    var find = function (hash) {
      if (hash === '' || hash === '#' || hash === '#aktualnosci') return 'list';
      for (var i = 0; i < articles.length; i++) if ('#' + articles[i].id === hash) return articles[i];
      return null; // inny hash (np. #main z linku „Przejdź do treści”) — nie zmieniaj widoku
    };

    var current = null;
    var show = function (target, moveFocus) {
      var art = target === 'list' ? null : target;
      list.hidden = !!art;
      wrap.hidden = !art;
      articles.forEach(function (a) { a.hidden = a !== art; });
      var changed = current !== target;
      current = target;
      if (art) {
        var h1 = $('.blog-article__title', art);
        document.title = h1.textContent + ' — Aktualności — Project Management Group';
        window.scrollTo(0, 0);
        if (moveFocus) h1.focus({ preventScroll: true });
      } else {
        document.title = baseTitle;
        if (changed || moveFocus) window.scrollTo(0, 0);
        if (moveFocus && listTitle) listTitle.focus({ preventScroll: true });
      }
    };

    // przewijanie sterujemy sami (Wstecz przeglądarki → góra listy + focus na nagłówku, spójnie z linkami powrotu)
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    var initial = find(location.hash);
    show(initial || 'list', false);
    document.documentElement.classList.add('blog-ready');

    window.addEventListener('hashchange', function () {
      var t = find(location.hash);
      if (t) show(t, true);
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

/* ---------- Podcast ---------- */
/* Podcast — tło (szum + plamy) i modal odcinka. Plik tymczasowy do scalenia z main.js. */
(function () {
  'use strict';

  function init() {
    var PMG = window.PMG;
    if (!PMG) return;
    var $ = PMG.$, $$ = PMG.$$, reduceMotion = PMG.reduceMotion;

    /* ---------- Szum (canvas w 1/2 rozdzielczości, skalowany CSS) ---------- */
    var canvas = $('[data-pod-noise]');
    if (canvas && canvas.getContext) {
      var ctx = canvas.getContext('2d');
      var SCALE = 0.5, timer = null, raf = null;
      var resize = function () {
        canvas.width = Math.max(1, Math.ceil(window.innerWidth * SCALE));
        canvas.height = Math.max(1, Math.ceil(window.innerHeight * SCALE));
      };
      var draw = function () {
        var imageData = ctx.createImageData(canvas.width, canvas.height);
        var buffer = new Uint32Array(imageData.data.buffer);
        for (var i = 0; i < buffer.length; i += 2) {
          if (Math.random() < 0.65) buffer[i] = (0xffffffff * Math.random()) >>> 0;
        }
        ctx.putImageData(imageData, 0, 0);
      };
      var stop = function () {
        clearTimeout(timer); cancelAnimationFrame(raf); timer = raf = null;
      };
      var loop = function () {
        draw();
        timer = setTimeout(function () { raf = requestAnimationFrame(loop); }, 60);
      };
      var start = function () {
        stop();
        if (reduceMotion.matches) { draw(); return; }   // bez animacji: jedna klatka
        if (!document.hidden) loop();
      };
      resize();
      start();
      window.addEventListener('resize', function () { resize(); if (reduceMotion.matches || document.hidden) draw(); });
      document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
      if (reduceMotion.addEventListener) reduceMotion.addEventListener('change', start);
    }

    /* ---------- Plamy: podążają za kursorem, na dotyku dryfują same ---------- */
    var blobs = $$('[data-pod-blob]');
    if (blobs.length) {
      var mx = window.innerWidth / 2, my = window.innerHeight / 2, tx = mx, ty = my;
      var braf = null, driftTimer = null;
      var isTouch = ('ontouchstart' in window) || window.matchMedia('(hover: none)').matches;
      var apply = function () {
        var cx = window.innerWidth / 2, cy = window.innerHeight / 2;
        blobs.forEach(function (b, i) {
          var offset = (i - 1) * 120;
          b.style.transform = 'translate(' + ((mx - cx) * 0.15 + offset) + 'px, ' + ((my - cy) * 0.15 + offset) + 'px)';
        });
      };
      var tick = function () {
        mx += (tx - mx) * 0.03;
        my += (ty - my) * 0.03;
        apply();
        if (Math.abs(tx - mx) < 0.5 && Math.abs(ty - my) < 0.5) { braf = null; return; }  // spoczynek
        braf = requestAnimationFrame(tick);
      };
      var kick = function () {
        if (reduceMotion.matches || document.hidden || braf) return;
        braf = requestAnimationFrame(tick);
      };
      var reset = function () {   // statyczne położenie (jak w CSS)
        if (braf) cancelAnimationFrame(braf);
        braf = null;
        mx = tx = window.innerWidth / 2; my = ty = window.innerHeight / 2;
        blobs.forEach(function (b) { b.style.transform = ''; });
      };
      window.addEventListener('mousemove', function (e) { tx = e.clientX; ty = e.clientY; kick(); }, { passive: true });
      var drift = function () {
        clearInterval(driftTimer);
        if (!isTouch || reduceMotion.matches) return;
        driftTimer = setInterval(function () {
          if (document.hidden) return;
          tx = Math.random() * window.innerWidth; ty = Math.random() * window.innerHeight;
          kick();
        }, 3500);
      };
      drift();
      document.addEventListener('visibilitychange', function () { if (!document.hidden) kick(); });
      if (reduceMotion.addEventListener) reduceMotion.addEventListener('change', function () { if (reduceMotion.matches) reset(); drift(); });
    }

    /* ---------- Modal odcinka ---------- */
    var dialog = $('[data-pod-modal]');
    if (dialog) {
      var content = $('[data-pod-modal-content]', dialog);
      var open = PMG.wireDialog(dialog, function () { content.textContent = ''; });  // usuwa też iframe Spotify
      $$('[data-pod-episode]').forEach(function (card) {
        var btn = $('.pod-ep__btn', card);
        var tpl = document.getElementById('pod-ep-' + card.getAttribute('data-pod-episode'));
        if (!btn || !tpl) return;
        btn.addEventListener('click', function () {
          content.textContent = '';
          content.appendChild(tpl.content.cloneNode(true));
          var player = $('[data-pod-player]', content);
          if (player) {
            var iframe = document.createElement('iframe');
            iframe.src = card.getAttribute('data-embed');
            iframe.title = 'Odtwarzacz Spotify: ' + card.getAttribute('data-title');
            iframe.width = '100%';
            iframe.height = '152';
            iframe.setAttribute('allow', 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture');
            iframe.setAttribute('allowfullscreen', '');
            iframe.loading = 'lazy';
            player.appendChild(iframe);
          }
          dialog.scrollTop = 0;
          open(btn);
        });
      });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
