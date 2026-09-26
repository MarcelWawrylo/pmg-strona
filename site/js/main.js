/* PMG — wspólne zachowania strony (wszystkie podstrony). Vanilla JS, bez zależności.
   Każdy moduł uruchamia się tylko, gdy na stronie jest jego element. */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  // katalog strony (site/) liczony od tego skryptu — dla wszystkich podstron
  var siteRoot = new URL('../', document.currentScript.src).href;

  // Odpowiedź backendu PHP (serwer PWr) albo null, gdy go nie ma (GitHub Pages zwraca plik .php jako tekst)
  var api = function (path, opts) {
    return fetch(siteRoot + 'api/' + path, opts).then(function (r) {
      return /json/.test(r.headers.get('content-type') || '') ? r.json() : null;
    }).catch(function () { return null; });
  };
  var newsPromise = null;
  var loadNews = function () {
    newsPromise = newsPromise || api('aktualnosci.php').then(function (d) { return d && d.wpisy && d.wpisy.length ? d.wpisy : null; });
    return newsPromise;
  };
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML.replace(/"/g, '&quot;'); };
  var fmtDate = function (iso) { var p = String(iso).split('-'); return p[2] + '.' + p[1] + '.' + p[0]; };
  // Polska odmiana liczebników: 1 -> one, 2-4 (poza 12-14) -> few, reszta -> many.
  var plural = function (n, one, few, many) {
    if (n === 1) return one;
    var r10 = n % 10, r100 = n % 100;
    return (r10 >= 2 && r10 <= 4 && (r100 < 10 || r100 >= 20)) ? few : many;
  };

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

  /* ---------- Listy rozwijane w menu (PM Session, Case Koła) ---------- */
  /* Desktop: otwiera najechanie, fokus i klik w strzałkę; Esc zamyka i wraca fokusem do strzałki.
     Mobile (< 961 px): akordeon, tylko klik w strzałkę. */
  function initSubnav() {
    var items = $$('[data-subnav]');
    if (!items.length) return;
    var desktop = window.matchMedia('(min-width: 961px)');
    var refocusing = false;

    items.forEach(function (item) {
      var btn = $('.site-nav__subtoggle', item);
      if (!btn) return;
      var closeTimer = null;
      var openedAt = 0;
      var setOpen = function (open) {
        clearTimeout(closeTimer);
        if (open && !item.classList.contains('is-open')) openedAt = Date.now();
        item.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) items.forEach(function (other) { if (other !== item) other.__subnavClose(); });
      };
      item.__subnavClose = function () { setOpen(false); };

      item.addEventListener('mouseenter', function () { if (desktop.matches) setOpen(true); });
      item.addEventListener('mouseleave', function () {
        if (!desktop.matches || item.contains(document.activeElement)) return;
        closeTimer = setTimeout(function () { setOpen(false); }, 180);
      });
      item.addEventListener('focusin', function () { if (desktop.matches && !refocusing) setOpen(true); });
      item.addEventListener('focusout', function (e) {
        if (desktop.matches && !item.contains(e.relatedTarget)) setOpen(false);
      });
      btn.addEventListener('click', function () {
        var open = item.classList.contains('is-open');
        // klik tuż po otwarciu najechaniem/fokusem nie zamyka listy od razu
        if (open && desktop.matches && Date.now() - openedAt < 400) return;
        setOpen(!open);
      });
      item.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !item.classList.contains('is-open')) return;
        e.stopPropagation(); // nie zamykaj przy okazji całego menu mobilnego
        setOpen(false);
        refocusing = true; btn.focus(); refocusing = false;
      });
    });

    document.addEventListener('click', function (e) {
      items.forEach(function (item) { if (!item.contains(e.target)) item.__subnavClose(); });
    });
    var onMq = function () { items.forEach(function (item) { item.__subnavClose(); }); };
    if (desktop.addEventListener) desktop.addEventListener('change', onMq); else desktop.addListener(onMq);
  }

  /* ---------- Ustawienia strony z panelu: linki społecznościowe, e-mail, rekrutacja ---------- */
  /* Fallback: brak backendu / brak danych / pusta wartość klucza = element zostaje bez zmian (statyczny HTML). */
  function initSettings() {
    var els = $$('[data-set]');
    if (!els.length) return;
    api('ustawienia.php').then(function (data) {
      if (!data) return;
      els.forEach(function (el) {
        var v = data[el.getAttribute('data-set')];
        if (!v) return;
        if (el.tagName === 'A') {
          if (el.getAttribute('data-set') === 'email') {
            el.href = 'mailto:' + v;
            if (el.childElementCount === 0) el.textContent = v;
          } else if (v.indexOf('https://') === 0) {
            el.href = v; // obrona w głębi — url_ok() w panelu już to wymusza
          }
        } else {
          el.textContent = v;
        }
      });
      if (data.email) {
        $$('[data-contact-form]').forEach(function (f) { f.setAttribute('data-mailto', data.email); });
      }
      if (data.rekrutacja_otwarta === '0') {
        $$('[data-set="rekrutacja_link"], .join-page-form__help').forEach(function (el) { el.hidden = true; });
      }
    });
  }

  /* ---------- Struktura koła (O nas): zarząd + sekcje z panelu ---------- */
  /* Fallback: brak backendu / błąd / pusta lista sekcji i pusty zarząd = strona zostaje statyczna.
     Zarząd i sekcje aktualizowane niezależnie — jeśli jedna z list jest pusta, ten fragment zostaje bez zmian.
     .about-sub (liczba osób) NIE jest tu dotykane — zostaje tekstem statycznym. */
  function initTeam() {
    var boardList = $('.about-board__list');
    var sectionsList = $('.about-sections');
    if (!boardList && !sectionsList) return;

    var MAIL_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.5 7 8.5 6.5L20.5 7"/></svg>';
    var LI_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="fill:currentColor;stroke:none"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.064 2.064 0 1 1 0-4.128 2.064 2.064 0 0 1 0 4.128zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';

    var icons = function (o, klasa) {
      var name = esc(o.imie + ' ' + o.nazwisko);
      var html = '<a class="about-ico' + klasa + '" href="mailto:' + esc(o.email) + '" title="' + esc(o.email) + '" aria-label="Napisz e-mail do: ' + name + '">' + MAIL_SVG + '</a>';
      if (o.linkedin && o.linkedin.indexOf('https://') === 0) {
        html += '<a class="about-ico' + klasa + '" href="' + esc(o.linkedin) + '" target="_blank" rel="noopener" aria-label="Profil LinkedIn: ' + name + '">' + LI_SVG + '</a>';
      }
      return html;
    };
    var fullName = function (o) { return esc(o.imie + ' ' + o.nazwisko); };

    var boardCard = function (o) {
      return '<li class="about-board__card about-person"><p class="about-board__who"><span class="about-board__role">' + esc(o.funkcja) + '</span> <span class="about-board__name">' + fullName(o) + '</span></p>' +
        '<div class="about-act">' + icons(o, ' about-ico--light') + '</div></li>';
    };
    var coordBlock = function (o) {
      return '<div class="about-coord about-person"><p class="about-coord__label">' + esc(o.funkcja) + '</p>' +
        '<div class="about-coord__row"><p class="about-coord__name">' + fullName(o) + '</p><span class="about-act">' + icons(o, '') + '</span></div></div>';
    };
    var memberItem = function (o) {
      return '<li class="about-member about-person"><span class="about-member__name">' + fullName(o) + '</span><span class="about-act">' + icons(o, '') + '</span></li>';
    };
    var sectionCard = function (s) {
      var n = s.koordynatorzy.length + s.czlonkowie.length;
      var czlonkowie = s.czlonkowie.slice().sort(function (a, b) {
        return a.nazwisko.localeCompare(b.nazwisko, 'pl') || a.imie.localeCompare(b.imie, 'pl');
      });
      var members = czlonkowie.length ? '<ul class="about-sec__members" aria-label="Członkowie sekcji ' + esc(s.nazwa) + '">' + czlonkowie.map(memberItem).join('') + '</ul>' : '';
      return '<li class="about-sec about-sec--' + esc(s.kolor) + '"><div class="about-sec__head"><h3 class="about-sec__name">' + esc(s.nazwa) + '</h3>' +
        '<p class="about-sec__count">' + n + ' ' + plural(n, 'osoba', 'osoby', 'osób') + '</p></div>' +
        '<div class="about-sec__body">' + s.koordynatorzy.map(coordBlock).join('') + members + '</div></li>';
    };

    api('czlonkowie.php').then(function (data) {
      if (!data) return;
      if (boardList && data.zarzad && data.zarzad.length) boardList.innerHTML = data.zarzad.map(boardCard).join('');
      if (sectionsList && data.sekcje && data.sekcje.length) sectionsList.innerHTML = data.sekcje.map(sectionCard).join('');
    });
  }

  /* ---------- PM Session: bieżąca edycja z panelu (baner, prelegenci, harmonogram, liczby przez data-set) ---------- */
  /* Fallback: brak backendu / błąd / edycja: null = strona zostaje statyczna. Prelegenci i harmonogram
     aktualizowane niezależnie od banera — pusta lista jednego z nich zostawia odpowiedni fragment statyczny. */
  function initPmSession() {
    var speakersList = $('.speakers');
    var scheduleList = $('.schedule');
    if (!speakersList && !scheduleList) return;

    var speakerCard = function (p, i) {
      var name = esc(p.imie_nazwisko);
      var id = 'spk-' + (i + 1);
      var img = p.zdjecie
        ? '<img class="speaker__img" src="' + esc(siteRoot + p.zdjecie) + '" width="560" height="560" alt="' + esc(p.zdjecie_alt) + '" loading="lazy" decoding="async">'
        : '<div class="speaker__img" aria-hidden="true"></div>';
      var note = p.notatka ? '<p class="speaker__note">' + esc(p.notatka) + '</p>' : '';
      var links = (p.linkedin && p.linkedin.indexOf('https://') === 0)
        ? '<p class="speaker__links"><a class="chip-link" href="' + esc(p.linkedin) + '" target="_blank" rel="noopener">LinkedIn <span aria-hidden="true">↗</span><span class="visually-hidden"> — ' + name + ' (otwiera się w nowej karcie)</span></a></p>'
        : '';
      return '<li class="speaker" data-expand>' + img +
        '<h3 class="speaker__name"><button class="expand-toggle" type="button" aria-expanded="false" aria-controls="' + id + '">' + name + '<span class="visually-hidden"> — pokaż biogram</span></button></h3>' +
        note + '<p class="speaker__topic">' + esc(p.temat) + '</p>' +
        '<div class="speaker__more" id="' + id + '"><div class="speaker__more-inner"><p class="speaker__bio">' + esc(p.bio) + '</p>' + links + '</div></div></li>';
    };

    var scheduleRow = function (items) {
      var time = items[0].godzina;
      if (items.length === 1) {
        var it = items[0];
        var body = it.prelegent ? esc(it.prelegent) + ' <span class="muted">' + esc(it.tytul) + '</span>' : esc(it.tytul);
        return '<li class="schedule__row"><p class="schedule__time">' + esc(time) + '</p><p class="schedule__item">' + body + '</p></li>';
      }
      var tag = items.length + ' ' + plural(items.length, 'sesja', 'sesje', 'sesji') + '<br> równoległe';
      var sessions = items.map(function (it) {
        return '<li class="schedule__session"><p class="schedule__who">' + esc(it.prelegent) + '</p><p class="schedule__what">' + esc(it.tytul) + '</p></li>';
      }).join('');
      return '<li class="schedule__row schedule__row--parallel"><p class="schedule__time">' + esc(time) + '<span class="schedule__tag">' + tag + '</span></p><ul class="schedule__sessions">' + sessions + '</ul></li>';
    };

    api('pmsession.php').then(function (data) {
      if (!data || !data.edycja) return;
      var ed = data.edycja;

      var titleEl = $('.pms-banner__title');
      if (titleEl) titleEl.textContent = 'PM Session ' + ed.numer;
      var pillEl = $('.date-pill');
      if (pillEl) {
        var p = String(ed.data).split('-');
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        pillEl.textContent = d.toLocaleDateString('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' }) + ' · ' + ed.miejsce;
      }
      var themeEl = $('.pms-banner__theme');
      if (themeEl) themeEl.textContent = ed.temat;
      document.title = document.title.replace(/PM Session \S+/, 'PM Session ' + ed.numer);

      if (speakersList && data.prelegenci && data.prelegenci.length) {
        speakersList.innerHTML = data.prelegenci.map(speakerCard).join('');
        initNewsTiles(speakersList);
      }
      if (scheduleList && data.harmonogram && data.harmonogram.length) {
        var groups = [];
        data.harmonogram.forEach(function (h) {
          var last = groups[groups.length - 1];
          if (last && last[0].godzina === h.godzina) last.push(h); else groups.push([h]);
        });
        scheduleList.innerHTML = groups.map(scheduleRow).join('');
      }
    });
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
  window.PMG = { wireDialog: wireDialog, $: $, $$: $$, reduceMotion: reduceMotion, root: siteRoot, loadNews: loadNews, esc: esc, fmtDate: fmtDate };

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

  /* ---------- Kontakt: wysyłka przez api/kontakt.php; bez backendu (GitHub Pages) → link mailto ---------- */
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
      var send = $('.contact-form__send', form);
      send.disabled = true;
      status.textContent = 'Wysyłamy wiadomość…';
      api('kontakt.php', { method: 'POST', body: new FormData(form) }).then(function (res) {
        send.disabled = false;
        if (res && res.ok) {
          form.reset();
          status.textContent = 'Dziękujemy! Wiadomość wysłana — odpowiemy jak najszybciej.';
        } else if (res) {
          status.textContent = res.error || 'Nie udało się wysłać wiadomości.';
        } else {
          var bodyText = v('message') + '\n\n—\n' + v('name') + '\n' + v('email');
          status.textContent = 'Otwieramy Twój program pocztowy z gotową wiadomością…';
          window.location.href = 'mailto:' + form.getAttribute('data-mailto') +
            '?subject=' + encodeURIComponent(v('subject')) + '&body=' + encodeURIComponent(bodyText);
        }
      });
    });
  }

  /* ---------- „Zgłoś błąd” w stopce → formularz kontaktowy z tematem i adresem strony ---------- */
  /* Link dostaje &strona=<ścieżka bieżącej strony> (sama ścieżka, bez parametrów i danych osobowych).
     Na kontakt.html?temat=blad pusty temat i wiadomość są wstępnie wypełniane. */
  function initReportBug() {
    $$('a[data-report-bug]').forEach(function (a) {
      var url = new URL(a.getAttribute('href'), location.href);
      url.searchParams.set('strona', location.pathname);
      a.href = url.href;
    });
    var form = $('[data-contact-form]');
    if (!form) return;
    var params = new URLSearchParams(location.search);
    if (params.get('temat') !== 'blad') return;
    var subject = $('[name="subject"]', form);
    var message = $('[name="message"]', form);
    if (subject && !subject.value) subject.value = 'Zgłoszenie błędu na stronie';
    var strona = params.get('strona') || '';
    if (message && !message.value && /^\/[\w\/.-]*$/.test(strona)) message.value = 'Strona: ' + strona + '\n\n';
  }

  /* ---------- Rozwijane karty: kafelki aktualności, prelegenci PMS (hover rozwija, klik/dotyk przypina) ---------- */
  function initNewsTiles(scope) {
    $$('[data-news], [data-expand]', scope).forEach(function (tile) {
      var btn = $('.news-tile__toggle, .expand-toggle', tile);
      if (!btn) return;
      btn.addEventListener('click', function () {
        var open = !tile.classList.contains('is-open');
        tile.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  /* ---------- Strona główna: 3 najnowsze wpisy z panelu (gdy backend działa) ---------- */
  function initHomeNews() {
    var grid = $('.news-grid');
    if (!grid) return;
    loadNews().then(function (posts) {
      if (!posts) return;
      grid.innerHTML = posts.slice(0, 3).map(function (p) {
        var img = p.zdjecie ? '<img src="' + esc(siteRoot + p.zdjecie) + '" alt="" loading="lazy">' : '<span>[ zdjęcie 16:9 ]</span>';
        var href = 'aktualnosci.html#wpis-' + esc(p.slug);
        return '<li class="news-tile"><div class="news-tile__img news-tile__img--' + esc(p.kolor) + '" aria-hidden="true">' + img + '</div>' +
          '<div class="news-tile__body"><p class="news-tile__date"><time datetime="' + esc(p.data) + '">' + esc(fmtDate(p.data)) + '</time></p>' +
          '<h3 class="news-tile__title"><a class="news-tile__link" href="' + href + '">' + esc(p.tytul) + '</a></h3>' +
          '<p class="news-tile__more">' + esc(p.zajawka) + '</p>' +
          '<span class="news-tile__cta" aria-hidden="true">Czytaj więcej →</span></div></li>';
      }).join('');
    });
  }

  /* ---------- Aktualności: pas trawy z polną myszą przed stopką (dekoracja, aria-hidden) ----------
     Bez JS pas pokazuje statyczną trawę z tła CSS. Tu budujemy SVG na szerokość pasa: tylna warstwa
     źdźbeł, mysz, przednia warstwa (mysz wychodzi Z trawy). Źdźbła falują animacją CSS (.is-live
     włącza animation-play-state: running); mysz animuje Web Animations API (tylko transform).
     IntersectionObserver: poza ekranem brak .is-live, timer wyczyszczony, animacje myszy anulowane.
     prefers-reduced-motion: trawa nieruchoma, mysz stale wygląda z trawy, zero timerów. */
  function initGrass() {
    var strip = $('[data-grass]');
    if (!strip || !window.SVGElement) return;
    var NS = 'http://www.w3.org/2000/svg';
    var still = reduceMotion.matches;
    var svg, mouse, body, ear, eye, whisk, W = 0, H = 0;
    var timer = 0, anims = [], live = false, spots = [];

    // stały „losowy” układ źdźbeł (ten sam przy każdym wejściu)
    var rng = function (seed) { return function () { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; }; };
    var blade = function (x, h, w, lean) {
      var b = H + 2, t = b - h;
      return 'M' + (x - w / 2).toFixed(1) + ' ' + b +
        'Q' + (x - w * 0.3 + lean * 0.35).toFixed(1) + ' ' + (b - h * 0.55).toFixed(1) + ' ' + (x + lean).toFixed(1) + ' ' + t.toFixed(1) +
        'Q' + (x + w * 0.3 + lean * 0.5).toFixed(1) + ' ' + (b - h * 0.5).toFixed(1) + ' ' + (x + w / 2).toFixed(1) + ' ' + b + 'Z';
    };
    var layer = function (cls, seed, step, hMin, hMax, fills) {
      var r = rng(seed), out = '', i = 0, k = H / 110;
      for (var x = -6; x < W + 12; x += step * (0.7 + r() * 0.6), i++) {
        var h = (hMin + r() * (hMax - hMin)) * k;
        out += '<path class="grass-strip__blade" style="animation-delay:' + (-(i * 0.15) % 3.6).toFixed(2) + 's" fill="url(#' + fills[Math.floor(r() * fills.length)] + ')" d="' +
          blade(x, h, 7 + r() * 7, (r() - 0.5) * h * 0.45) + '"/>';
      }
      return '<g class="' + cls + '">' + out + '</g>';
    };
    var grad = function (id, bottom, top) {
      return '<linearGradient id="' + id + '" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="' + bottom + '"/><stop offset="1" stop-color="' + top + '"/></linearGradient>';
    };
    // polna mysz patrząca w prawo; (0,0) = środek podstawy tułowia
    var MOUSE =
      '<g class="grass-strip__mouse-in">' +
        '<path d="M-17 40V-12C-17 -26 -8 -32 1 -32C10 -32 17 -25 17 -12V40Z" fill="#8a7560"/>' +
        '<ellipse cx="7" cy="-11" rx="8" ry="12" fill="#c9b8a3"/>' +
        '<g class="grass-strip__ear-back"><ellipse cx="-2" cy="-45" rx="8" ry="8.5" fill="#7a6552"/><ellipse cx="-1.5" cy="-45" rx="4.8" ry="5.3" fill="#e8a5a5"/></g>' +
        '<path d="M-5 -33C-5 -42 3 -47 11 -45C17 -43.5 22 -38 28 -33.5C23 -29 17 -26.5 10 -26C2 -25.5 -5 -27.5 -5 -33Z" fill="#8a7560"/>' +
        '<g class="grass-strip__ear"><ellipse cx="9" cy="-49" rx="8.5" ry="9" fill="#8a7560"/><ellipse cx="9.5" cy="-48.5" rx="5.2" ry="5.8" fill="#e8a5a5"/></g>' +
        '<ellipse cx="11" cy="-29" rx="3.5" ry="2.2" fill="#e8a5a5" opacity=".55"/>' +
        '<g class="grass-strip__eye"><circle cx="15.5" cy="-36" r="2.5" fill="#141414"/><circle cx="16.4" cy="-37" r=".85" fill="#fff"/></g>' +
        '<circle cx="28" cy="-33.5" r="2.2" fill="#e27d8e"/>' +
        '<g class="grass-strip__whiskers" stroke="#3b3027" stroke-width=".8" stroke-linecap="round" fill="none"><path d="M25 -32L37 -35.5M25 -31.5L37.5 -31M24.5 -31L36 -27"/></g>' +
        '<ellipse cx="9" cy="-19" rx="3.2" ry="2.4" fill="#d9c4b0"/><ellipse cx="16" cy="-19.5" rx="3.2" ry="2.4" fill="#d9c4b0"/>' +
      '</g>';

    var build = function () {
      var w = strip.clientWidth, h = strip.clientHeight;
      if (!w || !h || (w === W && h === H && svg)) return;
      stop();
      W = w; H = h;
      var small = W < 641;
      var s = small ? 0.86 : 1.05;
      // miejsca, w których mysz może wyskoczyć (co ok. 180–260 px, z dala od krawędzi)
      spots = [];
      for (var x = 80; x < W - 80; x += small ? 110 : 230) spots.push(Math.round(x + (spots.length % 2 ? 25 : -15)));
      if (!spots.length) spots.push(Math.round(W / 2));
      strip.innerHTML = '<svg class="grass-strip__svg" viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" focusable="false" aria-hidden="true" xmlns="' + NS + '">' +
        '<defs>' + grad('gs-back', '#4f8a00', '#76b000') + grad('gs-back2', '#5a9400', '#80b800') +
          grad('gs-front', '#62a000', '#8cc400') + grad('gs-front2', '#6eac00', '#a0d000') + '</defs>' +
        layer('grass-strip__back', 7, small ? 12 : 16, 60, 92, ['gs-back', 'gs-back2']) +
        '<g class="grass-strip__mouse" transform="translate(' + spots[Math.floor(spots.length / 3)] + ' ' + H + ') scale(' + s + ')">' + MOUSE + '</g>' +
        layer('grass-strip__front', 13, small ? 11 : 14, 26, 50, ['gs-front', 'gs-front2', 'gs-front']) +
        '<rect x="0" y="' + (H - 6) + '" width="' + W + '" height="6" fill="#4f8a00"/>' +
      '</svg>';
      svg = strip.firstChild;
      mouse = $('.grass-strip__mouse', svg);
      body = $('.grass-strip__mouse-in', svg);
      ear = $('.grass-strip__ear', svg);
      eye = $('.grass-strip__eye', svg);
      whisk = $('.grass-strip__whiskers', svg);
      strip.classList.add('is-ready');
      if (still) body.style.transform = 'translateY(-10px)';
      else if (live) schedule(1200);
    };

    var place = function () {
      var x = spots[Math.floor(Math.random() * spots.length)];
      var flip = Math.random() < 0.5 ? -1 : 1;
      var s = W < 641 ? 0.86 : 1.05;
      mouse.setAttribute('transform', 'translate(' + x + ' ' + H + ') scale(' + (s * flip) + ' ' + s + ')');
    };
    // ukryta → wyskok 400 ms (ease-out z overshootem) → pauza 1200 ms (ucho, wąsy, mrugnięcie) → schowanie 350 ms (ease-in)
    var popUp = function () {
      timer = 0;
      if (!live) return;
      place();
      var T = 1950;
      anims = [
        body.animate([
          { transform: 'translateY(64px)', offset: 0, easing: 'cubic-bezier(.34,1.56,.64,1)' },
          { transform: 'translateY(-16px)', offset: 400 / T },
          { transform: 'translateY(-16px)', offset: 1600 / T, easing: 'cubic-bezier(.55,0,1,.45)' },
          { transform: 'translateY(64px)', offset: 1 }
        ], { duration: T }),
        ear.animate([
          { transform: 'rotate(0deg)', offset: 0 }, { transform: 'rotate(0deg)', offset: 0.36 },
          { transform: 'rotate(-14deg)', offset: 0.40 }, { transform: 'rotate(4deg)', offset: 0.44 },
          { transform: 'rotate(0deg)', offset: 0.48 }, { transform: 'rotate(0deg)', offset: 1 }
        ], { duration: T }),
        eye.animate([
          { transform: 'scaleY(1)', offset: 0 }, { transform: 'scaleY(1)', offset: 0.55 },
          { transform: 'scaleY(.1)', offset: 0.58 }, { transform: 'scaleY(1)', offset: 0.61 }, { transform: 'scaleY(1)', offset: 1 }
        ], { duration: T }),
        whisk.animate([
          { transform: 'rotate(0deg)' }, { transform: 'rotate(-6deg)' }, { transform: 'rotate(4deg)' },
          { transform: 'rotate(-5deg)' }, { transform: 'rotate(0deg)' }
        ], { duration: 600, delay: 900 })
      ];
      anims[0].onfinish = function () { anims = []; schedule(3500 + Math.random() * 2000); };
    };
    var schedule = function (ms) { clearTimeout(timer); timer = setTimeout(popUp, ms); };
    var stop = function () {
      clearTimeout(timer); timer = 0;
      anims.forEach(function (a) { a.onfinish = null; a.cancel(); });
      anims = [];
    };
    var setLive = function (on) {
      on = on && !still && !document.hidden;
      if (on === live) return;
      live = on;
      strip.classList.toggle('is-live', on);
      if (on) schedule(1200); else stop();
    };

    var visible = false;
    build();
    if (still) return;
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        visible = entries[entries.length - 1].isIntersecting;
        setLive(visible);
      }).observe(strip);
    }
    document.addEventListener('visibilitychange', function () { setLive(visible); });
    var rt = 0;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(build, 200); });
  }

  document.documentElement.classList.add('js');
  var init = function () {
    initNewsTiles();
    initHomeNews();
    initSettings();
    initTeam();
    initPmSession();
    initNav();
    initSubnav();
    initToTop();
    initReveal();
    initLightbox();
    initContactForm();
    initReportBug();
    initGrass();
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
    if (PMG.loadNews) PMG.loadNews().then(function (posts) { if (posts) render(posts); route(); }); else route();

    // Wpisy z panelu zastępują statyczne. Wszystkie pola przechodzą przez esc(); treść: akapity
    // rozdzielone pustą linią, „## ” na początku = śródtytuł.
    function render(posts) {
      var esc = PMG.esc, fmt = PMG.fmtDate;
      var ph = { pink: 'pms', purple: 'podcast', blue: 'case', violet: 'integracja' };
      var img = function (p, cls, withAlt) {
        var alt = withAlt && p.zdjecie_alt;
        return '<div class="blog-ph blog-ph--' + (ph[p.kolor] || 'pms') + ' ' + cls + '"' + (p.zdjecie && alt ? '' : ' aria-hidden="true"') + '>' +
          (p.zdjecie ? '<img src="' + esc(PMG.root + p.zdjecie) + '" alt="' + (alt ? esc(p.zdjecie_alt) : '') + '">' : '[ zdjęcie 16:9 ]') + '</div>';
      };
      var meta = function (p, cls) {
        return '<div class="blog-meta' + (cls || '') + '"><span class="blog-cat blog-cat--' + esc(p.kolor) + '">' + esc(p.kategoria) + '</span>' +
          '<span class="blog-date"><time datetime="' + esc(p.data) + '">' + esc(fmt(p.data)) + '</time></span></div>';
      };
      var body = function (t) {
        return String(t).split(/\n\s*\n/).map(function (b) {
          b = b.trim();
          return /^## /.test(b) ? '<h2 class="blog-article__h2">' + esc(b.slice(3)) + '</h2>' : '<p class="blog-article__p">' + esc(b).replace(/\n/g, '<br>') + '</p>';
        }).join('');
      };
      var link = function (p, tag, cls) {
        return '<' + tag + ' class="' + cls + '"><a class="blog-link" href="#wpis-' + esc(p.slug) + '">' + esc(p.tytul) + '</a></' + tag + '>';
      };
      var f = posts[0];
      $('.blog-featured', list).innerHTML = img(f, 'blog-featured__img') + '<div class="blog-featured__body">' + meta(f) + link(f, 'h2', 'blog-featured__title') +
        '<p class="blog-featured__excerpt">' + esc(f.zajawka) + '</p><p class="blog-more blog-more--' + esc(f.kolor) + '" aria-hidden="true">Czytaj więcej →</p></div>';
      $('.blog-grid', list).innerHTML = posts.slice(1).map(function (p) {
        return '<li class="blog-card" data-blog-card>' + img(p, 'blog-card__img') + '<div class="blog-card__body">' + meta(p) + link(p, 'h3', 'blog-card__title') +
          '<p class="blog-card__excerpt">' + esc(p.zajawka) + '</p><p class="blog-more blog-more--' + esc(p.kolor) + '" aria-hidden="true">Czytaj więcej →</p></div></li>';
      }).join('');
      $('.blog-posts', list).hidden = posts.length < 2;
      wrap.innerHTML = posts.map(function (p) {
        var id = 'wpis-' + esc(p.slug);
        return '<article id="' + id + '" class="blog-article" aria-labelledby="' + id + '-title">' +
          '<a class="blog-back" href="#aktualnosci" data-blog-back><span aria-hidden="true">←</span> Wszystkie aktualności</a>' + meta(p, ' blog-meta--article') +
          '<h1 class="blog-article__title" id="' + id + '-title" tabindex="-1">' + esc(p.tytul) + '</h1>' +
          '<p class="blog-article__lead">' + esc(p.zajawka) + '</p>' + img(p, 'blog-article__img', true) +
          '<div class="blog-article__body">' + body(p.tresc) + '</div><div class="blog-article__foot">' +
          (p.autor ? '<p class="blog-article__author">Autor: <b>' + esc(p.autor) + '</b></p>' : '<span></span>') +
          '<a class="btn btn--dark blog-article__back" href="#aktualnosci" data-blog-back><span aria-hidden="true">←</span> Wróć do listy</a></div></article>';
      }).join('');
    }

    function route() {
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
    }
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
