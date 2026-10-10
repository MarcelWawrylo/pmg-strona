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
  // Obraz z API: obraz = {src, srcset, webp, w, h} (warianty z img/, jak w HTML) -> <picture> z WebP; obraz = null (zdjęcie wgrane
  // z panelu albo brak wariantów) -> zwykły <img> z pliku `path`. o = {cls, sizes, w, h, alt, lazy}; sizes/cls pochodzą z kodu, nie z danych.
  var pictureHtml = function (obraz, path, o) {
    var w = o.w || (obraz && obraz.w) || o.fw || 0, h = o.h || (obraz && obraz.h) || o.fh || 0;
    var cls = o.cls ? ' class="' + o.cls + '"' : '';
    var sizes = o.sizes ? ' sizes="' + o.sizes + '"' : '';
    var dim = w && h ? ' width="' + w + '" height="' + h + '"' : '';
    var tail = ' alt="' + esc(o.alt || '') + '"' + (o.lazy ? ' loading="lazy"' : '') + ' decoding="async">';
    var pic = o.pictureCls ? ' class="' + o.pictureCls + '"' : '';
    if (!obraz) {
      var plain = '<img' + cls + ' src="' + esc(siteRoot + path) + '"' + dim + tail;
      return o.pictureCls ? '<picture' + pic + '>' + plain + '</picture>' : plain;
    }
    var abs = function (set) { return set.split(', ').map(function (e) { return siteRoot + e; }).join(', '); };
    return '<picture' + pic + '>' + (obraz.webp ? '<source type="image/webp" srcset="' + esc(abs(obraz.webp)) + '"' + sizes + '>' : '') +
      '<img' + cls + ' src="' + esc(siteRoot + obraz.src) + '" srcset="' + esc(abs(obraz.srcset)) + '"' + sizes + dim + tail + '</picture>';
  };
  // Tekst wieloakapitowy z panelu (akapity oddzielone pustą linią) -> <p class="cls"> na akapit
  var paragraphsHtml = function (text, cls) {
    return String(text == null ? '' : text).split(/\n\s*\n/).map(function (b) { return b.trim(); }).filter(Boolean).map(function (b) {
      return '<p class="' + cls + '">' + esc(b) + '</p>';
    }).join('');
  };
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

    // Chowanie headera: w dół poza jego wysokością chowa (transform), w górę (próg 8 px, bez drgania) pokazuje.
    // Wymuszone pokazanie (menu/podmenu otwarte, fokus w nav, otwarty dialog) robi CSS (.site-nav.is-hidden…).
    // Miejsce, które header zajmuje w układzie strony, nie zmienia się przy is-scrolled (CSS), więc przeglądarka
    // nie przesuwa przewinięcia; histereza progu (włącz > 32 px, wyłącz < 8 px) — drobne ruchy przy górze nie przełączają.
    var lastY = window.scrollY || document.documentElement.scrollTop || 0;
    var onScroll = function () {
      var y = window.scrollY || document.documentElement.scrollTop || 0;
      if (y > 32) nav.classList.add('is-scrolled');
      else if (y < 8) nav.classList.remove('is-scrolled');
      if (themeSwitch) {
        // Dołącz: ciemny nav nad ciemnym hero, jasny po zjechaniu z hero
        var bottom = themeSwitch.getBoundingClientRect().bottom;
        nav.classList.toggle('site-nav--dark', bottom > nav.offsetHeight);
      }
      var delta = y - lastY;
      if (y <= nav.offsetHeight) { nav.classList.remove('is-hidden'); lastY = y; }
      else if (delta > 8) { nav.classList.add('is-hidden'); lastY = y; }
      else if (delta < -8) { nav.classList.remove('is-hidden'); lastY = y; }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var toggle = $('.site-nav__toggle', nav);
    var menu = $('.site-nav__menu', nav);
    if (!toggle || !menu) return;
    // stała nazwa przycisku; stan (otwarte/zamknięte) przekazuje tylko aria-expanded
    toggle.setAttribute('aria-label', 'Menu');

    // ≤ 960 px: „Dołącz” zawsze widoczne w pasku (na desktopie jest w samym menu) — kopia linku z menu,
    // więc HTML podstron się nie zmienia; na stronie Dołącz (aria-current) kopii nie ma
    var cta = $('.site-nav__cta', menu);
    if (cta && !cta.hasAttribute('aria-current')) {
      var barCta = cta.cloneNode(true);
      barCta.classList.add('site-nav__bar-cta');
      toggle.parentNode.insertBefore(barCta, toggle);
    }

    // ≤ 960 px menu jest panelem na cały ekran: reszta strony dostaje inert (niedostępna dla fokusu
    // i czytnika), tło się nie przewija, Tab krąży po nagłówku
    var mobile = window.matchMedia('(max-width: 960px)');
    var inerted = [];
    var setInert = function (on) {
      if (on) {
        for (var el = nav; el.parentElement && el !== document.body; el = el.parentElement) {
          Array.prototype.forEach.call(el.parentElement.children, function (sib) {
            if (sib !== el && !sib.inert && !/^(SCRIPT|STYLE|TEMPLATE)$/.test(sib.tagName)) { sib.inert = true; inerted.push(sib); }
          });
        }
      } else {
        inerted.forEach(function (sib) { sib.inert = false; });
        inerted = [];
      }
    };
    var setOpen = function (open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      var modal = open && mobile.matches;
      document.documentElement.classList.toggle('nav-open', modal);
      setInert(modal);
    };
    var focusables = function () {
      return $$('a[href], button:not([disabled])', nav).filter(function (el) {
        return el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
      });
    };
    toggle.addEventListener('click', function () { setOpen(!nav.classList.contains('is-open')); });
    // klik w link w panelu zamyka menu (także kotwice na tej samej stronie)
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (!nav.classList.contains('is-open')) return;
      if (e.key === 'Escape') { setOpen(false); toggle.focus(); return; }
      if (e.key !== 'Tab' || !mobile.matches) return;
      var items = focusables();
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1], active = document.activeElement;
      if (e.shiftKey && (active === first || !nav.contains(active))) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && (active === last || !nav.contains(active))) { e.preventDefault(); first.focus(); }
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

  /* ---------- Teksty stron z panelu („Treści stron”): elementy z data-tresc="strona.klucz" ---------- */
  /* Fallback: brak backendu / brak wartości klucza / pusta wartość = element zostaje z tekstem z HTML.
     Wartość to zwykły tekst wstawiany jako węzły tekstowe (nigdy HTML): nowa linia = <br>; w <p> pusta linia = kolejny <p>
     (kopia elementu z tą samą klasą). Podmieniamy tylko początkowy tekst elementu (i <br>), do pierwszego elementu
     potomnego — ozdobne <span aria-hidden> (strzałka) zostają. window.PMG.tresciReady kończy się po podmianie
     (v2.js czeka na nią z animacją „słowo po słowie”, która czyta tekst akapitu). */
  var fillLines = function (parent, text, before) {
    text.split('\n').forEach(function (line, i) {
      if (i) parent.insertBefore(document.createElement('br'), before);
      parent.insertBefore(document.createTextNode(line.trim()), before);
    });
  };
  var applyTresc = function (el, v) {
    if (typeof v !== 'string') return;
    var blocks = v.replace(/\r\n?/g, '\n').split(/\n[ \t]*\n/).map(function (b) { return b.trim(); }).filter(Boolean);
    if (!blocks.length) return;
    var isP = el.tagName === 'P';
    var node = el.firstChild, lastText = '';
    while (node && (node.nodeType === 3 || (node.nodeType === 1 && node.tagName === 'BR'))) {
      var next = node.nextSibling;
      if (node.nodeType === 3) lastText = node.nodeValue;
      el.removeChild(node);
      node = next;
    }
    fillLines(el, isP ? blocks[0] : blocks.join('\n'), node);
    if (node && /\s$/.test(lastText)) el.insertBefore(document.createTextNode(' '), node);
    if (!isP) return;
    var prev = el;
    blocks.slice(1).forEach(function (b) {
      var extra = el.cloneNode(false);
      ['data-tresc', 'id', 'data-reveal'].forEach(function (a) { extra.removeAttribute(a); });
      fillLines(extra, b, null);
      prev.parentNode.insertBefore(extra, prev.nextSibling);
      prev = extra;
    });
  };
  function initTresci() {
    var els = $$('[data-tresc]');
    var done = function () {};
    window.PMG.tresciReady = new Promise(function (resolve) { done = resolve; });
    if (!els.length) { done(); return; }
    api('tresci.php').then(function (data) {
      var map = data && data.tresci;
      if (map) els.forEach(function (el) { applyTresc(el, map[el.getAttribute('data-tresc')]); });
      done();
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

  /* ---------- PM Session „Czym jest”: taśmy tekstu w intro (style.css, .pms-intro__tape) ----------
     Taśmy przesuwają się tylko wtedy, gdy strona się przewija (prędkość zależna od przewijania, kierunek z data-dir,
     prędkość z data-speed). Bez GSAP: scroll + rAF; pętla działa tylko, dopóki wygładzona pozycja nie dogoni przewinięcia,
     więc gdy przewijanie stoi, nic się nie rusza. Każda taśma składa się z tylu powtórzeń, by objęła ekran i jedno
     powtórzenie zapasu; przesunięcie modulo szerokość powtórzenia, więc nigdy nie widać pustego końca.
     Telefon (≤ 640 px): wszystkie taśmy, tu wolniej (każda tak samo). Ograniczony ruch: taśmy stoją. */
  function initPmsTapes() {
    var section = $('[data-pms-intro]');
    var tapes = section ? $$('.pms-intro__tape', section) : [];
    if (!tapes.length) return;
    var narrow = window.matchMedia('(max-width: 640px)');
    var texts = [];   // tekst jednego powtórzenia każdej taśmy (z HTML, czytany raz)
    var items = [];   // zbudowane taśmy: track, szerokość powtórzenia, kierunek, prędkość
    var pos = 0;      // wygładzona pozycja przewijania (px)
    var raf = 0, rt = 0;

    var unit = function (text) {
      var span = document.createElement('span');
      span.className = 'pms-intro__unit';
      span.textContent = text;
      return span;
    };

    var build = function () {
      var vw = window.innerWidth || document.documentElement.clientWidth || 1280;
      items = [];
      tapes.forEach(function (tape, i) {
        var track = $('.pms-intro__track', tape);
        if (!track) return;
        if (texts[i] === undefined) {
          var first = $('.pms-intro__unit', track);
          texts[i] = first ? first.textContent : '';
        }
        if (!texts[i]) return;
        track.textContent = '';
        var probe = unit(texts[i]);
        track.appendChild(probe);
        var unitW = probe.getBoundingClientRect().width;
        if (!unitW) return; // taśma ukryta (display: none)
        var n = Math.max(2, Math.ceil(vw / unitW) + 1);
        for (var k = 1; k < n; k++) track.appendChild(unit(texts[i]));
        var speed = parseFloat(tape.getAttribute('data-speed')) || 0.5;
        items.push({
          track: track, unitW: unitW, val: '',
          dir: parseFloat(tape.getAttribute('data-dir')) < 0 ? -1 : 1,
          speed: narrow.matches ? speed * 0.6 : speed
        });
      });
    };

    // przesunięcia taśm dla bieżącej pozycji (tylko transform); lewa taśma jedzie w lewo, prawa w prawo, x w [-unitW, 0]
    var apply = function () {
      items.forEach(function (it) {
        var off = reduceMotion.matches ? 0 : pos * it.speed;
        off = ((off % it.unitW) + it.unitW) % it.unitW;
        var x = it.dir < 0 ? -off : off - it.unitW;
        var val = 'translate3d(' + x.toFixed(2) + 'px, 0, 0)';
        if (val !== it.val) { it.track.style.transform = val; it.val = val; }
      });
    };

    var frame = function () {
      raf = 0;
      var target = window.pageYOffset || document.documentElement.scrollTop || 0;
      if (reduceMotion.matches) { apply(); return; }
      pos += (target - pos) * 0.3;
      if (Math.abs(target - pos) < 0.05) pos = target;
      apply();
      if (pos !== target) raf = requestAnimationFrame(frame);
    };
    var rebuild = function () { build(); apply(); };

    pos = window.pageYOffset || document.documentElement.scrollTop || 0;
    rebuild();
    window.addEventListener('scroll', function () { if (!raf) raf = requestAnimationFrame(frame); }, { passive: true });
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(rebuild, 120); });
    // po wczytaniu Space Grotesk szerokość powtórzenia się zmienia — składamy taśmy od nowa
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(rebuild);
    if (reduceMotion.addEventListener) reduceMotion.addEventListener('change', rebuild);
  }

  /* ---------- PM Session: edycja z panelu (baner, prelegenci, harmonogram, liczby przez data-set) ---------- */
  /* Strona pm-session-xiv.html ma <body data-edycja="XIV">, pm-session-xv.html — "XV": pobieramy dokładnie tę edycję
     (api/pmsession.php?numer=…), nigdy „bieżącą” z innej strony. Bez data-edycja API zwraca bieżącą edycję.
     Fallback: brak backendu / błąd / edycja: null (nieznana albo szkic) = strona zostaje statyczna.
     Prelegenci i harmonogram aktualizowane niezależnie od banera — pusta lista jednego z nich zostawia
     odpowiedni fragment statyczny. Sekcje z [data-pms-dynamic] (ukryte w HTML) pokazują się dopiero po wypełnieniu.
     Edycja w statusie „Aktualna edycja – wkrótce więcej” (edycja.status === 'zapowiedz', API podaje tylko numer): nagłówek
     „PM Session <numer>” i box [data-pms-soon] jak w pm-session-xv.html, bez daty, tematu, prelegentów i harmonogramu. */
  function initPmSession() {
    var speakersList = $('.speakers');
    var scheduleTableBody = $('.schedule-table tbody');
    var scheduleList = $('.schedule'); // stary <ol> — tylko robocza v3
    var pageEdition = (document.body.getAttribute('data-edycja') || '').toUpperCase();
    if (!speakersList && !scheduleTableBody && !scheduleList && !pageEdition) return;
    // nowy markup (xiv+): kafelek-przycisk + wspólny dialog [data-speaker-modal]. Stary (v3): expand-toggle inline.
    var newSpeakerMarkup = !!$('[data-speaker-modal]');

    var speakerCard = function (p, i) {
      var name = esc(p.imie_nazwisko);
      var id = 'spk-' + (i + 1);
      if (newSpeakerMarkup) {
        var img2 = p.zdjecie
          ? pictureHtml(p.obraz_karta || p.obraz_modal, p.zdjecie, { cls: 'speaker__img', sizes: '(max-width: 640px) calc(50vw - 40px), 220px', w: 560, h: 560, lazy: true })
          : '<div class="speaker__img" aria-hidden="true"></div>';
        return '<li class="speaker"><button class="speaker__card" type="button" data-speaker-trigger data-speaker-tpl="' + id + '">' + img2 +
          '<span class="speaker__name">' + name + '</span></button></li>';
      }
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

    // treść dialogu prelegenta (nowy markup): jeden <template id="spk-N"> na osobę, jak w statycznym HTML.
    var speakerTemplate = function (p, i) {
      var name = esc(p.imie_nazwisko);
      var note = p.notatka ? '<p class="speaker__note">' + esc(p.notatka) + '</p>' : '';
      var linkedin = (p.linkedin && p.linkedin.indexOf('https://') === 0)
        ? '<a class="chip-link speaker-modal__linkedin" href="' + esc(p.linkedin) + '" target="_blank" rel="noopener">LinkedIn <span aria-hidden="true">↗</span><span class="visually-hidden"> — ' + name + ' (otwiera się w nowej karcie)</span></a>'
        : '';
      var photo = p.zdjecie
        ? pictureHtml(p.obraz_modal, p.zdjecie, { pictureCls: 'speaker-modal__media', cls: 'speaker-modal__img', sizes: '(max-width: 640px) calc(100vw - 24px), (max-width: 768px) calc(100vw - 48px), 595px', fw: 1600, fh: 1200 })
        : '';
      return '<template id="spk-' + (i + 1) + '">' + photo + '<div class="speaker-modal__head"><h2 class="pod-modal__title" id="speaker-modal-title">' + name + '</h2>' + linkedin + '</div>' + note +
        (p.bio ? '<h3 class="speaker-modal__label">' + (p.plec === 'k' ? 'O prelegentce' : 'O prelegencie') + '</h3>' + paragraphsHtml(p.bio, 'pod-modal__desc') : '') +
        '<h3 class="pod-modal__num">' + esc(p.temat) + '</h3>' + paragraphsHtml(p.opis, 'pod-modal__desc') + '</template>';
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

    var scheduleTableRows = function (items) {
      var time = items[0].godzina;
      // znacznik pod godziną (np. „3 sesje równoległe”) tylko wtedy, gdy wpisano go w panelu przy którymś punkcie tej godziny
      var tagText = '';
      items.forEach(function (it) { tagText = tagText || it.znacznik || ''; });
      var tag = tagText ? '<span class="schedule-table__tag">' + esc(tagText) + '</span>' : '';
      return items.map(function (it, i) {
        var first = i === 0 ? '<td' + (items.length > 1 ? ' rowspan="' + items.length + '"' : '') + '>' + esc(time) + tag + '</td>' : '';
        return '<tr>' + first + '<td>' + esc(it.tytul) + '</td><td>' + (it.prelegent ? esc(it.prelegent) : '–') + '</td></tr>';
      }).join('');
    };

    var showSoon = function (numer) {
      var titleEl = $('.pms-banner__title');
      if (titleEl) titleEl.textContent = 'PM Session ' + numer;
      document.title = document.title.replace(/PM Session \S+/, 'PM Session ' + numer);
      $$('.pms-banner__date, .pms-banner__theme, .pms-banner__lead').forEach(function (el) { el.hidden = true; });
      [speakersList, scheduleTableBody, scheduleList].forEach(function (list) { // statyczny program (np. xiv) też znika
        var sec = list && list.closest('section');
        if (sec) sec.hidden = true;
      });
      $$('[data-pms-wrap]').forEach(function (el) { el.hidden = true; });
      var soon = $$('[data-pms-soon]');
      if (soon.length) { soon.forEach(function (el) { el.hidden = false; }); return; } // pm-session-xv.html: box z HTML (tekst z panelu „Teksty na stronie”)
      var banner = $('.pms-banner');
      if (!banner) return;
      var box = document.createElement('div');
      box.className = 'pms-next';
      box.setAttribute('data-pms-soon', '');
      box.innerHTML = '<div class="container"><div class="pms-next__box"><div class="pms-next__icon"><picture><source type="image/webp" srcset="' +
        esc(siteRoot) + 'img/logo-pm-session-sygnet-56.webp 56w, ' + esc(siteRoot) + 'img/logo-pm-session-sygnet-112.webp 112w" sizes="56px"><img src="' +
        esc(siteRoot) + 'img/logo-pm-session-sygnet-56.png" srcset="' + esc(siteRoot) + 'img/logo-pm-session-sygnet-56.png 56w, ' + esc(siteRoot) +
        'img/logo-pm-session-sygnet-112.png 112w" sizes="56px" width="112" height="111" alt="Logo PM Session"></picture></div><p class="pms-next__text"></p></div></div>';
      box.querySelector('.pms-next__text').textContent = 'Więcej informacji o ' + numer + ' edycji konferencji PM Session wkrótce!';
      banner.parentNode.insertBefore(box, banner.nextSibling);
    };

    api('pmsession.php' + (/^[IVXLC]{1,10}$/.test(pageEdition) ? '?numer=' + pageEdition : '')).then(function (data) {
      if (!data || !data.edycja) return;
      var ed = data.edycja;
      if (pageEdition && ed.numer !== pageEdition) return; // zła edycja w odpowiedzi (np. stara pamięć podręczna) — nie nakładamy
      if (ed.status === 'zapowiedz') { showSoon(ed.numer); return; }

      var titleEl = $('.pms-banner__title');
      if (titleEl) titleEl.textContent = 'PM Session ' + ed.numer;
      var p = String(ed.data).split('-');
      var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
      var pillEl = $('.date-pill'); // stary — tylko robocza v3
      if (pillEl) pillEl.textContent = d.toLocaleDateString('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' }) + ' · ' + ed.miejsce;
      var dateTimeEl = $('.pms-banner__date time');
      if (dateTimeEl) {
        dateTimeEl.textContent = fmtDate(ed.data);
        dateTimeEl.setAttribute('datetime', ed.data);
      }
      var themeEl = $('.pms-banner__theme');
      if (themeEl) {
        themeEl.textContent = 'Temat: ';
        var themeEm = document.createElement('em');
        themeEm.textContent = ed.temat;
        themeEl.appendChild(themeEm);
      }
      var leadEl = $('.pms-banner__lead');
      if (leadEl && ed.opis) { leadEl.textContent = ed.opis; leadEl.hidden = false; }
      $$('.pms-banner__date, .pms-banner__theme').forEach(function (el) { el.hidden = false; });
      document.title = document.title.replace(/PM Session \S+/, 'PM Session ' + ed.numer);
      var reveal = function (list) { // sekcja ukryta w HTML (xv) pokazuje się, gdy ma dane z panelu
        var sec = list && list.closest('[data-pms-dynamic]');
        if (sec) sec.hidden = false;
        var wrap = list && list.closest('[data-pms-wrap]');
        if (wrap) wrap.hidden = false;
        $$('[data-pms-soon]').forEach(function (el) { el.hidden = true; }); // „wkrótce” znika, gdy są już dane
      };

      if (speakersList && data.prelegenci && data.prelegenci.length) {
        speakersList.innerHTML = data.prelegenci.map(speakerCard).join('');
        reveal(speakersList);
        if (newSpeakerMarkup) {
          var tplContainer = speakersList.parentNode;
          $$('template[id^="spk-"]', tplContainer).forEach(function (t) { t.remove(); });
          tplContainer.insertAdjacentHTML('beforeend', data.prelegenci.map(speakerTemplate).join(''));
        } else {
          initNewsTiles(speakersList);
        }
      }
      if ((scheduleTableBody || scheduleList) && data.harmonogram && data.harmonogram.length) {
        var groups = [];
        data.harmonogram.forEach(function (h) {
          var last = groups[groups.length - 1];
          if (last && last[0].godzina === h.godzina) last.push(h); else groups.push([h]);
        });
        if (scheduleTableBody) { scheduleTableBody.innerHTML = groups.map(scheduleTableRows).join(''); reveal(scheduleTableBody); }
        else scheduleList.innerHTML = groups.map(scheduleRow).join('');
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

  /* ---------- Płynne przewijanie do kotwic na tej samej stronie ---------- */
  /* Wyklucza skip-link (#main) i hashe Aktualności (#aktualnosci, #wpis-...), bo tam main.js
     sam obsługuje hashchange (routing lista/artykuł) — patrz initAktualnosci niżej.
     ≥ 961 px z aktywnym Lenis (v2.js): window.PMG.smoothScrollTo, ten sam offset co scrollToHash.
     W pozostałych przypadkach: natywny scrollIntoView + html { scroll-padding-top } z CSS. */
  function initAnchorScroll() {
    var EXCLUDE = /^#(main|aktualnosci|wpis-)/;
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest && e.target.closest('a[href^="#"]');
      if (!a) return;
      var hash = a.getAttribute('href');
      if (!hash || hash === '#' || EXCLUDE.test(hash)) return;
      var target;
      try { target = document.querySelector(hash); } catch (err) { return; }
      if (!target) return;
      e.preventDefault();
      if (decodeURIComponent(location.hash) !== hash) history.pushState(null, '', hash);
      var focused = false;
      var focusTarget = function () {
        if (focused) return;
        focused = true;
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
      };
      if (reduceMotion.matches) {
        target.scrollIntoView();
        focusTarget();
      } else if (window.PMG.smoothScrollTo) {
        window.PMG.smoothScrollTo(target, focusTarget);
      } else {
        target.scrollIntoView({ behavior: 'smooth' });
        window.addEventListener('scrollend', focusTarget, { once: true });
        setTimeout(focusTarget, 900); // zabezpieczenie na przeglądarki bez zdarzenia scrollend
      }
    });
  }

  /* ---------- Poziome pasy przewijane palcem (≤ 640 px: kafelki „Po co? / Dla kogo?…” na PM Session) ----------
     Pas bez linków w środku nie dostałby fokusu, więc z klawiatury (np. Safari) nie dałoby się go przewinąć:
     gdy treść faktycznie wychodzi w bok, owijka pasa dostaje tabindex="0", rolę region i nazwę (sama lista <dl>
     zostaje listą); na szerokim ekranie (siatka) nic. */
  function initScrollers() {
    var items = $$('.pms-facts-wrap');
    if (!items.length) return;
    var update = function () {
      items.forEach(function (el) {
        var scrolls = el.scrollWidth > el.clientWidth + 1;
        if (scrolls) {
          el.setAttribute('tabindex', '0');
          el.setAttribute('role', 'region');
          el.setAttribute('aria-label', 'PM Session w pytaniach (przewijane w poziomie)');
        } else {
          el.removeAttribute('tabindex');
          el.removeAttribute('role');
          el.removeAttribute('aria-label');
        }
      });
    };
    update();
    var t = 0;
    window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(update, 200); });
  }

  /* ---------- Odsłanianie przy scrollu ---------- */
  function initReveal() {
    var items = $$('[data-reveal]');
    if (!('IntersectionObserver' in window) || reduceMotion.matches) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.1 });
    items.forEach(function (el) { io.observe(el); });
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
  window.PMG = { picture: pictureHtml, wireDialog: wireDialog, $: $, $$: $$, reduceMotion: reduceMotion, root: siteRoot, loadNews: loadNews, esc: esc, fmtDate: fmtDate, api: api, initLightbox: initLightbox };

  /* ---------- Lightbox galerii ---------- */
  function initLightbox(scope) { // scope: fragment wstawiony później (np. galeria z API); okno dialogowe podłączamy tylko raz
    var lb = $('[data-lightbox]');
    if (!lb) return;
    var img = $('[data-lightbox-img]', lb);
    var cap = $('[data-lightbox-caption]', lb);
    var open = lb.__open || (lb.__open = wireDialog(lb, function () { img.removeAttribute('src'); }));
    $$('[data-lightbox-trigger]', scope).forEach(function (btn) {
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
    var result = $('[data-form-result]', form);
    var note = status.textContent;
    var v = function (n) { return form.elements[n].value; };
    // adres czytany przy kliknięciu: data-mailto podmienia później api/ustawienia.php
    var mailto = function (draft) {
      var href = 'mailto:' + form.getAttribute('data-mailto');
      if (!draft) return href;
      var bodyText = draft.message + '\n\n—\n' + draft.name + '\n' + draft.email;
      return href + '?subject=' + encodeURIComponent(draft.subject) + '&body=' + encodeURIComponent(bodyText);
    };
    var ICONS = {
      ok: '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M7.5 12.5l3 3 6-6.5"/></svg>',
      error: '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 7v6.5M12 16.5v.01"/></svg>'
    };
    // Karta z wynikiem wysyłki: widoczna, przewinięta do środka ekranu i z fokusem (czytnik ekranu odczyta ją od razu).
    // draft !== undefined → przycisk „Wyślij z programu pocztowego” (null = sam adres, bez treści).
    var showResult = function (kind, title, text, draft) {
      result.className = 'form-result form-result--' + kind;
      result.innerHTML = '<span class="form-result__icon">' + ICONS[kind] + '</span><div class="form-result__body">' +
        '<h2 class="form-result__title">' + esc(title) + '</h2><p class="form-result__text">' + esc(text) + '</p>' +
        (draft !== undefined ? '<a class="btn btn--dark form-result__mail" href="' + esc(mailto(draft)) + '">Wyślij z programu pocztowego</a>' : '') + '</div>';
      var link = $('.form-result__mail', result);
      if (link) link.addEventListener('click', function () { link.href = mailto(draft); });
      result.hidden = false;
      result.scrollIntoView({ behavior: reduceMotion.matches ? 'auto' : 'smooth', block: 'center' });
      result.focus({ preventScroll: true });
    };
    // powrót po zwykłym POST (bez JS): api/kontakt.php przekierowuje na kontakt.html?wyslano=1 albo ?blad=1
    // (po „load”: wcześniej przeglądarka sama przewija do #formularz i zdejmuje fokus z karty)
    var back = new URLSearchParams(location.search);
    window.addEventListener('load', function () {
      if (back.get('wyslano') === '1') showResult('ok', 'Dziękujemy! Wiadomość wysłana', 'Odpowiemy jak najszybciej.');
      else if (back.get('blad') === '1') showResult('error', 'Nie udało się wysłać wiadomości', 'Sprawdź, czy wszystkie pola są wypełnione, i spróbuj ponownie albo napisz do nas z własnego programu pocztowego.', null);
    });
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
      result.hidden = true;
      var draft = { name: v('name'), email: v('email'), subject: v('subject'), message: v('message') };
      var send = $('.contact-form__send', form);
      send.disabled = true;
      status.textContent = 'Wysyłamy wiadomość…';
      api('kontakt.php', { method: 'POST', body: new FormData(form) }).then(function (res) {
        send.disabled = false;
        status.textContent = note;
        if (res && res.ok) {
          form.reset();
          showResult('ok', 'Dziękujemy! Wiadomość wysłana', 'Odpowiemy na adres ' + draft.email + ' jak najszybciej.');
        } else if (res) {
          // res.mailto: serwer przyjął dane, ale nie zapisał ani nie wysłał wiadomości (nie przy błędzie pól ani limicie)
          showResult('error', 'Nie udało się wysłać wiadomości', (res.error || 'Spróbuj ponownie za chwilę.') +
            (res.mailto ? ' Możesz wysłać ją z własnego programu pocztowego — treść jest już gotowa.' : ''), res.mailto ? draft : undefined);
        } else {
          status.textContent = 'Otwieramy Twój program pocztowy z gotową wiadomością…';
          window.location.href = mailto(draft);
        }
      });
    });
  }

  /* ---------- Zgoda na mapę Google: pasek przy pierwszej wizycie, wybór w localStorage (nie w ciasteczku) ---------- */
  /* Mapa na podstronie „Kontakt” wczytuje się sama tylko po zgodzie ('tak'). Przy 'nie' albo braku decyzji
     zostaje przycisk „Wczytaj mapę Google” (jednorazowo). Przy braku dostępu do localStorage działa jak brak decyzji. */
  var CONSENT_KEY = 'pmg-zgoda-mapa';
  var consentMemory = null;
  function getConsent() {
    try {
      var raw = window.localStorage.getItem(CONSENT_KEY);
      var v = raw ? JSON.parse(raw).v : null;
      return v === 'tak' || v === 'nie' ? v : null;
    } catch (e) { return consentMemory; }
  }
  function setConsent(v) {
    consentMemory = v;
    try { window.localStorage.setItem(CONSENT_KEY, JSON.stringify({ v: v, data: new Date().toISOString().slice(0, 10) })); } catch (e) { /* bez zapisu: decyzja obowiązuje do końca tej strony */ }
  }

  var mapApi = null;
  function initMap() {
    var box = $('[data-map]');
    if (!box || !$('[data-map-load]', box)) return;
    var original = box.innerHTML;
    var loaded = false;
    function removeAfterLink() {
      var next = box.nextElementSibling;
      if (next && next.classList.contains('map-consent__link--after')) next.remove();
    }
    function loadFrame() {
      if (loaded) return;
      loaded = true;
      var frame = document.createElement('iframe');
      frame.title = box.getAttribute('data-title') || 'Mapa Google';
      frame.src = box.getAttribute('data-src');
      frame.loading = 'lazy';
      frame.referrerPolicy = 'strict-origin-when-cross-origin';
      frame.allowFullscreen = true;
      var link = $('.map-consent__link[href]', box);
      var keep = link ? link.cloneNode(true) : null;
      box.textContent = '';
      box.appendChild(frame);
      if (keep) { keep.classList.add('map-consent__link--after'); box.insertAdjacentElement('afterend', keep); }
    }
    function showConsent() {
      loaded = false;
      removeAfterLink();
      box.innerHTML = original;
      var btn = $('[data-map-load]', box);
      btn.hidden = false;
      btn.addEventListener('click', function () { loadFrame(); var f = $('iframe', box); if (f) f.focus({ preventScroll: true }); });
      $$('[data-cookie-settings]', box).forEach(function (el) { el.hidden = false; });
    }
    showConsent();
    mapApi = {
      apply: function (decision) {
        if (decision === 'tak') loadFrame();
        else if (loaded) showConsent();
      }
    };
    if (getConsent() === 'tak') loadFrame();
  }

  function initConsentBar() {
    var policy = $('.site-footer a[href$="polityka-prywatnosci.html"]');
    var href = (policy ? policy.getAttribute('href') : 'polityka-prywatnosci.html') + '#pp-mapa';
    var bar = null;
    var opener = null; // element z fokusem tuż przed pokazaniem paska (null = brak albo sam pasek)
    function pad() { document.body.style.paddingBottom = bar && !bar.hidden ? bar.offsetHeight + 'px' : ''; }
    function hide() {
      if (!bar) return;
      bar.classList.remove('is-in');
      bar.hidden = true;
      pad();
    }
    function build() {
      bar = document.createElement('div');
      bar.className = 'cookie-bar';
      bar.setAttribute('role', 'region');
      bar.setAttribute('aria-label', 'Zgoda na mapę Google');
      bar.hidden = true;
      bar.innerHTML = '<p class="cookie-bar__text">Na stronie „Kontakt” używamy mapy Google, która zapisuje pliki cookie Google. ' +
        'Możesz zgodzić się na jej automatyczne wczytywanie albo odmówić. ' +
        '<a href="' + href + '">Polityka prywatności</a></p>' +
        '<div class="cookie-bar__actions">' +
        '<button class="cookie-bar__btn" type="button" data-cookie-choice="tak">Akceptuję</button>' +
        '<button class="cookie-bar__btn" type="button" data-cookie-choice="nie">Odrzucam</button></div>';
      document.body.appendChild(bar);
      bar.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-cookie-choice]') : null;
        if (!b) return;
        var choice = b.getAttribute('data-cookie-choice');
        var hadFocus = bar.contains(document.activeElement);
        setConsent(choice);
        hide();
        if (mapApi) mapApi.apply(choice);
        if (hadFocus) {
          var back = opener && opener.isConnected ? opener : $('.site-footer [data-cookie-settings]');
          if (back) back.focus({ preventScroll: true });
        }
      });
      window.addEventListener('resize', pad);
    }
    function show(focusFirst) {
      if (!bar) build();
      if (bar.hidden) {
        var active = document.activeElement;
        opener = active && active !== document.body && !bar.contains(active) ? active : null;
      }
      bar.hidden = false;
      pad();
      requestAnimationFrame(function () { bar.classList.add('is-in'); });
      if (focusFirst) { var first = $('button', bar); if (first) first.focus({ preventScroll: true }); }
    }
    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('[data-cookie-settings]') : null;
      if (!t) return;
      e.preventDefault();
      show(true);
    });
    if (!getConsent()) show(false);
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
        var img = p.zdjecie ? pictureHtml(p.obraz, p.zdjecie, { sizes: '(max-width: 640px) calc(100vw - 40px), (max-width: 960px) calc(50vw - 30px), 400px', w: 1600, h: 900, lazy: true }) : '<span>[ zdjęcie 16:9 ]</span>';
        var href = 'aktualnosci.html#wpis-' + esc(p.slug);
        return '<li class="news-tile"><div class="news-tile__img news-tile__img--' + esc(p.kolor) + '" aria-hidden="true">' + img + '</div>' +
          '<div class="news-tile__body"><p class="news-tile__date"><time datetime="' + esc(p.data) + '">' + esc(fmtDate(p.data)) + '</time></p>' +
          '<h3 class="news-tile__title"><a class="news-tile__link" href="' + href + '">' + esc(p.tytul) + '</a></h3>' +
          '<p class="news-tile__more">' + esc(p.zajawka || p.lead) + '</p>' +
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
    initTresci();
    initNewsTiles();
    initHomeNews();
    initSettings();
    initTeam();
    initPmSession();
    initPmsTapes();
    initNav();
    initSubnav();
    initToTop();
    initAnchorScroll();
    initReveal();
    initScrollers();
    initLightbox();
    initContactForm();
    initMap();
    initConsentBar();
    initReportBug();
    initGrass();
    // pusty listener na touchstart włącza stany :active w Safari na iOS
    document.addEventListener('touchstart', function () {}, { passive: true });
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
      var SIZES = {
        featured: '(max-width: 960px) calc(100vw - 40px), 640px',
        card: '(max-width: 640px) calc(100vw - 40px), (max-width: 960px) calc(50vw - 30px), 560px',
        article: '(max-width: 860px) calc(100vw - 40px), 820px'
      };
      var img = function (p, cls, sizes, withAlt) {
        var alt = withAlt && p.zdjecie_alt;
        return '<div class="blog-ph blog-ph--' + (ph[p.kolor] || 'pms') + ' ' + cls + '"' + (p.zdjecie && alt ? '' : ' aria-hidden="true"') + '>' +
          (p.zdjecie ? PMG.picture(p.obraz, p.zdjecie, { sizes: sizes, w: 1600, h: 900, alt: alt ? p.zdjecie_alt : '', lazy: true }) : '[ zdjęcie 16:9 ]') + '</div>';
      };
      var meta = function (p, cls) {
        return '<div class="blog-meta' + (cls || '') + '">' +
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
      $('.blog-featured', list).innerHTML = img(f, 'blog-featured__img', SIZES.featured) + '<div class="blog-featured__body">' + meta(f) + link(f, 'h2', 'blog-featured__title') +
        '<p class="blog-featured__excerpt">' + esc(f.zajawka || f.lead) + '</p><p class="blog-more blog-more--' + esc(f.kolor) + '" aria-hidden="true">Czytaj więcej →</p></div>';
      $('.blog-grid', list).innerHTML = posts.slice(1).map(function (p) {
        return '<li class="blog-card" data-blog-card>' + img(p, 'blog-card__img', SIZES.card) + '<div class="blog-card__body">' + meta(p) + link(p, 'h3', 'blog-card__title') +
          '<p class="blog-card__excerpt">' + esc(p.zajawka || p.lead) + '</p><p class="blog-more blog-more--' + esc(p.kolor) + '" aria-hidden="true">Czytaj więcej →</p></div></li>';
      }).join('');
      $('.blog-posts', list).hidden = posts.length < 2;
      wrap.innerHTML = posts.map(function (p) {
        var id = 'wpis-' + esc(p.slug);
        return '<article id="' + id + '" class="blog-article" aria-labelledby="' + id + '-title">' +
          meta(p, ' blog-meta--article') +
          '<h1 class="blog-article__title" id="' + id + '-title" tabindex="-1">' + esc(p.tytul) + '</h1>' +
          '<p class="blog-article__lead">' + esc(p.lead || p.zajawka) + '</p>' + img(p, 'blog-article__img', SIZES.article, true) +
          '<div class="blog-article__body">' + body(p.tresc) + '</div>' +
          (p.galeria && p.galeria.length && PMG.galleryHtml ? '<section class="blog-article__gallery" aria-labelledby="' + id + '-galeria"><h2 class="blog-article__h2" id="' + id + '-galeria">Galeria</h2>' +
            '<div class="gallery gallery--small">' + p.galeria.map(function (g) { return PMG.galleryHtml(g); }).join('') + '</div></section>' : '') +
          (p.autor ? '<div class="blog-article__foot"><p class="blog-article__author">Autor: <b>' + esc(p.autor) + '</b></p></div>' : '') +
          '</article>';
      }).join('');
      if (PMG.initLightbox) PMG.initLightbox(wrap);
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
      var crumbList = $('[data-breadcrumb-list]');
      var crumbArticle = $$('[data-breadcrumb-article]');
      var crumbCurrent = $('[data-breadcrumb-current]');
      var show = function (target, moveFocus) {
        var art = target === 'list' ? null : target;
        list.hidden = !!art;
        wrap.hidden = !art;
        articles.forEach(function (a) { a.hidden = a !== art; });
        if (crumbList) crumbList.hidden = !!art;
        crumbArticle.forEach(function (li) { li.hidden = !art; });
        var changed = current !== target;
        current = target;
        if (art) {
          var h1 = $('.blog-article__title', art);
          document.title = h1.textContent + ' — Aktualności — Project Management Group';
          if (crumbCurrent) crumbCurrent.textContent = h1.textContent;
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
      // ≤ 960 px klatka co 120 ms zamiast 60 ms: ten sam wygląd ziarna, o połowę mniej pracy procesora i baterii telefonu
      var FRAME_MS = window.matchMedia('(max-width: 960px)').matches ? 120 : 60;
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
        timer = setTimeout(function () { raf = requestAnimationFrame(loop); }, FRAME_MS);
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

    /* ---------- Odcinki z panelu (api/podcast.php) ---------- */
    /* Fallback: brak backendu / błąd / pusta tabela w bazie = lista i okna zostają statyczne z podcast.html.
       Gdy tabela ma rekordy (także szkice), lista pochodzi z bazy — nawet pusta. Okno odcinka obsługuje
       delegacja zdarzeń poniżej, więc działa i dla kafelków statycznych, i dla wyrenderowanych. */
    var episodesList = $('.pod-episodes');
    if (episodesList && PMG.api) {
      var esc = PMG.esc;
      var httpsUrl = function (u, re) { return typeof u === 'string' && u.indexOf('https://') === 0 && re.test(u); };
      var podDate = function (iso) { var p = String(iso).split('-'); return esc(p[2] + '/' + p[1] + '/' + p[0]); };
      var podImg = function (o, cls) {
        if (!o.zdjecie) return '';
        return '<img class="' + cls + '" src="' + esc(PMG.root + o.zdjecie) + '" width="1600" height="900" alt="' + esc(o.zdjecie_alt) + '" loading="lazy" decoding="async">';
      };
      var podCard = function (o) {
        var spotify = /^[A-Za-z0-9]{22}$/.test(o.spotify_id || '');
        var embed = spotify ? ' data-embed="' + esc('https://open.spotify.com/embed/episode/' + o.spotify_id + '?utm_source=generator&theme=0') + '"' : '';
        var media = o.zdjecie ? '<div class="pod-ep__media">' + podImg(o, 'pod-ep__img') + '</div>' : '';
        return '<li class="pod-ep" data-pod-episode="' + o.numer + '"' + embed + ' data-title="' + esc(o.tytul) + '">' + media +
          '<div class="pod-ep__body"><p class="pod-ep__meta">odcinek #' + o.numer + ' | ' + podDate(o.data) + '</p>' +
          '<h3 class="pod-ep__title"><button class="pod-ep__btn" type="button" aria-haspopup="dialog">' + esc(o.tytul) + '<span class="visually-hidden"> — otwórz odcinek #' + o.numer + '</span></button></h3>' +
          '<p class="pod-ep__more" aria-hidden="true">Otwórz odcinek ▸</p></div></li>';
      };
      var newTab = '<span aria-hidden="true">→</span><span class="visually-hidden"> (otwiera się w nowej karcie)</span>';
      var podTemplate = function (o) {
        var spotify = /^[A-Za-z0-9]{22}$/.test(o.spotify_id || '');
        var meta = 'odcinek #' + o.numer + ' | ' + podDate(o.data) + (o.czas_min ? ' | ' + o.czas_min + ' min' : '');
        var people = '';
        if (o.prowadzacy) people += '<div><dt>Prowadzący</dt><dd>' + esc(o.prowadzacy) + '</dd></div>';
        if (o.gosc) people += '<div><dt>Gość</dt><dd>' + esc(o.gosc) + '</dd></div>';
        var links = '';
        if (spotify) links += '<a class="pod-btn pod-btn--spotify" href="' + esc('https://open.spotify.com/episode/' + o.spotify_id) + '" target="_blank" rel="noopener">Posłuchaj na Spotify ' + newTab + '</a>';
        if (httpsUrl(o.apple_url, /^https:\/\/podcasts\.apple\.com\//)) links += ' <a class="pod-btn pod-btn--ghost" href="' + esc(o.apple_url) + '" target="_blank" rel="noopener">Posłuchaj na Apple Podcasts ' + newTab + '</a>';
        if (httpsUrl(o.youtube_url, /^https:\/\/(www\.|music\.)?(youtube\.com|youtu\.be)\//)) links += ' <a class="pod-btn pod-btn--ghost" href="' + esc(o.youtube_url) + '" target="_blank" rel="noopener">Obejrzyj na YouTube ' + newTab + '</a>';
        var desc = String(o.opis || '').split(/\n\s*\n/).map(function (par) { return par.trim() ? '<p class="pod-modal__desc">' + esc(par.trim()) + '</p>' : ''; }).join('');
        var guest = o.gosc_bio ? '<div class="pod-modal__guest"><p class="pod-modal__guest-label">O gościu</p><p class="pod-modal__guest-bio">' + esc(o.gosc_bio) + '</p></div>' : '';
        return '<template id="pod-ep-' + o.numer + '"><div class="pod-modal__head">' + podImg(o, 'pod-modal__img') +
          '<div class="pod-modal__heading"><p class="pod-modal__num">' + meta + '</p><h2 class="pod-modal__title" id="pod-modal-title">' + esc(o.tytul) + '</h2></div></div>' +
          '<div class="pod-modal__body">' + (people ? '<dl class="pod-modal__people">' + people + '</dl>' : '') +
          (spotify ? '<div class="pod-modal__player" data-pod-player></div>' : '') +
          (links ? '<p class="pod-modal__links">' + links + '</p>' : '') + desc + guest + '</div></template>';
      };
      /* Edycja podcastu = osobna sekcja: nagłówek, zespół, opis, lista odcinków. Grupa bez numeru (odcinki bez edycji)
         nie ma nagłówka ani zespołu. Nowe sekcje dostają is-in od razu — observer [data-reveal] już się uruchomił. */
      var podTeam = function (label, val) { return val ? label + ': ' + esc(val) : ''; };
      var podEdition = function (g) {
        var hid = 'h-edition-' + g.numer;
        var title = g.numer === null ? '' : '<h2 id="' + hid + '" class="pod-edition__title">Edycja ' + g.numer +
          (g.lata ? ' <span class="pod-edition__year">(' + esc(g.lata) + ')</span>' : '') + '</h2>';
        var desc = g.opis ? '<p class="pod-edition__desc">' + esc(g.opis) + '</p>' : '';
        var team = [podTeam('Koordynator', g.koordynator), podTeam('Mentorzy', g.mentorzy), podTeam('Zespół', g.zespol)].filter(Boolean).join('<br>');
        var items = g.odcinki.length
          ? g.odcinki.map(podCard).join('')
          : '<li class="pod-ep"><div class="pod-ep__body"><p class="pod-ep__meta">Wkrótce nowe odcinki.</p></div></li>';
        return '<section class="pod-edition"' + (g.numer === null ? ' aria-label="Odcinki"' : ' aria-labelledby="' + hid + '"') + '><div class="container is-in" data-reveal>' +
          title + desc + (team ? '<p class="pod-edition__team">' + team + '</p>' : '') + '<ul class="pod-episodes">' + items + '</ul></div></section>';
      };
      PMG.api('podcast.php').then(function (data) {
        if (!data || !data.odcinki || !data.wszystkich) return;
        var section = episodesList.closest('.pod-edition');
        if (!section) return;
        var groups = data.edycje && data.edycje.length ? data.edycje : [{ numer: null, odcinki: data.odcinki }];
        $$('template[id^="pod-ep-"]').forEach(function (t) { t.remove(); });
        section.insertAdjacentHTML('beforebegin', groups.map(podEdition).join(''));
        section.insertAdjacentHTML('afterend', data.odcinki.map(podTemplate).join(''));
        section.remove();
      });
    }

    /* ---------- Modal odcinka ---------- */
    var dialog = $('[data-pod-modal]');
    if (dialog) {
      var content = $('[data-pod-modal-content]', dialog);
      var open = PMG.wireDialog(dialog, function () { content.textContent = ''; });  // usuwa też iframe Spotify
      document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.pod-ep__btn');
        var card = btn && btn.closest('[data-pod-episode]');
        if (!card) return;
        var tpl = document.getElementById('pod-ep-' + card.getAttribute('data-pod-episode'));
        if (!tpl) return;
        content.textContent = '';
        content.appendChild(tpl.content.cloneNode(true));
        var player = $('[data-pod-player]', content);
        if (player && card.getAttribute('data-embed')) {
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
        open(btn);
        dialog.scrollTop = 0;
      });
    }

    /* ---------- Modal prelegenta (PM Session) ---------- */
    /* Delegacja na document: działa też na kafelkach wyrenderowanych później przez initPmSession(). */
    var speakerDialog = $('[data-speaker-modal]');
    if (speakerDialog) {
      var speakerContent = $('[data-speaker-modal-content]', speakerDialog);
      var openSpeaker = PMG.wireDialog(speakerDialog, function () { speakerContent.textContent = ''; });
      document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-speaker-trigger]');
        if (!btn) return;
        var tpl = document.getElementById(btn.getAttribute('data-speaker-tpl'));
        if (!tpl) return;
        speakerContent.textContent = '';
        speakerContent.appendChild(tpl.content.cloneNode(true));
        openSpeaker(btn);
        speakerContent.scrollTop = 0;
      });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

/* ---------- Case Koła: hub, podstrona edycji i menu z panelu ---------- */
/* Hub (case-kola.html) i menu pokazują edycje z api/case-kola.php, a case-kola-edycja.html?nr=N składa podstronę jednej edycji.
   Bez backendu (GitHub Pages) albo gdy w panelu nie ma jeszcze edycji zostaje statyczny HTML. Wszystkie pola przechodzą przez esc(). */
(function () {
  'use strict';
  var PMG = window.PMG;
  if (!PMG) return;
  var $ = PMG.$, $$ = PMG.$$, esc = PMG.esc;
  var hub = $('[data-case-hub]');
  var page = $('[data-case-edycja]');
  var subnav = $('#subnav-case');
  if (!hub && !page && !subnav) return;

  // Podstrona edycji czeka z animacjami wjazdu sekcji (v2.js) na treść z API.
  var ready = function () {};
  if (page) PMG.caseReady = new Promise(function (resolve) { ready = resolve; });

  // dotychczasowe pliki edycji 1–4: gdy backend nie odpowiada, link do wspólnej podstrony prowadzi do nich
  var LEGACY = { 1: 'case-kola-debatelab.html', 2: 'case-kola-pwr-racing-team.html', 3: 'case-kola-qubit.html', 4: 'case-kola-solvro.html' };
  var HERO_SIZES = '(max-width: 960px) calc(100vw - 64px), (max-width: 1272px) calc(100vw - 112px), 1160px';
  var GALLERY_SIZES = '(max-width: 640px) calc(100vw - 40px), (max-width: 1272px) calc(50vw - 66px), 570px';
  var abs = function (p) { return PMG.root + p; };
  var nrParam = (new URLSearchParams(location.search).get('nr') || '');

  /* ----- karty w hubie ----- */
  var densities = function (set) { // 'a 192w, b 384w' -> 'a 1x, b 2x' (logo na karcie ma stałą wysokość, nie szerokość)
    var a = set.split(', ').map(function (e) { return e.split(' ')[0]; });
    return a.slice(0, 2).map(function (p, i) { return abs(p) + ' ' + (i + 1) + 'x'; }).join(', ');
  };
  var cardLogo = function (e, plate) {
    var cls = plate ? ' class="case-card__media__logo-plate"' : '';
    var o = e.logo_obraz;
    if (!o) return '<img' + cls + ' src="' + esc(abs(e.logo)) + '" alt="" loading="lazy" decoding="async">';
    var dim = o.w && o.h ? ' width="' + o.w + '" height="' + o.h + '"' : '';
    return '<picture>' + (o.webp ? '<source type="image/webp" srcset="' + esc(densities(o.webp)) + '">' : '') +
      '<img' + cls + ' src="' + esc(abs(o.src)) + '" srcset="' + esc(densities(o.srcset)) + '"' + dim + ' alt="" loading="lazy" decoding="async"></picture>';
  };
  var cardHtml = function (e) {
    var tall = e.logo_styl === 'jasne-wysokie', light = tall || e.logo_styl === 'jasne';
    var cls = 'case-card__media case-card__media--logo' + (tall ? ' case-card__media--logo-tall' : '') + (light ? ' case-card__media--logo-light' : '') + (e.linia ? ' case-card__media--linia' : '');
    return '<li class="is-in" data-reveal><a class="case-card card card--hover" href="' + esc(e.url) + '">' +
      '<div class="' + cls + '">' + (e.logo ? cardLogo(e, light) : '') + '</div>' +
      '<div class="case-card__body"><h2 class="case-card__title">' + esc(e.tytul) + '</h2><p class="case-card__edition">Edycja ' + Number(e.numer) + '</p>' +
      '<span class="case-card__more">Dowiedz się więcej o&nbsp;tej edycji <span class="case-card__more-arrow" aria-hidden="true">→</span></span></div></a></li>';
  };

  /* ----- menu: lista edycji w podmenu Case Koła ----- */
  var rebuildMenu = function (cards) {
    var first = subnav && subnav.firstElementChild; // „Czym jest Case Koła?” zostaje
    if (!first) return;
    var cur = $('a[aria-current="page"]', subnav);
    var curText = cur && cur.getAttribute('href') !== 'case-kola.html' ? cur.textContent.trim().toLowerCase() : '';
    var file = location.pathname.split('/').pop();
    while (first.nextElementSibling) subnav.removeChild(first.nextElementSibling);
    cards.forEach(function (c) {
      var li = document.createElement('li'), a = document.createElement('a');
      a.className = 'site-nav__sublink';
      a.href = abs(c.url); // od katalogu strony, nie od bieżącego adresu (404.html wyświetla się pod dowolną ścieżką)
      a.textContent = c.nazwa;
      if ((page && nrParam === String(c.numer)) || (curText && c.nazwa.toLowerCase() === curText) || (c.adres_strony && c.adres_strony === file)) a.setAttribute('aria-current', 'page');
      li.appendChild(a);
      subnav.appendChild(li);
    });
  };

  /* ----- podstrona edycji ----- */
  var paras = function (text) {
    return String(text == null ? '' : text).split(/\n\s*\n/).map(function (b) { return b.trim(); }).filter(Boolean).map(function (b) {
      return '<p>' + esc(b).replace(/\n/g, '<br>') + '</p>';
    }).join('');
  };
  var lastPath = function (set) { var a = set.split(', '); return a[a.length - 1].split(' ')[0]; };
  var galleryHtml = function (g) {
    var o = g.obraz;
    var full = g.pelne || (o ? lastPath(o.webp || o.srcset) : g.zdjecie);
    var cap = esc(g.podpis);
    return '<figure class="gallery__item"><button class="gallery__button" type="button" data-lightbox-trigger data-full="' + esc(abs(full)) + '" data-caption="' + cap + '" aria-label="Powiększ zdjęcie: ' + cap + '">' +
      PMG.picture(o, g.zdjecie, { sizes: GALLERY_SIZES, fw: 1200, fh: 675, alt: g.podpis, lazy: true }) + '</button><figcaption class="gallery__caption">' + cap + '</figcaption></figure>';
  };
  PMG.galleryHtml = galleryHtml; // używa go też widok artykułu w Aktualnościach (blok „Aktualności” wyżej w tym pliku)
  var sectionsHtml = function (e) {
    var out = [];
    var bg = function () { return out.length % 2 ? 'bg-warm' : 'bg-surface'; }; // tła na przemian, także gdy sekcji brakuje
    var prose = function (id, title, text, wrapped) {
      if (!String(text || '').trim()) return;
      var inner = '<h2 id="' + id + '">' + title + '</h2>' + paras(text);
      out.push('<section class="case-section section ' + bg() + '" aria-labelledby="' + id + '">' +
        (wrapped ? '<div class="measure" data-reveal><div class="result prose">' + inner + '</div></div>' : '<div class="measure prose" data-reveal>' + inner + '</div>') + '</section>');
    };
    prose('h-partner', 'O partnerze', e.o_partnerze);
    prose('h-wyzwanie', 'Wyzwanie', e.wyzwanie);
    prose('h-co', 'Co zrobiliśmy', e.co_zrobilismy);
    prose('h-rezultat', 'Rezultat', e.rezultat, true);
    if (e.w_toku) out.push('<section class="case-section case-pending bg-warm"><div class="measure" data-reveal><p class="case-pending__text">' + esc(e.w_toku) + '</p></div></section>');
    if (e.galeria && e.galeria.length) {
      out.push('<section class="case-section section section--gallery ' + bg() + '" aria-labelledby="h-galeria"><div class="container">' +
        '<h2 id="h-galeria" class="case-section__title--gallery" data-reveal>Galeria</h2>' +
        '<div class="gallery' + (e.galeria.length === 1 ? ' gallery--single' : '') + '">' + e.galeria.map(galleryHtml).join('') + '</div></div></section>');
    }
    return out.join('');
  };
  var heroHtml = function (e) {
    if (e.hero_tryb === 'zdjecie') {
      return '<section class="case-hero"><div class="container" data-reveal><div class="media ratio-16x9">' +
        PMG.picture(e.hero_obraz, e.hero, { sizes: HERO_SIZES, fw: 1920, fh: 1080, alt: e.hero_alt }) + '</div></div></section>';
    }
    if (e.hero_tryb === 'logo' && e.logo) {
      return '<section class="case-hero"><div class="container" data-reveal><div class="media ratio-16x9 case-hero__logo-box">' +
        PMG.picture(e.logo_obraz, e.logo, { sizes: '(max-width: 960px) 52vw, 384px', alt: 'Logo ' + e.naglowek }) + '</div></div></section>';
    }
    return '';
  };
  var reveal = function (root) { // elementy [data-reveal] wstawione po starcie main.js (jego IntersectionObserver ich nie widzi)
    var items = $$('[data-reveal]', root);
    if (!('IntersectionObserver' in window) || PMG.reduceMotion.matches) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { threshold: 0.1 });
    items.forEach(function (el) { io.observe(el); });
  };
  var status = function (html) {
    var box = $('[data-ce-status]', page);
    if (box) box.innerHTML = html;
  };
  var renderEdition = function (e) {
    var box = $('[data-ce-status]', page);
    var crumb = $('[data-ce-crumb]', page);
    if (crumb) crumb.textContent = e.nazwa;
    document.title = 'Case Koła - ' + e.nazwa + ' | Project Management Group';
    var meta = $('meta[name="description"]');
    if (meta && e.opis_meta) meta.setAttribute('content', e.opis_meta);
    var holder = document.createElement('div');
    holder.innerHTML = '<section class="case-banner"><div class="container rise"><div class="case-banner__head"><h1 class="h1">' + esc(e.naglowek) + '</h1></div></div></section>' +
      heroHtml(e) + sectionsHtml(e);
    var frag = document.createDocumentFragment();
    while (holder.firstChild) frag.appendChild(holder.firstChild);
    if (box) box.parentNode.removeChild(box);
    page.appendChild(frag);
    reveal(page);
    PMG.initLightbox(page);
  };
  var notFound = function (offline) {
    status('<p class="lead">' + (offline ? 'Nie udało się wczytać tej edycji. Spróbuj ponownie za chwilę.' : 'Nie ma takiej edycji Case Koła.') +
      ' <a href="case-kola.html">Zobacz wszystkie edycje</a>.</p>');
  };

  if (page) {
    if (!/^[0-9]{1,3}$/.test(nrParam)) { notFound(false); ready(); }
    else PMG.api('case-kola.php?nr=' + nrParam).then(function (d) {
      if (d && d.edycja) renderEdition(d.edycja);
      else if (d === null && LEGACY[Number(nrParam)]) { location.replace(LEGACY[Number(nrParam)]); return; }
      else notFound(d === null);
      ready();
    });
  }
  if (hub || subnav) {
    PMG.api('case-kola.php').then(function (d) {
      if (!d || !d.edycje || !(d.wszystkich > 0)) return;
      if (hub) hub.innerHTML = d.edycje.map(cardHtml).join('');
      rebuildMenu(d.edycje);
    });
  }
})();

/* ---------- PM Session: podmenu edycji z panelu ---------- */
/* #subnav-pms: pierwsza pozycja („Czym jest PM Session?”) zostaje z HTML, dalej edycje z api/pmsession.php?lista=1:
   „Aktualna edycja” dla bieżącej (także „Aktualna edycja – wkrótce więcej”, status zapowiedz) i „PM Session <numer>” dla zakończonych (tylko te z własnym plikiem pm-session-<numer>.html,
   szkice nigdy). Bez backendu (GitHub Pages), przy błędzie albo pustej bazie edycji zostaje statyczne menu z HTML. */
(function () {
  'use strict';
  var PMG = window.PMG;
  if (!PMG) return;
  var subnav = PMG.$('#subnav-pms');
  var first = subnav && subnav.firstElementChild;
  if (!first) return;
  PMG.api('pmsession.php?lista=1').then(function (d) {
    if (!d || !d.edycje || !(d.wszystkich > 0)) return;
    var file = location.pathname.split('/').pop();
    while (first.nextElementSibling) subnav.removeChild(first.nextElementSibling);
    d.edycje.forEach(function (e) {
      if (!/^pm-session-[ivxlc]{1,10}\.html$/.test(e.adres)) return;
      var li = document.createElement('li'), a = document.createElement('a');
      a.className = 'site-nav__sublink';
      a.href = PMG.root + e.adres; // od katalogu strony, nie od bieżącego adresu (404.html wyświetla się pod dowolną ścieżką)
      a.textContent = e.status === 'biezaca' || e.status === 'zapowiedz' ? 'Aktualna edycja' : 'PM Session ' + e.numer;
      if (e.adres === file) a.setAttribute('aria-current', 'page');
      li.appendChild(a);
      subnav.appendChild(li);
    });
  });
})();
