# Strona koła naukowego PMG — kod

Strona to zwykłe pliki HTML, CSS i JavaScript. Nie trzeba niczego instalować ani „budować”. Te same pliki działają na GitHub Pages (wersja robocza) i na serwerze Politechniki (Apache).

## Co gdzie leży

| Plik / katalog | Co to jest |
|---|---|
| `index.html` | Strona główna |
| `o-nas.html`, `aktualnosci.html`, `pm-session.html`, `podcast.html`, `dolacz.html`, `kontakt.html` | Podstrony z menu i Aktualności |
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

## PM Session — jak ukryć sekcję „Prelegenci” albo „Harmonogram”

Obie sekcje są w pliku `pm-session.html`. Każda zaczyna się od takiej linii:

```html
<section id="prelegenci" class="pms-block container" aria-labelledby="h-speakers">
<section id="harmonogram" class="pms-block container" aria-labelledby="h-schedule">
```

Żeby **ukryć** sekcję, dopisz słowo `hidden` przed znakiem `>`:

```html
<section id="prelegenci" class="pms-block container" aria-labelledby="h-speakers" hidden>
```

Żeby ją **pokazać**, usuń słowo `hidden`. Zapisz plik i odśwież stronę. Nic więcej nie trzeba zmieniać.

## Formularz kontaktowy i panel Aktualności (PHP — tylko na serwerze PWr)

- `api/kontakt.php` wysyła wiadomość z formularza na `pmgroup.kontakt@gmail.com`. Na GitHub Pages PHP nie działa, więc przycisk „Wyślij” otwiera wtedy program pocztowy (jak dotąd).
- `panel/` to panel do dodawania wpisów w Aktualnościach (tytuł, data, kategoria, treść, zdjęcie 16:9). Wpisy trafiają do bazy MariaDB i pojawiają się na `aktualnosci.html` oraz jako 3 kafelki na stronie głównej. Bez działającego panelu widać wpisy wpisane na sztywno w HTML.

Uruchomienie na serwerze (jednorazowo):
1. Skopiuj `api/config.example.php` jako `api/config.php` i wpisz dane bazy oraz adres nadawcy w domenie serwera. `config.php` nie trafia do repozytorium.
2. Otwórz `…/panel/ustaw-haslo.php`, wpisz hasło (min. 12 znaków) i wklej pokazany hash do `config.php` jako `panel_hash`.
3. Wejdź na `…/panel/`, zaloguj się i dodaj wpis. Tabela w bazie tworzy się sama.
4. Wpis bez zaznaczonego „Opublikuj” jest szkicem i nie pokazuje się na stronie.

Uwaga przy wgrywaniu nowej wersji strony: nie nadpisuj ani nie usuwaj na serwerze `api/config.php` i `uploads/aktualnosci/` (zdjęcia z panelu).

## Zasady przy zmianach

- Linki między podstronami są względne (np. `href="kontakt.html"`) — nie dopisuj adresu domeny.
- Nazwy plików tylko małymi literami, bez polskich znaków i spacji.
- Nowe zdjęcie: dodaj wersję `.webp` i `.jpg` w dwóch szerokościach i wpisz w HTML `width` i `height` oraz opis w `alt`.
- Menu i stopka są powtórzone w każdym pliku HTML — przy zmianie popraw je we wszystkich 12 plikach.
- Dostępność: każdy obraz ma `alt`, przyciski to `<button>`, linki to `<a>`.

## Publikacja na GitHub Pages

Repozytorium: `MarcelWawrylo/pmg-strona`. Strona publikuje się z gałęzi `gh-pages`, do której trafia zawartość katalogu `site/`:

```
git subtree push --prefix site origin gh-pages
```
