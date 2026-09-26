/* Panel PMG — skrypty widoku (A3e). Progresywne wzbogacenie: bez JS panel działa w pełni.
   Zakaz: innerHTML, insertAdjacentHTML, eval, blob:/data: URL. Bez zależności, IIFE, ES2017. */
(function () {
  'use strict';

  // Ustawia fokus na alercie błędu ($error), żeby czytnik ekranu i klawiatura od razu trafiły w kontekst.
  function fokusNaBledzie() {
    var el = document.querySelector('[data-pmg-fokus]');
    if (el) el.focus();
  }

  // Licznik znaków dla pól z data-pmg-licznik + maxlength: widoczny licznik "N / M" (ukryty < 80%)
  // oraz cichy komunikat dla czytników ekranu tylko przy przekroczeniu progów 80% / 90% / 100%.
  function licznikZnakow() {
    var pola = document.querySelectorAll('[data-pmg-licznik][maxlength]');
    pola.forEach(function (pole) {
      var max = parseInt(pole.getAttribute('maxlength'), 10);
      if (!max || max <= 0) return;

      var licznik = document.createElement('p');
      licznik.className = 'pmg-counter';
      licznik.setAttribute('aria-hidden', 'true');
      licznik.hidden = true;

      var zywy = document.createElement('span');
      zywy.className = 'pmg-vh';
      zywy.setAttribute('aria-live', 'polite');

      if (pole.nextSibling) {
        pole.parentNode.insertBefore(licznik, pole.nextSibling);
      } else {
        pole.parentNode.appendChild(licznik);
      }
      if (licznik.nextSibling) {
        pole.parentNode.insertBefore(zywy, licznik.nextSibling);
      } else {
        pole.parentNode.appendChild(zywy);
      }

      var ostatniProg = 0;
      function prog(dlugosc) {
        var proc = (dlugosc / max) * 100;
        if (proc >= 100) return 100;
        if (proc >= 90) return 90;
        if (proc >= 80) return 80;
        return 0;
      }

      function odswiez(oglaszaj) {
        var dlugosc = pole.value.length;
        licznik.textContent = dlugosc + ' / ' + max;
        licznik.hidden = dlugosc < max * 0.8;

        var biezacyProg = prog(dlugosc);
        if (oglaszaj && biezacyProg !== ostatniProg && biezacyProg > 0) {
          if (biezacyProg === 100) {
            zywy.textContent = 'Osiągnięto limit ' + max + ' znaków.';
          } else {
            zywy.textContent = 'Zostało ' + (max - dlugosc) + ' znaków.';
          }
        }
        ostatniProg = biezacyProg;
      }

      odswiez(false);
      pole.addEventListener('input', function () { odswiez(true); });
    });
  }

  // "Kopiuj link": pokazuje przycisk (ukryty bez JS), kopiuje do schowka z zapasowym zaznaczeniem tekstu.
  function kopiujLink() {
    var przyciski = document.querySelectorAll('[data-pmg-kopiuj]');
    przyciski.forEach(function (btn) {
      var pole = document.getElementById(btn.getAttribute('data-pmg-kopiuj'));
      if (!pole) return;
      var karta = btn.closest('.pmg-card') || document;
      var status = karta.querySelector('[data-pmg-kopiuj-status]');
      btn.hidden = false;

      function etykieta(tekst) {
        for (var i = 0; i < btn.childNodes.length; i++) {
          if (btn.childNodes[i].nodeType === 3) { btn.childNodes[i].nodeValue = tekst; return; }
        }
        btn.appendChild(document.createTextNode(tekst));
      }

      function ustawStatus(tekst) {
        if (status) status.textContent = tekst;
      }

      function sukces() {
        ustawStatus('Link skopiowany do schowka.');
        etykieta('Skopiowano');
        setTimeout(function () { etykieta('Kopiuj link'); }, 2000);
      }

      function zapasowo() {
        pole.focus();
        pole.select();
        pole.setSelectionRange(0, pole.value.length);
        var udalo = false;
        try { udalo = document.execCommand('copy'); } catch (e) { udalo = false; }
        if (udalo) {
          sukces();
        } else {
          ustawStatus('Link zaznaczony — skopiuj go (Ctrl+C albo przytrzymaj palcem).');
        }
      }

      btn.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(pole.value).then(sukces, zapasowo);
        } else {
          zapasowo();
        }
      });
    });
  }

  // Ostrzeżenie przed zamknięciem karty przy niezapisanych zmianach w formularzach edycji.
  function niezapisaneZmiany() {
    var formularze = document.querySelectorAll('form[data-pmg-niezapisane]');
    if (!formularze.length) return;
    var zmieniony = false;
    var wysylany = false;

    formularze.forEach(function (f) {
      f.addEventListener('input', function () { zmieniony = true; });
      f.addEventListener('change', function () { zmieniony = true; });
    });
    document.addEventListener('submit', function () { wysylany = true; });
    window.addEventListener('beforeunload', function (e) {
      if (zmieniony && !wysylany) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    window.addEventListener('pageshow', function () {
      zmieniony = false;
      wysylany = false;
    });
  }

  // Menu mobilne (<details class="pmg-menu">): Esc zamyka i oddaje fokus, klik poza zamyka,
  // zmiana szerokości na desktopową (>= 1024 px) zamyka.
  function menuMobilne() {
    var menu = document.querySelector('details.pmg-menu');
    if (!menu) return;
    var summary = menu.querySelector('summary');

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.open) {
        menu.open = false;
        if (summary) summary.focus();
      }
    });
    document.addEventListener('click', function (e) {
      if (menu.open && !menu.contains(e.target)) {
        menu.open = false;
      }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 1024 && menu.open) {
        menu.open = false;
      }
    });
  }

  // Informacja o wybranym pliku (nazwa, rozmiar); bez podglądu — CSP img-src 'self' blokuje blob:.
  function informacjaOPliku() {
    var pola = document.querySelectorAll('input[type=file]');
    pola.forEach(function (pole) {
      pole.addEventListener('change', function () {
        var istniejacy = pole.parentNode.querySelector('[data-pmg-plik-info]');
        if (!pole.files || !pole.files.length) {
          if (istniejacy) istniejacy.parentNode.removeChild(istniejacy);
          return;
        }
        var plik = pole.files[0];
        var mb = (plik.size / (1024 * 1024)).toFixed(1).replace('.', ',');
        var tekst = 'Wybrano: ' + plik.name + ' (' + mb + ' MB).';
        var zaDuzy = plik.size > 10 * 1024 * 1024;
        if (zaDuzy) tekst += ' Plik jest większy niż 10 MB — serwer go nie przyjmie.';

        var p = istniejacy;
        if (!p) {
          p = document.createElement('p');
          p.className = 'pmg-hint';
          p.setAttribute('aria-live', 'polite');
          p.setAttribute('data-pmg-plik-info', '');
          if (pole.nextSibling) {
            pole.parentNode.insertBefore(p, pole.nextSibling);
          } else {
            pole.parentNode.appendChild(p);
          }
        }
        p.textContent = tekst;
        p.classList.toggle('is-warning', zaDuzy);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    fokusNaBledzie();
    licznikZnakow();
    kopiujLink();
    niezapisaneZmiany();
    menuMobilne();
    informacjaOPliku();
  });
})();
