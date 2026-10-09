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
| `css/v2.css`, `js/v2.js` | „Płynne” animacje (Lenis + GSAP), tylko na szerokich ekranach i bez „ogranicz animacje”; opis: `KONCEPCJA-v2.md` |
| `img/` | Zdjęcia i logotypy (WebP + JPG/PNG w dwóch rozmiarach) |
| `.nojekyll` | Plik techniczny dla GitHub Pages — nie usuwać |

## Jak podejrzeć stronę na komputerze

1. Otwórz terminal w katalogu `site`.
2. Wpisz: `python -m http.server 8000`
3. W przeglądarce wejdź na: http://localhost:8000

## Backend PHP: formularz kontaktowy i panel (tylko na serwerze PWr)

Na GitHub Pages PHP nie działa — strona pokazuje wtedy treść wpisaną na sztywno w HTML, a formularz kontaktowy otwiera program pocztowy. Na serwerze z PHP 7.4 (lub nowszym) i MariaDB `js/main.js` pobiera dane z `api/*.php` i podmienia nimi treść (Aktualności, O nas, PM Session, Podcast, linki w stopce, rekrutacja na Dołącz). Jeśli API nie odpowie, zostaje treść z HTML.

| Plik / katalog | Co to jest |
|---|---|
| `api/lib.php` | Wspólne funkcje: połączenie z bazą, tworzenie tabel (`pmg_migrate()`), limit prób |
| `api/aktualnosci.php`, `api/czlonkowie.php`, `api/pmsession.php`, `api/podcast.php`, `api/ustawienia.php` | Publiczne dane dla strony (JSON, tylko opublikowane / aktywne). `pmsession.php?numer=XIV` zwraca wskazaną edycję (bieżącą albo zakończoną, nigdy szkic), bez parametru — bieżącą |
| `api/seed-podcast.php` | Cztery pierwsze odcinki podcastu — wczytywane raz, przy pierwszym utworzeniu tabeli `pmg_odcinki` |
| `api/kontakt.php` | Wysyłka wiadomości z formularza kontaktowego |
| `api/config.example.php` | Wzór konfiguracji (hasła). Na serwerze kopia jako **`pmg-config.php` poza katalogiem strony** (patrz niżej) |
| `panel/index.php` | Panel (logowanie, konta, router modułów); `panel/_*.php` to moduły: Aktualności, Członkowie, PM Session, Podcast (treści) oraz Konta, Dziennik, Ustawienia, Kopia bazy (administracja) — nie otwiera się ich bezpośrednio |
| `uploads/` | Zdjęcia wgrane z panelu (`aktualnosci/`, `czlonkowie/`, `pmsession/`, `podcast/`) — tylko na serwerze |
| `.htaccess`, `404.html`, `robots.txt` | Konfiguracja Apache, strona błędu, blokada `/panel/` i `/api/` dla wyszukiwarek |

### Gdzie położyć plik z hasłami (konfiguracja)

Plik z hasłami do bazy (`pmg-config.php`) powinien leżeć **poza katalogiem strony**, żeby nie dało się go pobrać z przeglądarki. Skopiuj `api/config.example.php` pod nazwą `pmg-config.php` do katalogu nad katalogiem głównym domeny — np. gdy strona leży w `/home/konto/public_html/`, plik ma być w `/home/konto/pmg-config.php`. Kod (`api/lib.php`, `pmg_config()`) szuka pliku w tej kolejności i bierze pierwszy istniejący:

1. zmienna środowiskowa `PMG_CONFIG` (pełna ścieżka; np. `SetEnv PMG_CONFIG /home/konto/pmg-config.php` w konfiguracji Apache),
2. `pmg-config.php` w katalogu nad katalogiem głównym domeny (`DOCUMENT_ROOT`),
3. `pmg-config.php` dwa poziomy nad `api/` (gdy strona leży w podkatalogu domeny),
4. awaryjnie `api/config.php` w katalogu strony (chroniony tylko przez `api/.htaccess` — używaj tylko, gdy hosting nie pozwala położyć pliku wyżej).

Katalog domowy konta mieści się zwykle w `open_basedir`, więc PHP może tam czytać; niedostępne ścieżki są po cichu pomijane. Opcja `tmp_dir` w pliku konfiguracji (katalog poza webrootem, tworzony z prawami 0700) przenosi tam sesje panelu i liczniki prób logowania — bez niej używany jest systemowy `/tmp`, który na hostingu współdzielonym może być wspólny z innymi kontami.

### Uruchomienie na serwerze (jednorazowo)

1. Utwórz `pmg-config.php` (patrz wyżej): dane bazy, adres nadawcy w domenie serwera, `tmp_dir`, oraz losowe hasło instalacyjne `setup_haslo` (min. 12 znaków, najlepiej 20+) — bez niego ekran pierwszego konta nie przyjmie zgłoszenia. Plik nie trafia do repozytorium.
2. Od razu po wgraniu wejdź na `…/panel/`. Przy pustej bazie kont panel pokaże **„Pierwsze konto administratora”**: hasło instalacyjne, imię i nazwisko, e-mail (login), hasło (min. 12 znaków). Tabele w bazie tworzą się same przy pierwszym wejściu do panelu (istniejące wpisy zostają); wersja schematu jest zapisana w tabeli ustawień, więc kolejne wejścia nie wykonują już migracji. **Po każdej aktualizacji strony wejdź do panelu raz** — tylko wtedy powstają nowe tabele i kolumny (np. odcinki podcastu), bo publiczne API ich nie tworzy.
3. Po założeniu konta usuń linię `setup_haslo` z pliku konfiguracji.
4. Kolejne osoby: **Konta → + Nowe konto** (rola: administrator albo redaktor z wybranymi modułami: Aktualności, Członkowie, PM Session, Podcast). Panel pokaże link ważny 72 h — skopiuj go i przekaż tej osobie (np. na Messengerze). Zapomniane hasło = **Resetuj hasło** i nowy link. Kont się nie usuwa, tylko blokuje.
5. Kopia bazy: **Kopia bazy danych** w menu panelu → przycisk „Pobierz kopię bazy” pobiera plik `.sql` (zawiera e-maile i skróty haseł — przechowuj bezpiecznie; nie zawiera zdjęć z `uploads/`). Pełna kopia kończy się linią `-- KONIEC KOPII` — jeśli jej nie ma, pobieranie zostało przerwane. Przywracanie: import w phpMyAdmin.

### Moduły panelu — co z nich wpływa na stronę

| Moduł | Gdzie na stronie | Uwagi |
|---|---|---|
| Aktualności | `aktualnosci.html`, kafelki na stronie głównej | szkic / opublikowany |
| Członkowie | `o-nas.html` | zarząd i sekcje |
| PM Session | `pm-session-xiv.html`, `pm-session-xv.html`, liczby na `pm-session.html` | Każda strona edycji ma w `<body>` atrybut `data-edycja="XIV"` / `"XV"` i pobiera dane **swojej** edycji (nie „bieżącej” z innej strony). Nowa edycja: **PM Session → + Nowa edycja** (numer rzymski, temat, data, miejsce, opcjonalny opis pod nagłówkiem), potem prelegenci (kolejność strzałkami) i harmonogram. Edycja w statusie „Szkic” nie jest publiczna. Strona nowej edycji musi mieć własny plik HTML z `data-edycja` (wzór: `pm-session-xv.html`); galeria zdjęć edycji zostaje w HTML |
| Podcast | `podcast.html` | Odcinki: numer, tytuł, data, czas, opis, prowadzący, gość, Spotify (wklej adres odcinka), Apple Podcasts, YouTube, zdjęcie 16:9, kolejność strzałkami, szkic / opublikowany. Cztery pierwsze odcinki trafiają do bazy automatycznie (`api/seed-podcast.php`). Gdy w bazie jest choć jeden odcinek (także szkic), lista na stronie pochodzi z bazy, a nie z `podcast.html`; bez backendu (GitHub Pages) zostaje treść z HTML |

Zmiany zapisane w panelu widać na stronie w ciągu 5 minut (pamięć podręczna przeglądarki).

### Co wgrać na dev.pmgroup.pwr.edu.pl

Cała zawartość `site/` **oprócz**: `graphify-out/` (narzędzie lokalne), `README.md` (opcjonalnie). Na serwerze **nie nadpisuj ani nie usuwaj**: `pmg-config.php` (leży poza katalogiem strony) albo awaryjnie `api/config.php`, oraz `uploads/` (zdjęcia z panelu).

Po wgraniu sprawdź ręcznie (lokalny serwer PHP ignoruje `.htaccess`, więc tego nie dało się przetestować): `…/api/lib.php` i `…/uploads/aktualnosci/x.php` → błąd 403; nieistniejący adres → strona 404 w stylu strony; nagłówek `X-Robots-Tag: noindex` na `dev.`.

## Zasady przy zmianach

- Linki między podstronami są względne (np. `href="kontakt.html"`) — nie dopisuj adresu domeny.
- Nazwy plików tylko małymi literami, bez polskich znaków i spacji.
- Nowe zdjęcie: dodaj wersję `.webp` i `.jpg` w dwóch szerokościach i wpisz w HTML `width` i `height` oraz opis w `alt`.
- Menu i stopka są powtórzone w każdym pliku HTML — przy zmianie popraw je we wszystkich plikach HTML (17, razem z `polityka-prywatnosci.html`, `deklaracja-dostepnosci.html`, `404.html`).
- Elementy z atrybutem `data-set` (linki społecznościowe, e-mail, rekrutacja, liczby PMS) są podmieniane wartościami z panelu — przy kopiowaniu stopki zachowaj te atrybuty.
- Dostępność: każdy obraz ma `alt`, przyciski to `<button>`, linki to `<a>`.

## Publikacja na GitHub Pages

Repozytorium: `MarcelWawrylo/pmg-strona`. Strona publikuje się z gałęzi `gh-pages`, do której trafia zawartość katalogu `site/`:

```
git subtree push --prefix site origin gh-pages
```
