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
| `api/aktualnosci.php`, `api/czlonkowie.php`, `api/pmsession.php`, `api/podcast.php`, `api/case-kola.php`, `api/ustawienia.php` | Publiczne dane dla strony (JSON, tylko opublikowane / aktywne). `pmsession.php?numer=XIV` zwraca wskazaną edycję (bieżącą albo zakończoną, nigdy szkic), bez parametru — bieżącą |
| `api/tresci.php` + `api/tresci-pola.php` | Zakładki „Teksty na stronie”: `tresci.php` zwraca teksty zmienione w panelu (tylko niepuste, klucze z listy pól). `tresci-pola.php` to **wygenerowana z HTML** lista 88 pól (klucz, etykieta, typ, limit, tekst domyślny) — elementy HTML z atrybutem `data-tresc="strona.klucz"`. Po zmianie takiego tekstu w HTML (albo dodaniu `data-tresc`) trzeba wygenerować listę ponownie i sprawdzić zgodność z HTML (skrypty pomocnicze nie są w repo) |
| `api/seed-podcast.php` | Cztery pierwsze odcinki podcastu — wczytywane raz, przy pierwszym utworzeniu tabeli `pmg_odcinki` |
| `api/dane-startowe.php` + `panel/_import.php` | Treści, które były wpisane w HTML (3 wpisy Aktualności, 21 osób w 4 sekcjach, edycja PM Session XIV, linki/e-mail/rekrutacja, liczby PMS). W pustej bazie administrator widzi na stronach panelu Aktualności, O nas, PM Session, Case Koła oraz Stopka i kontakt przycisk „Wczytaj treści ze strony” (POST z CSRF, w transakcji, działa tylko na pustych tabelach, wpis w dzienniku). Aktualności trafiają jako opublikowane z tymczasowymi datami (15, 20 i 25.09.2026; w HTML było „do ustalenia”), edycja XIV jako zakończona z pełnymi biogramami i opisami prelekcji — strona pokazuje je z panelu w tym samym wyglądzie |
| `api/kontakt.php` | Wysyłka wiadomości z formularza kontaktowego |
| `api/config.example.php` | Wzór konfiguracji (hasła). Na serwerze kopia jako **`pmg-config.php` poza katalogiem strony** (patrz niżej) |
| `panel/index.php` | Panel (logowanie, konta, router stron i zakładek: `MODULY`, `ZAKLADKI`, `MENU`); `panel/_*.php` to pliki stron panelu: Aktualności, O nas (`_czlonkowie.php`), PM Session, Podcast, Case Koła, Rekrutacja, Teksty na stronie (`_tresci.php`) oraz Stopka i kontakt, Konta, Dziennik, Kopia (`_admin.php`) — nie otwiera się ich bezpośrednio |
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
4. Kolejne osoby: **Konta → + Nowe konto** (rola: administrator albo redaktor z wybranymi stronami: Strona główna, O nas, Aktualności, PM Session, Podcast, Case Koła, Dołącz (teksty strony), Rekrutacja (nabór i link na stronie Dołącz), Kontakt; strona = wszystko na niej razem z jej tekstami; Stopka i kontakt, Konta, Dziennik i Kopię ma tylko administrator). Panel pokaże link ważny 24 h — skopiuj go i przekaż tej osobie (np. na Messengerze). Zapomniane hasło = **Resetuj hasło** i nowy link. Kont się nie usuwa, tylko blokuje. Własne hasło każdy zmienia w **Moje konto**.
5. Kopia bazy: **Kopia bazy danych** w menu panelu → przycisk „Pobierz kopię bazy” pobiera plik `.sql` (zawiera e-maile i skróty haseł — przechowuj bezpiecznie; nie zawiera zdjęć z `uploads/`). Pełna kopia kończy się linią `-- KONIEC KOPII` — jeśli jej nie ma, pobieranie zostało przerwane. Przywracanie: import w phpMyAdmin.

### Jedyny administrator bez hasła (procedura awaryjna)

Zasada: w panelu zawsze są **co najmniej dwa aktywne konta administratora z hasłem**. Gdy zostaje jedno, panel pokazuje ostrzeżenie na stronie startowej. Własne hasło zmienia się w **Moje konto** (wymaga obecnego hasła), cudze: **Konta → Resetuj hasło**.

Gdy jedyny administrator zapomni hasła, panel nie pomoże. Potrzebna jest osoba z dostępem do phpMyAdmin bazy na serwerze (kto ma ten dostęp: do ustalenia; dane dostępowe nie trafiają do repozytorium):

1. Wygeneruj losowy ciąg, np. w Terminalu: `openssl rand -hex 32`.
2. W phpMyAdmin, zakładka SQL (wstaw ten ciąg i e-mail administratora):
   ```sql
   UPDATE pmg_uzytkownicy
   SET token_hash = SHA2('TU_WKLEJ_CIAG', 256), token_do = NOW() + INTERVAL 1 HOUR, aktywny = 1
   WHERE email = 'adres@administratora';
   ```
   Panel porównuje `token_hash` z SHA-256 ciągu z linku (`panel/index.php`, ustawienie hasła; tak samo tworzy go `panel/_admin.php` przy zaproszeniu).
3. W ciągu godziny otwórz `…/panel/?t=TU_WKLEJ_CIAG` (bez logowania) i ustaw nowe hasło (min. 12 znaków).

Procedura wynika z kodu i nie była jeszcze uruchamiana na serwerze.

### Reset hasła mailem

Domyślnie wyłączony. Do czasu włączenia zapomniane hasło resetuje administrator: **Konta → Resetuj hasło** i nowy link.

Jak włączyć (gdy działa skrzynka nadawcy): w `pmg-config.php` na serwerze ustaw trzy klucze:
- `'reset_hasla_mailem' => true`,
- `'mail_from'` — adres nadawcy w domenie serwera (np. `kontakt@pmgroup.pwr.edu.pl`),
- `'adres_panelu'` — pełny adres panelu, np. `'https://pmgroup.pwr.edu.pl/panel/'`.

Brakuje któregoś z nich = funkcja wyłączona (i ostrzeżenie w logu serwera). Wtedy pod logowaniem zostaje tekst „Nie masz hasła? Poproś administratora o link.”

Jak działa: pod logowaniem pojawia się link „Nie pamiętasz hasła?”. Po wpisaniu e-maila panel zawsze pokazuje ten sam komunikat (nie zdradza, czy konto istnieje). Mail z linkiem ważnym 1 godzinę dostaje tylko aktywne konto, które ma już hasło; obecne hasło działa, dopóki ktoś nie ustawi nowego. Konto bez hasła (świeże zaproszenie, reset przez administratora) dostaje link tylko od administratora. Limit: 5 prób na 15 minut z jednego adresu IP i 3 maile na godzinę na konto. W dzienniku: **Reset hasła — prośba o reset hasła mailem**.

Jak sprawdzić po włączeniu: wyloguj się, kliknij „Nie pamiętasz hasła?”, wpisz swój e-mail z panelu, sprawdź skrzynkę (i spam), otwórz link, ustaw nowe hasło i zaloguj się nim. Potem wpisz adres bez konta: komunikat ma być taki sam, a mail nie może przyjść. Mail nie przyszedł: sprawdź log serwera („reset hasła mailem”) i ustawienia SPF domeny nadawcy.

### Lista kontrolna przed uruchomieniem produkcji

Odhaczaj po sprawdzeniu na serwerze PWr (nie w repozytorium).

- [ ] **HTTPS, HSTS i CSP.** Przekierowanie `http://` → `https://` robi serwer PWr (nie `.htaccess`); sprawdź, że nadal działa. HSTS (`max-age=31536000`, bez `includeSubDomains`) i Content-Security-Policy są włączone w `.htaccess`, CSP panelu w `panel/.htaccess` (ta sama wartość co w `panel/index.php`). Sprawdź `curl -sI` na `/`, `/kontakt.html` i `/panel/`: strona ma CSP z `frame-src https://www.google.com https://open.spotify.com`, panel ma CSP z `frame-ancestors 'none'`, oba mają `Strict-Transport-Security`. Po zmianie skryptu `<script>…classList.add('js')</script>` w plikach HTML trzeba przeliczyć jego hash `sha256-…` w `.htaccess`.
- [ ] **`tmp_dir` w `pmg-config.php`.** Ustaw katalog poza katalogiem strony, tworzony z prawami 0700. Sprawdź, że wartość nie jest pusta, bo bez niej sesje i liczniki prób leżą we wspólnym `/tmp`.
- [ ] **Usunięte `setup_haslo`.** Po założeniu pierwszego konta usuń linię `setup_haslo` z `pmg-config.php` (krok 3 powyżej). Sprawdź, że w panelu nie pojawia się już ekran „Pierwsze konto administratora”.
- [ ] **`AllowOverride` dla `uploads/`.** Blokada PHP w `uploads/.htaccess` działa tylko, gdy Apache czyta `.htaccess` w tym katalogu. Ustal z administratorem serwera PWr, czy `AllowOverride` na to pozwala, i sprawdź, że plik `.php` w `uploads/` daje błąd 403.
- [ ] **`display_errors = Off`.** Kod ustawia to w `api/lib.php`, ale wartość z `php.ini` serwera trzeba sprawdzić (do ustalenia z administratorem serwera PWr). Błędy PHP mają trafiać tylko do logu.
- [ ] **Limity uploadu PHP.** `upload_max_filesize` i `post_max_size` co najmniej 10 MB, bo panel przyjmuje zdjęcia do 10 MB. Sprawdź też `memory_limit`: przekodowanie zdjęcia do 12 Mpix wymaga zapasu pamięci (wartość do ustalenia z administratorem serwera PWr).
- [ ] **HTTPS widziany przez PHP.** Flaga `Secure` ciasteczka panelu zależy od `$_SERVER['HTTPS']`. Jeśli HTTPS kończy się przed Apache (reverse proxy), sprawdź, czy PHP to widzi (do ustalenia z administratorem serwera PWr).

### Strony panelu — co z nich wpływa na stronę

Menu panelu odpowiada stronom serwisu (grupa **Strona**) i zadaniom administratora (grupa **Administracja**). Każda strona ma zakładki (parametr `w`); pasek zakładek widać, gdy użytkownik ma dostęp do co najmniej dwóch. Uprawnienie do strony daje wszystkie jej zakładki, łącznie z „Teksty na stronie” — wyjątek: Dołącz, gdzie Rekrutacja i teksty strony to osobne uprawnienia. Definicje: stałe `MODULY`, `ZAKLADKI` i `MENU` w `panel/index.php`.

| Strona panelu (`?m=`) | Zakładki | Uprawnienie | Gdzie na stronie | Uwagi |
|---|---|---|---|---|
| Strona główna (`glowna`) | Teksty na stronie | `glowna` | `index.html` | Bez paska zakładek (jedna zakładka) |
| O nas (`czlonkowie`) | Treść, Teksty na stronie | `czlonkowie` | `o-nas.html` | zarząd i sekcje |
| Aktualności (`aktualnosci`) | Treść, Teksty na stronie | `aktualnosci` | `aktualnosci.html`, kafelki na stronie głównej | szkic / opublikowany |
| PM Session (`pmsession`) | Treść, Teksty na stronie (razem z podstronami XIV i XV) | `pmsession` | `pm-session-xiv.html`, `pm-session-xv.html`, liczby na `pm-session.html` | Każda strona edycji ma w `<body>` atrybut `data-edycja="XIV"` / `"XV"` i pobiera dane **swojej** edycji (nie „bieżącej” z innej strony). Nowa edycja: **PM Session → + Nowa edycja** (numer rzymski, temat, data, miejsce, opcjonalny opis pod nagłówkiem), po zapisaniu panel od razu otwiera widok edycji, gdzie dodajesz prelegentów (opis prelekcji, biogram z akapitami, zdjęcie 4:3, kolejność strzałkami) i harmonogram (opcjonalny znacznik pod godziną, np. „3 sesje równoległe”). Edycja w statusie „Szkic” nie jest publiczna. Strona nowej edycji musi mieć własny plik HTML z `data-edycja` (wzór: `pm-session-xv.html`); galeria zdjęć edycji zostaje w HTML |
| Podcast (`podcast`) | Odcinki, Edycje podcastu (`w=edycje`), Teksty na stronie | `podcast` | `podcast.html` | Odcinki: numer, tytuł, data, czas, opis, prowadzący, gość, Spotify (wklej adres odcinka), Apple Podcasts, YouTube, zdjęcie 16:9, kolejność strzałkami, szkic / opublikowany. Cztery pierwsze odcinki trafiają do bazy automatycznie (`api/seed-podcast.php`). Gdy w bazie jest choć jeden odcinek (także szkic), lista na stronie pochodzi z bazy, a nie z `podcast.html`; bez backendu (GitHub Pages) zostaje treść z HTML |
| Case Koła (`case`) | Treść, Teksty na stronie | `case` | `case-kola.html`, `case-kola-edycja.html?nr=N` | Edycje: numer, nazwa i logo (karta w hubie), nagłówek, zdjęcie główne 16:9, teksty sekcji (O partnerze, Wyzwanie, Co zrobiliśmy, Rezultat, komunikat „w toku”), galeria zdjęć 16:9, kolejność strzałkami, ukrywanie. Puste pola są pomijane na stronie. Menu Case Koła też pochodzi z panelu. Stare pliki `case-kola-*.html` zostają pod dotychczasowymi adresami. |
| Dołącz (`dolacz`) | Rekrutacja, Teksty na stronie | `rekrutacja` / `dolacz` (osobno) | `dolacz.html` | Rekrutacja: nabór otwarty / zamknięty, link do formularza, krótki tekst. Redaktor z samym `rekrutacja` widzi tylko Rekrutację (bez paska zakładek) |
| Kontakt (`kontakt`) | Teksty na stronie | `kontakt` | `kontakt.html` | Bez paska zakładek |
| Stopka i kontakt (`stopka`) | E-mail i media, Teksty w stopce | tylko administrator | stopka każdej strony, e-mail na Kontakcie | Dawne „Ustawienia strony” i część „Stopka i wspólne” dawnych Treści stron. Przycisk „Wczytaj treści ze strony” dla tekstów całej witryny jest tylko w „Teksty w stopce” |
| Konta, Dziennik zmian, Kopia zapasowa | — | tylko administrator | — | Kopia: ekran z przyciskiem, plik `.sql` pobiera się dopiero po kliknięciu |

**Teksty na stronie:** nagłówki, opisy, napisy przycisków i adres w stopce jako zwykły tekst (nowa linia = `<br>`, pusta linia w akapicie = nowy akapit; bez HTML). Puste pole albo „Przywróć tekst ze strony” = tekst z HTML. Zakładka zapisuje tylko teksty swojej strony (klucze spoza niej są pomijane). Nie obejmuje nagłówków z wyróżnieniem graficznym (np. hero strony głównej i Kontaktu).

**Stare adresy** (zakładki w przeglądarce, linki w notatkach) przekierowują: `?m=tresci&z=…` → strona tej części z `w=teksty` (bez `z` → Strona główna), `?m=ustawienia` → `?m=stopka`, `?m=rekrutacja` → `?m=dolacz&w=rekrutacja`. O dostępie decyduje strona docelowa. W dzienniku stare wpisy mają polskie nazwy (Teksty na stronie, Stopka i kontakt, Rekrutacja, Konto).

Zmiany zapisane w panelu widać na stronie w ciągu 5 minut (pamięć podręczna przeglądarki).

### Co wgrać na dev.pmgroup.pwr.edu.pl

Cała zawartość `site/` **oprócz**: `graphify-out/` (narzędzie lokalne), `README.md` (opcjonalnie). Na serwerze **nie nadpisuj ani nie usuwaj**: `pmg-config.php` (leży poza katalogiem strony) albo awaryjnie `api/config.php`, oraz `uploads/` (zdjęcia z panelu).

Wgrywanie z Windowsa (`sftp put -r`) zostawia katalogi z prawami tylko dla właściciela i strona zwraca 403 „Server unable to read htaccess file”. Po każdym wgraniu ustaw prawa (konto `pmgroup.pwr`, klucz SSH):
`ssh -p 2022 pmgroup.pwr@host.wcss.pl "cd public_html && find css js fonts img panel api uploads -type d -exec chmod 755 {} + && find css js fonts img panel api -type f -exec chmod 644 {} + && chmod 644 .htaccess robots.txt *.html uploads/.htaccess uploads/aktualnosci/.htaccess"`

Po wgraniu sprawdź ręcznie (lokalny serwer PHP ignoruje `.htaccess`, więc tego nie dało się przetestować): `…/api/lib.php`, `…/api/config.example.php`, `…/api/dane-startowe.php`, `…/api/seed-podcast.php`, `…/api/tresci-pola.php` i `…/uploads/aktualnosci/x.php` → błąd 403, a `…/api/aktualnosci.php` → dane JSON (publiczne endpointy działają); nieistniejący adres → strona 404 w stylu strony; nagłówek `X-Robots-Tag: noindex` na `dev.`.

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
