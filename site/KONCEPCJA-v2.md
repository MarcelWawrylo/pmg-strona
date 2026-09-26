# Wersja 2 — koncepcja („płynna”)

Stan: 16.09.2026. Te same treści i poprawki co v1; zmienia się doświadczenie. Źródła: **[P]** dokument „Pomysły”, **[I]** analiza stron-inspiracji (`materialy/inspiracje/`), **[S]** skill `frontend-design`, **[G]** druga opinia Gemini, **[W]** wymagania sesji.

## Zasady
- **Jedno wyraziste miejsce na podstronę, reszta spokojna.** Bez animowania każdej karty. [S]
- **Rozszerzenie, nie przebudowa:** v2 to HTML z v1 + `v2/css/v2.css` + `v2/js/v2.js`. Bez JS i na mobile strona działa jak v1. [W]
- **Tryb pełny** tylko na ekranach ≥ 961 px bez `prefers-reduced-motion`. Wtedy `v2.js` doładowuje biblioteki. Na mobile i przy ograniczonym ruchu nie ma bibliotek ani WebGL, zostaje statyczna wersja v1. [W] [I]
- **Fonty bez zmian** (Space Grotesk + Manrope), ale większa skala i ciaśniejsze wiersze nagłówków. Satoshi — do decyzji. [I theartofcinema]

## Biblioteki (CDN jsDelivr, przypięte wersje, SRI)
| Biblioteka | Rozmiar | Po co |
|---|---|---|
| Lenis 1.3.4 | 16 KB | płynny scroll [I affinity, theartofcinema] |
| GSAP 3.13.0 | 72 KB | animacje sterowane przewijaniem [I] |
| GSAP ScrollTrigger 3.13.0 | 44 KB | przypinanie sekcji, `scrub` [I] |

**three.js odrzucony:** `three.module.min.js` ma 692 KB, a budżet JS wynosi ≤ 400 KB. WebGL piszę sam: jeden shader (~ kilka KB). [W] Gemini proponował three.js [G] — odrzucone z tego powodu.

## Pomysł na każdą część
1. **Strona główna — logo w WebGL.** Kolor przepływa falą przez słupki logo i przez słowo „naprawdę” [P]. Słupki rysuje shader, a przesunięcie za kursorem daje efekt głębi [I x-mengto: 3D hero]. Scena pauzuje poza ekranem i na ukrytej karcie [W]. Fallback: animowane logo CSS z v1.
2. **Nawigacja** po przewinięciu zamienia się w pływającą „pigułkę” — samym CSS. [I tovoda] [G]
3. **PM Session — zdanie koloruje się słowo po słowie.** Sekcja „Co to PM Session?” jest przypięta, a słowa opisu zmieniają kolor z szarego na czarny w rytm przewijania [P] [I affinity]. Pełne zdanie zostaje dla czytników ekranu (`aria-label` + `aria-hidden` na fragmentach) [G].
4. **Podcast — fala dźwiękowa.** Stale poruszająca się fala na canvasie. „Lupa” powiększa fragment pod kursorem. 4 segmenty fali to 4 odcinki [P] [I mainframe]. Każdy segment ma przezroczysty `<button>`, więc falę obsłuży też klawiatura. Klik otwiera to samo okno odcinka co karta. Dźwięk nigdy nie włącza się sam [I]. Lista odcinków zostaje bez zmian.
5. **Dołącz — ścieżka procesu.** Etapy rekrutacji łączy ścieżka SVG rysowana przy przewijaniu [P] [I tovoda].
6. **Case Koła — przejście do case'u.** Zdjęcie z karty w hubie płynnie przechodzi w hero podstrony (CSS View Transitions, bez biblioteki; w przeglądarkach bez wsparcia zwykłe przejście). Pozostałe podstrony: miękkie przejście między stronami. [W: „przejścia”]
7. **O nas, Aktualności, Kontakt** — tylko płynny scroll i przejścia. [S]

## Wydajność i dostępność
- JS v2 razem z bibliotekami ≤ 400 KB, mierzone skryptem `weight.js`.
- WebGL: `devicePixelRatio` ≤ 1,5; `IntersectionObserver` i `visibilitychange` wstrzymują scenę.
- Canvas i SVG są dekoracyjne (`aria-hidden`), a obok zostaje zwykły HTML.
- Lenis nie psuje skoków do kotwic ani focusu; linki do kotwic przewija przez `lenis.scrollTo`.
- Kontrast, focus, obsługa klawiatury i Esc — jak w v1.

## Poza zakresem v2 (do decyzji)
Mysz w trawie (Aktualności), dźwięk po najechaniu na falę, przełącznik motywu, „Zgłoś błąd”, rekrutacja w iFrame [P]. Szablony PHP (`include`) zamiast powielonego menu — do decyzji przy przejściu na serwer PWr [G].
