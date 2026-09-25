# Strona koła naukowego PMG — kod

Strona to zwykłe pliki HTML, CSS i JavaScript. Nie trzeba niczego instalować ani „budować”. Te same pliki działają na GitHub Pages (wersja robocza) i na serwerze Politechniki (Apache).

## Co gdzie leży

| Plik / katalog | Co to jest |
|---|---|
| `index.html` | Strona główna |
| `o-nas.html`, `aktualnosci.html`, `pm-session.html`, `podcast.html`, `dolacz.html`, `kontakt.html` | Podstrony z menu i Aktualności |
| `polityka-prywatnosci.html`, `deklaracja-dostepnosci.html` | Strony prawne (link w stopce) |
| `case-kola.html` | Lista case'ów (hub) |
| `case-kola-pwr-racing-team.html`, `case-kola-debatelab.html`, `case-kola-qubit.html`, `case-kola-solvro.html` | Podstrony case'ów |
| `css/style.css` | Wygląd wszystkich podstron (kolory, fonty, układ) |
| `js/main.js` | Zachowania: menu, przewijanie, galerie, okna, formularz |
| `img/` | Zdjęcia i logotypy (WebP + JPG/PNG w dwóch rozmiarach) |
| `v2/` | Wersja 2 strony („płynna”) |
| `.nojekyll` | Plik techniczny dla GitHub Pages — nie usuwać |

## Jak podejrzeć stronę na komputerze

1. Otwórz terminal w katalogu `site`.
2. Wpisz: `python -m http.server 8000`
3. W przeglądarce wejdź na: http://localhost:8000

## Backend PHP: formularz kontaktowy i panel (tylko na serwerze PWr)

Na GitHub Pages PHP nie działa — strona pokazuje wtedy treść wpisaną na sztywno w HTML, a formularz kontaktowy otwiera program pocztowy. Na serwerze z PHP 7.4 i MariaDB `js/main.js` pobiera dane z `api/*.php` i podmienia nimi treść (Aktualności, O nas, PM Session, linki w stopce, rekrutacja na Dołącz). Jeśli API nie odpowie, zostaje treść z HTML.

| Plik / katalog | Co to jest |
|---|---|
| `api/lib.php` | Wspólne funkcje: połączenie z bazą, tworzenie tabel (`pmg_migrate()`), limit prób |
| `api/aktualnosci.php`, `api/czlonkowie.php`, `api/pmsession.php`, `api/ustawienia.php` | Publiczne dane dla strony (JSON, tylko opublikowane / aktywne / bieżąca edycja PMS) |
| `api/kontakt.php` | Wysyłka wiadomości z formularza kontaktowego |
| `api/config.example.php` | Wzór konfiguracji — na serwerze kopia jako `api/config.php` |
| `panel/index.php` | Panel (logowanie, konta, router modułów); `panel/_*.php` to moduły — nie otwiera się ich bezpośrednio |
| `uploads/` | Zdjęcia wgrane z panelu (`aktualnosci/`, `czlonkowie/`, `pmsession/`) — tylko na serwerze |
| `.htaccess`, `404.html`, `robots.txt` | Konfiguracja Apache, strona błędu, blokada `/panel/` i `/api/` dla wyszukiwarek |

### Uruchomienie na serwerze (jednorazowo)

1. Skopiuj `api/config.example.php` jako `api/config.php` i wpisz dane bazy oraz adres nadawcy w domenie serwera. `config.php` nie trafia do repozytorium.
2. Jeśli na serwerze działał już stary panel z jednym hasłem (`panel_hash` w `config.php`) — zostaw tę linię do czasu kroku 3.
3. Od razu po wgraniu wejdź na `…/panel/`. Przy pustej bazie kont panel pokaże **„Pierwsze konto administratora”**: imię i nazwisko, e-mail (login), hasło (min. 12 znaków). Przy starym `panel_hash` trzeba też podać dotychczasowe hasło panelu. Tabele w bazie tworzą się same przy wejściu do panelu (istniejące wpisy Aktualności zostają). Uwaga: dopóki pierwsze konto nie jest założone, może je założyć każdy, kto wejdzie na `/panel/` — dlatego zrób to zaraz po wgraniu.
4. Po założeniu konta usuń linię `panel_hash` z `config.php` (nie jest już używana).
5. Kolejne osoby: **Konta → + Nowe konto** (rola: administrator albo redaktor z wybranymi modułami). Panel pokaże link ważny 72 h — skopiuj go i przekaż tej osobie (np. na Messengerze). Zapomniane hasło = **Resetuj hasło** i nowy link. Kont się nie usuwa, tylko blokuje.
6. Kopia bazy: **Kopia bazy danych** w menu panelu pobiera plik `.sql` (zawiera e-maile i skróty haseł — przechowuj bezpiecznie; nie zawiera zdjęć z `uploads/`). Przywracanie: import w phpMyAdmin.

Zmiany zapisane w panelu widać na stronie w ciągu 5 minut (pamięć podręczna przeglądarki).

### Co wgrać na dev.pmgroup.pwr.edu.pl

Cała zawartość `site/` **oprócz**: `graphify-out/` (narzędzie lokalne), `README.md` (opcjonalnie). Na serwerze **nie nadpisuj ani nie usuwaj**: `api/config.php`, `uploads/` (zdjęcia z panelu). `v2/` i `v3/` są opcjonalne (wersje robocze).

Po wgraniu sprawdź ręcznie (lokalny serwer PHP ignoruje `.htaccess`, więc tego nie dało się przetestować): `…/api/lib.php` i `…/uploads/aktualnosci/x.php` → błąd 403; nieistniejący adres → strona 404 w stylu strony; nagłówek `X-Robots-Tag: noindex` na `dev.`.

## Zasady przy zmianach

- Linki między podstronami są względne (np. `href="kontakt.html"`) — nie dopisuj adresu domeny.
- Nazwy plików tylko małymi literami, bez polskich znaków i spacji.
- Nowe zdjęcie: dodaj wersję `.webp` i `.jpg` w dwóch szerokościach i wpisz w HTML `width` i `height` oraz opis w `alt`.
- Menu i stopka są powtórzone w każdym pliku HTML — przy zmianie popraw je we wszystkich plikach v1 (15, razem z `polityka-prywatnosci.html`, `deklaracja-dostepnosci.html`, `404.html`) i v2 (12).
- Elementy z atrybutem `data-set` (linki społecznościowe, e-mail, rekrutacja, liczby PMS) są podmieniane wartościami z panelu — przy kopiowaniu stopki zachowaj te atrybuty.
- Dostępność: każdy obraz ma `alt`, przyciski to `<button>`, linki to `<a>`.

## Publikacja na GitHub Pages

Repozytorium: `MarcelWawrylo/pmg-strona`. Strona publikuje się z gałęzi `gh-pages`, do której trafia zawartość katalogu `site/`:

```
git subtree push --prefix site origin gh-pages
```
