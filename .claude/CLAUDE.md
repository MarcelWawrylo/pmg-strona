# Strona PMG — wiedza o projekcie dla Claude

Ten plik czyta automatycznie każdy Claude Code otwarty w tym repozytorium. Jest dla wszystkich osób pracujących nad stroną (Marcel, Agnieszka i kolejni), nie tylko dla jednej sesji.

## Co robimy i po co

- Strona koła naukowego **Project Management Group (PMG)**, Wydział Zarządzania Politechniki Wrocławskiej.
- Strona ma pokazywać, czym jest koło, przyciągać nowych członków (rekrutacja), promować PM Session (nasza konferencja), podcast i „Case Koła” (studia przypadków innych kół PWr) oraz publikować Aktualności.
- Treści dostarczają sekcje koła (np. Marketing, HR). Stronę opracowują **Agnieszka Jachimiak i Marcel Wawryło** (tak jest też w stopce).
- **Marcel** zatwierdza i scala zmiany do `main` oraz publikuje stronę. Nikt inny nie publikuje.

## Gdzie działa strona

| Środowisko | Adres | Uwagi |
|---|---|---|
| Robocze (GitHub Pages) | https://marcelwawrylo.github.io/pmg-strona/ (v1), `/v2/` (v2) | Nie jest produkcją. PHP tu nie działa. |
| Produkcja (docelowo) | serwer PWr / WCSS, Apache 2.4 + PHP + MariaDB | Formularz kontaktowy i panel Aktualności działają tylko tu. |

GitHub Pages publikuje się z gałęzi `gh-pages` poleceniem `git subtree push --prefix site origin gh-pages`. **To polecenie wolno uruchomić tylko Marcelowi.**

## Jak to jest zbudowane

- Zwykły HTML + CSS + vanilla JS w katalogu `site/`. Bez frameworka, bez npm, bez kroku budowania. Do repo trafia tylko `site/`, `README.md`, `.gitignore` i ten plik (biała lista w `.gitignore`).
- Podgląd lokalny: w katalogu `site/` uruchom `python -m http.server 8000` i otwórz http://localhost:8000
- **v1** (`site/*.html`, `site/css/style.css`, `site/js/main.js`): wersja podstawowa, odtworzenie makiety z Claude Design.
- **v2** (`site/v2/`): te same treści co v1 + „płynne” animacje (`v2/css/v2.css`, `v2/js/v2.js`, Lenis + GSAP z CDN). Obrazy bierze z `../img/`. Koncepcja: `site/v2/KONCEPCJA.md`.
- **v3** (`site/v3/pm-session.html`): robocza wersja jednej zakładki, nie jest podlinkowana w menu.
- Backend PHP (tylko serwer PWr): `site/api/` (formularz kontaktowy, Aktualności, ustawienia), `site/panel/` (panel administracyjny z logowaniem). `site/api/config.php` z hasłami **nigdy** nie trafia do repo.
- Tokeny wyglądu są w `:root` w `site/css/style.css`: kolory marki `--color-pink #e5185e`, `--color-purple #8b2c9c`, `--color-blue #1d46e0`, `--color-ink #141414`, tło `--color-bg #F7F6F3`. Fonty: Space Grotesk (nagłówki), Manrope (tekst). Punkty łamania: 640 / 960 / 1240 px.

## Pułapki, na które łatwo wpaść

1. **Menu i stopka są skopiowane w każdym pliku HTML.** Zmiana w stopce = ta sama zmiana we wszystkich plikach v1, v2 i v3 (na gałęzi `noc-ui` to 28 plików; sprawdź: `grep -l site-footer__brand -r site --include=*.html`).
2. **Ścieżki różnią się między wersjami:** v1 używa `img/...`, v2 i v3 `../img/...`, a `404.html` ścieżek bezwzględnych `/img/...` (bo 404 wyświetla się pod dowolnym adresem).
3. **Stopka jest ciemna (`#141414`), a CSS zamienia każdy obrazek w `.site-footer__brand` na biały kształt** (`filter: brightness(0) invert(1)`). Cudze logo (np. uczelni) wrzucone w ten blok zostanie przebarwione. Dla logotypów z księgą znaku trzeba użyć ich oficjalnej wersji na ciemne tło i nie stosować filtra.
4. **Atrybuty `data-set="..."`** (np. e-mail, linki social w stopce) wypełnia panel przez `main.js`. Nie usuwaj ich i nie zmieniaj ich wartości.
5. **Równoległe gałęzie.** Sprawdź `git log --oneline main..origin/noc-ui` i `main..origin/cms-panel`. Jeśli są tam niescalone commity, zmieniają one te same pliki HTML (m.in. stopkę). Nie zaczynaj pracy na starej bazie, tylko zapytaj, od której gałęzi wyjść.
6. Claude lubi „przy okazji uporządkować” HTML. Tutaj nie wolno: żadnego przeformatowania, zmiany wcięć ani kolejności atrybutów w liniach, których zadanie nie dotyczy. Diff ma zawierać tylko zmianę z zadania.

## Zasady przy zmianach

- Nazwy plików: małe litery, bez polskich znaków i spacji (np. `logo-pwr-240.png`).
- Nowy obraz rastrowy: `.webp` + `.png`/`.jpg` w dwóch szerokościach (1x i 2x), `<picture>` jak przy istniejących logo, atrybuty `width`, `height`, `alt`. Logo w SVG może być pojedynczym plikiem `.svg`.
- Linki między podstronami względne (`href="kontakt.html"`), bez domeny.
- Dostępność (WCAG): każdy obraz ma sensowny `alt`, przyciski to `<button>`, linki to `<a>`, kontrast tekstu ≥ 4.5:1, działa klawiatura.
- Każdą zmianę w v1 przenieś też do v2 (i v3, jeśli dotyczy), chyba że zadanie mówi inaczej.
- Sprawdź wynik w przeglądarce na szerokości 375 px i 1440 px, w v1 i w v2.
- Nie dodawaj bibliotek, narzędzi budowania, frameworków ani nowych plików konfiguracyjnych.

## Git: jak pracować, żeby nic nie zepsuć

- Pracuj na **własnej gałęzi** (np. `agnieszka/logo-uczelni`), nigdy bezpośrednio na `main`, `gh-pages`, `noc-ui` ani `cms-panel`.
- Nigdy: `git push --force`, `git reset --hard` na cudzej gałęzi, `git rebase` gałęzi wspólnej, `git subtree push`, usuwanie gałęzi, edycja plików przez stronę github.com.
- Nie ruszaj bez wyraźnej prośby: `site/api/`, `site/panel/`, `site/uploads/`, żadnego `.htaccess`, `robots.txt`, `.gitignore`.
- Małe commity z opisem po polsku, co i dlaczego (styl historii: „Stopka: logo PWr i Wydziału Zarządzania”).
- Koniec pracy = push własnej gałęzi + Pull Request do gałęzi bazowej z opisem i zrzutami ekranu. Scalanie robi Marcel.

## Co już zostało zrobione (stan: 26.09.2026)

- 16.09: v1, 12 podstron z makiety; v2 „płynna” + przełącznik wersji w stopce.
- 17.09: audyt v1/v2, naprawa logo na stronie głównej, fala podcastu, PM Session (przypinanie sekcji, kontrast).
- 23.09: Aktualności (3 wpisy od Marketingu) + panel PHP/MariaDB; formularz kontaktowy przez PHP (fallback `mailto`); decyzje: Facebook + LinkedIn, 4 etapy rekrutacji + kontakt HR, liczby PM Session bez zaokrągleń; link do formularza rekrutacyjnego Google; robocza v3 zakładki PM Session.
- 24.09: TikTok @pmgroup_ w stopce. To jest obecny stan `main` i wersji opublikowanej na GitHub Pages.
- 25–26.09, **niescalone**: `cms-panel` (panel: konta, role, dziennik zmian, ustawienia strony, członkowie, PM Session; strony prawne: polityka prywatności, deklaracja dostępności, 404, `.htaccess`, `robots.txt`) oraz `noc-ui` (zawiera `cms-panel` + nowy wygląd panelu i poprawki UI strony: Dołącz, CTA w hero, kafelki Aktualności, Case Koła).

## Decyzje otwarte (nie rozstrzygaj sam)

- Szablony PHP (`include`) zamiast powielonego menu i stopki: decyzja przy przejściu na serwer PWr.
- Która wersja (v1 czy v2) będzie główna na produkcji.
- Rzeczy „poza zakresem v2” z `site/v2/KONCEPCJA.md`.
