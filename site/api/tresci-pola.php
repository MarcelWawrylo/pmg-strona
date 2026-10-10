<?php
// Lista pól modułu „Treści stron”: klucz => etykieta, typ, limit znaków i tekst domyślny (taki jak w HTML).
// WYGENEROWANE skryptem z plików HTML (elementy z atrybutem data-tresc) — nie edytuj ręcznie. Po zmianie tekstu
// w HTML albo dodaniu data-tresc wygeneruj plik ponownie; test „porównanie z HTML” musi przejść.
// typ: 'krotki' = jedna linia (pole input), 'tekst' = wiele linii (textarea; pusta linia = nowy akapit, nowa linia = <br>).
return [
  'zakladki' => [
    'index' => [
      'etykieta' => 'Strona główna',
      'plik' => 'index.html',
      'pola' => [
        'index.hero_opis' => [
          'etykieta' => 'Hero: opis pod nagłówkiem',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'PMG to studenckie koło naukowe poświęcone zarządzaniu projektami przy Wydziale Zarządzania na Politechnice Wrocławskiej.',
        ],
        'index.hero_przycisk' => [
          'etykieta' => 'Hero: przycisk (przewija do projektów)',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Nasze projekty',
        ],
        'index.hero_link' => [
          'etykieta' => 'Hero: link obok przycisku (prowadzi do O nas)',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Poznaj nas',
        ],
        'index.aktualnosci_naglowek' => [
          'etykieta' => 'Aktualności: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Aktualności',
        ],
        'index.aktualnosci_link' => [
          'etykieta' => 'Aktualności: link do wszystkich aktualności',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Zobacz wszystkie',
        ],
        'index.projekty_naglowek' => [
          'etykieta' => 'Nasze projekty: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Nasze projekty',
        ],
        'index.projekt_1_tytul' => [
          'etykieta' => 'Nasze projekty: tytuł karty 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Project Management Session',
        ],
        'index.projekt_1_opis' => [
          'etykieta' => 'Nasze projekty: opis karty 1',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Flagowy projekt, czyli coroczna konferencja poświęcona zarządzaniu projektami.',
        ],
        'index.projekt_1_zacheta' => [
          'etykieta' => 'Nasze projekty: napis zachęty na karcie 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Poznaj',
        ],
        'index.projekt_2_tytul' => [
          'etykieta' => 'Nasze projekty: tytuł karty 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Case Koła',
        ],
        'index.projekt_2_opis' => [
          'etykieta' => 'Nasze projekty: opis karty 2',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Rozwiązywanie problemów innych organizacji, praca na żywym organizmie.',
        ],
        'index.projekt_2_zacheta' => [
          'etykieta' => 'Nasze projekty: napis zachęty na karcie 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Odkryj',
        ],
        'index.projekt_3_tytul' => [
          'etykieta' => 'Nasze projekty: tytuł karty 3',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Podcast',
        ],
        'index.projekt_3_opis' => [
          'etykieta' => 'Nasze projekty: opis karty 3',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Chcesz posłuchać rozmów z doświadczonymi praktykami zarządzania?',
        ],
        'index.projekt_3_zacheta' => [
          'etykieta' => 'Nasze projekty: napis zachęty na karcie 3',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Posłuchaj',
        ],
        'index.dolacz_naglowek' => [
          'etykieta' => 'Dołącz: nagłówek dużej karty',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Dołącz do zespołu i bądź częścią PMG!',
        ],
        'index.kafel_1_tytul' => [
          'etykieta' => 'Linki na dole: tytuł kafelka 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'O nas',
        ],
        'index.kafel_1_opis' => [
          'etykieta' => 'Linki na dole: opis kafelka 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Zarząd, sekcje, struktura i historia koła.',
        ],
        'index.kafel_2_tytul' => [
          'etykieta' => 'Linki na dole: tytuł kafelka 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Kontakt',
        ],
        'index.kafel_2_opis' => [
          'etykieta' => 'Linki na dole: opis kafelka 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Napisz, jeśli chcesz dowiedzieć się więcej lub nawiązać współpracę.',
        ],
      ],
    ],
    'onas' => [
      'etykieta' => 'O nas',
      'plik' => 'o-nas.html',
      'pola' => [
        'onas.hero_naglowek' => [
          'etykieta' => 'Hero: nagłówek',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Kto tworzy Project Management Group?',
        ],
        'onas.hero_opis' => [
          'etykieta' => 'Hero: opis pod nagłówkiem',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Project Management Group tworzy społeczność ambitnych, odpowiedzialnych i kreatywnych studentów pełnych zapału, których łączy zainteresowanie tematyką zarządzania projektami. Stawiamy na praktyczne doświadczenie, bo uważamy, że przynosi ono większe korzyści niż sama wiedza z wykładów. Jesteśmy zespołem, który daje sobie nawzajem możliwość przetestowania swoich umiejętności w realnych projektach i stworzenia przestrzeni do rozwoju.',
        ],
        'onas.wizja_naglowek' => [
          'etykieta' => 'Wizja, misja, cel: nagłówek „Wizja”',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Wizja',
        ],
        'onas.wizja_tekst' => [
          'etykieta' => 'Wizja, misja, cel: tekst „Wizja”',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Dążymy do stworzenia jedynego na Politechnice Wrocławskiej środowiska, w którym świat akademicki realnie spotyka się z biznesem, a teoria zarządzania projektami płynnie łączy się z praktyką. Chcemy być przestrzenią, w której studenci – bez względu na kierunek – zamieniają swoje pomysły w realne działania i zdobywają kompetencje potrzebne do pewnego startu w zawodowym świecie.',
        ],
        'onas.misja_naglowek' => [
          'etykieta' => 'Wizja, misja, cel: nagłówek „Misja”',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Misja',
        ],
        'onas.misja_tekst' => [
          'etykieta' => 'Wizja, misja, cel: tekst „Misja”',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Naszą misją jest łączenie świata akademickiego z biznesem poprzez praktyczny rozwój studentów w obszarze zarządzania projektami. Realizujemy realne wyzwania – od doradztwa dla innych organizacji studenckich, przez rozmowy z ekspertami branży, po własne konferencje i warsztaty – żeby każdy, niezależnie od kierunku studiów, mógł zdobyć kompetencje potrzebne do prowadzenia zespołów i projektów w prawdziwym świecie.',
        ],
        'onas.cel_naglowek' => [
          'etykieta' => 'Wizja, misja, cel: nagłówek „Cel”',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Cel',
        ],
        'onas.cel_tekst' => [
          'etykieta' => 'Wizja, misja, cel: tekst „Cel”',
          'typ' => 'tekst',
          'max' => 1611,
          'domyslny' => 'Chcemy być na Politechnice Wrocławskiej tym, czym PWr Racing Team jest dla świata technicznego – pierwszym skojarzeniem, gdy ktoś myśli o zarządzaniu projektami i biznesie. Wierzymy, że nie ma dobrego projektu bez inżynierów i specjalistów, ale nie ma też projektu bez tych, którzy nim zarządzają. Dlatego docieramy z naszą wiedzą do studentów wszystkich kierunków, pokazując, że PWr to nie tylko technologia, ale i biznes. Zaczynamy od naszej uczelni, a naszą ambicją jest obecność tam, gdzie dzieje się zarządzanie projektami w Polsce.',
        ],
        'onas.sekcje_naglowek' => [
          'etykieta' => 'Nasze sekcje: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Nasze sekcje',
        ],
        'onas.sekcja_1_nazwa' => [
          'etykieta' => 'Nasze sekcje: nazwa sekcji 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Finanse i Logistyka',
        ],
        'onas.sekcja_1_opis' => [
          'etykieta' => 'Nasze sekcje: opis sekcji 1',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Zajmujemy się finansami koła, bilansami i ofertami, a także organizacją wydarzeń, przygotowaniem materiałów i obsługą komunikatorów. Dbamy, by projekty były dopięte na ostatni guzik.',
        ],
        'onas.sekcja_2_nazwa' => [
          'etykieta' => 'Nasze sekcje: nazwa sekcji 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Partnerzy i Kontakty',
        ],
        'onas.sekcja_2_opis' => [
          'etykieta' => 'Nasze sekcje: opis sekcji 2',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'My reprezentujemy koło w terenie. Jako pierwsi nawiązujemy kontakt z prelegentami zewnętrznymi i pozyskujemy partnerów do naszych wydarzeń. To z nami poznasz najciekawszych ludzi „świata PM-u”!',
        ],
        'onas.sekcja_3_nazwa' => [
          'etykieta' => 'Nasze sekcje: nazwa sekcji 3',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Marketing',
        ],
        'onas.sekcja_3_opis' => [
          'etykieta' => 'Nasze sekcje: opis sekcji 3',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Jesteśmy odpowiedzialni za promowanie koła. To właśnie my tworzymy koncepcje marketingowe oraz odpowiadamy za nasze profile społecznościowe na Facebooku i LinkedInie. Tworzymy też grafiki do wydarzeń i plakatów. Chcesz dołożyć do tego swój wkład?',
        ],
        'onas.sekcja_4_nazwa' => [
          'etykieta' => 'Nasze sekcje: nazwa sekcji 4',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'HR',
        ],
        'onas.sekcja_4_opis' => [
          'etykieta' => 'Nasze sekcje: opis sekcji 4',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Jeśli lubisz wyzwania związane z organizacją rekrutacji lub wiążesz swoją przyszłość z zarządzaniem zasobami ludzkimi – to miejsce właśnie dla Ciebie! Dbamy o to, żeby atmosfera w ciągu roku sprzyjała miłej i przyjemnej pracy. Integracje i wyjazdy – to właśnie nasze zadanie.',
        ],
        'onas.struktura_naglowek' => [
          'etykieta' => 'Struktura koła: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Struktura koła',
        ],
      ],
    ],
    'pms' => [
      'etykieta' => 'PM Session',
      'plik' => 'pm-session.html',
      'pola' => [
        'pms.wstep_naglowek' => [
          'etykieta' => 'Wstęp: nagłówek',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Konferencja naukowa poświęcona zarządzaniu projektami',
        ],
        'pms.about_naglowek' => [
          'etykieta' => 'Co to Project Management Session?: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Co to Project Management Session?',
        ],
        'pms.about_akapit_1' => [
          'etykieta' => 'Co to Project Management Session?: akapit 1',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'PM Session to coroczna konferencja biznesowo-naukowa poświęcona zarządzaniu projektami, organizowana przez Koło Naukowe Project Management Group przy Wydziale Zarządzania Politechniki Wrocławskiej. To wydarzenie łączące świat akademicki z praktyką biznesową, skierowane zarówno do osób stawiających pierwsze kroki w project management, jak i do doświadczonych specjalistów.',
        ],
        'pms.about_akapit_2' => [
          'etykieta' => 'Co to Project Management Session?: akapit 2',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'W programie znajdują się prelekcje ekspertów, praktyczne warsztaty oraz networking, które pozwalają zdobywać nową wiedzę, rozwijać kompetencje i wymieniać się doświadczeniami. PM Session to także przestrzeń do poznawania inspirujących osób, budowania wartościowych relacji i odkrywania nowych możliwości rozwoju w świecie zarządzania projektami.',
        ],
        'pms.fakt_1_pytanie' => [
          'etykieta' => 'Pytania i odpowiedzi: pytanie 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Po co?',
        ],
        'pms.fakt_1_odpowiedz' => [
          'etykieta' => 'Pytania i odpowiedzi: odpowiedź 1',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Poznanie praktycznych aspektów zarządzania projektami, wymiana doświadczeń oraz możliwość skonfrontowania wiedzy akademickiej z praktyką biznesową.',
        ],
        'pms.fakt_2_pytanie' => [
          'etykieta' => 'Pytania i odpowiedzi: pytanie 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Dla kogo?',
        ],
        'pms.fakt_2_odpowiedz' => [
          'etykieta' => 'Pytania i odpowiedzi: odpowiedź 2',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Dla studentów zainteresowanych zarządzaniem projektami oraz osób, które zdobywają lub posiadają doświadczenie w pracy projektowej.',
        ],
        'pms.fakt_3_pytanie' => [
          'etykieta' => 'Pytania i odpowiedzi: pytanie 3',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Kogo spotkasz?',
        ],
        'pms.fakt_3_odpowiedz' => [
          'etykieta' => 'Pytania i odpowiedzi: odpowiedź 3',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Przedstawicieli biznesu i nauki – m.in. menedżerów, konsultantów, przedsiębiorców, liderów biznesu, specjalistów IT i finansów oraz wykładowców Politechniki Wrocławskiej.',
        ],
        'pms.fakt_4_pytanie' => [
          'etykieta' => 'Pytania i odpowiedzi: pytanie 4',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Jak wygląda dzień?',
        ],
        'pms.fakt_4_odpowiedz' => [
          'etykieta' => 'Pytania i odpowiedzi: odpowiedź 4',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Prelekcje, praktyczne warsztaty i networking, które pozwalają poszerzyć wiedzę, rozwinąć kompetencje i wymienić się doświadczeniami.',
        ],
        'pms.fakt_5_pytanie' => [
          'etykieta' => 'Pytania i odpowiedzi: pytanie 5',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Kiedy?',
        ],
        'pms.fakt_5_odpowiedz' => [
          'etykieta' => 'Pytania i odpowiedzi: odpowiedź 5',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'PM Session odbywa się raz w roku, wiosną, na kampusie Politechniki Wrocławskiej.',
        ],
        'pms.fakt_6_pytanie' => [
          'etykieta' => 'Pytania i odpowiedzi: pytanie 6',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Kto organizuje?',
        ],
        'pms.fakt_6_odpowiedz' => [
          'etykieta' => 'Pytania i odpowiedzi: odpowiedź 6',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Koło Naukowe Project Management Group działające przy Wydziale Zarządzania Politechniki Wrocławskiej. Za organizację wydarzenia odpowiada zespół członków Koła.',
        ],
        'pms.liczby_naglowek' => [
          'etykieta' => 'PM Session w liczbach: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'PM Session w liczbach',
        ],
      ],
    ],
    'pms14' => [
      'etykieta' => 'PM Session XIV',
      'plik' => 'pm-session-xiv.html',
      'pola' => [
        'pms14.harmonogram_naglowek' => [
          'etykieta' => 'Harmonogram: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Harmonogram',
        ],
        'pms14.prelegenci_naglowek' => [
          'etykieta' => 'Prelegenci: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Prelegenci',
        ],
        'pms14.galeria_naglowek' => [
          'etykieta' => 'Galeria: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Galeria',
        ],
        'pms14.galeria_1_podpis' => [
          'etykieta' => 'Galeria: podpis zdjęcia 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Warsztat podczas PM Session XIV',
        ],
        'pms14.galeria_2_podpis' => [
          'etykieta' => 'Galeria: podpis zdjęcia 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Prelekcja w auli podczas PM Session XIV',
        ],
        'pms14.galeria_3_podpis' => [
          'etykieta' => 'Galeria: podpis zdjęcia 3',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Zespół organizacyjny PMG na PM Session XIV',
        ],
        'pms14.galeria_4_podpis' => [
          'etykieta' => 'Galeria: podpis zdjęcia 4',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Identyfikatory prelegentów PM Session XIV',
        ],
        'pms14.galeria_5_podpis' => [
          'etykieta' => 'Galeria: podpis zdjęcia 5',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Prezentacja planu projektu podczas warsztatu',
        ],
      ],
    ],
    'pms15' => [
      'etykieta' => 'PM Session XV',
      'plik' => 'pm-session-xv.html',
      'pola' => [
        'pms15.harmonogram_naglowek' => [
          'etykieta' => 'Harmonogram: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Harmonogram',
        ],
        'pms15.prelegenci_naglowek' => [
          'etykieta' => 'Prelegenci: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Prelegenci',
        ],
        'pms15.wkrotce' => [
          'etykieta' => 'Zapowiedź: tekst „wkrótce”',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Więcej informacji o XV edycji konferencji PM Session wkrótce!',
        ],
      ],
    ],
    'case' => [
      'etykieta' => 'Case Koła',
      'plik' => 'case-kola.html',
      'pola' => [
        'case.hero_naglowek' => [
          'etykieta' => 'Hero: nagłówek',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Analiza problemów
i dedykowane rozwiązania',
        ],
        'case.hero_opis' => [
          'etykieta' => 'Hero: opis pod nagłówkiem',
          'typ' => 'tekst',
          'max' => 1971,
          'domyslny' => 'Case Koła jest inicjatywą mającą na celu świadczenie wsparcia innym organizacjom studenckim. Oferujemy doradztwo w kwestiach związanych z wybranym w ramach współpracy obszarem (np. planowanie wydarzeń, efektywne zarządzanie strukturami organizacyjnymi lub realizacja projektów). Poprzez udział w projekcie organizacje studenckie nie tylko pozyskują konkretne wskazówki dotyczące obszarów, w których potrzebują wsparcia, ale także tworzą trwałe relacje partnerskie, przyczyniające się do wzajemnego rozwoju i osiągnięć całego środowiska studenckiego. Case Koła to program wsparcia oraz możliwość aktywnego rozwoju i synergii między organizacjami studenckimi.',
        ],
      ],
    ],
    'podcast' => [
      'etykieta' => 'Podcast',
      'plik' => 'podcast.html',
      'pola' => [
        'podcast.hero_naglowek' => [
          'etykieta' => 'Hero: nagłówek',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Rozmowy o zarządzaniu, karierze
i biznesie.',
        ],
        'podcast.hero_opis' => [
          'etykieta' => 'Hero: opis pod nagłówkiem',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Projekt Podcast wystartował pod koniec 2025. Nagrywamy w studiu Akademickiego Radia LUZ na PWr.',
        ],
      ],
    ],
    'aktualnosci' => [
      'etykieta' => 'Aktualności',
      'plik' => 'aktualnosci.html',
      'pola' => [
        'aktualnosci.hero_naglowek' => [
          'etykieta' => 'Hero: nagłówek',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Co w trawie piszczy?',
        ],
        'aktualnosci.hero_opis' => [
          'etykieta' => 'Hero: opis pod nagłówkiem',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Przeczytaj, co nowego wydarzyło się w naszym kole',
        ],
      ],
    ],
    'dolacz' => [
      'etykieta' => 'Dołącz',
      'plik' => 'dolacz.html',
      'pola' => [
        'dolacz.hero_naglowek' => [
          'etykieta' => 'Hero: nagłówek',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Rekrutacja',
        ],
        'dolacz.formularz_naglowek' => [
          'etykieta' => 'Formularz: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Formularz rekrutacyjny',
        ],
        'dolacz.formularz_link' => [
          'etykieta' => 'Formularz: link do strony O nas (pod formularzem)',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Informacje o każdej sekcji znajdziesz w zakładce „O nas”',
        ],
        'dolacz.proces_naglowek' => [
          'etykieta' => 'Proces rekrutacyjny: nagłówek sekcji',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Proces rekrutacyjny',
        ],
        'dolacz.krok_1_tytul' => [
          'etykieta' => 'Proces rekrutacyjny: tytuł kroku 1',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => '01 · Formularz zgłoszeniowy',
        ],
        'dolacz.krok_1_opis' => [
          'etykieta' => 'Proces rekrutacyjny: opis kroku 1',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Wypełniasz krótki formularz: kilka słów o sobie i sekcja, która Cię interesuje.',
        ],
        'dolacz.krok_2_tytul' => [
          'etykieta' => 'Proces rekrutacyjny: tytuł kroku 2',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => '02 · Koordynatorzy czytają zgłoszenia',
        ],
        'dolacz.krok_2_opis' => [
          'etykieta' => 'Proces rekrutacyjny: opis kroku 2',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Przeglądamy odpowiedzi i odzywamy się mailem z zaproszeniem i terminem rozmowy.',
        ],
        'dolacz.krok_3_tytul' => [
          'etykieta' => 'Proces rekrutacyjny: tytuł kroku 3',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => '03 · Rozmowa rekrutacyjna',
        ],
        'dolacz.krok_3_opis' => [
          'etykieta' => 'Proces rekrutacyjny: opis kroku 3',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Spotkanie online, na którym opowiadasz o sobie, a my o kole.',
        ],
        'dolacz.krok_4_tytul' => [
          'etykieta' => 'Proces rekrutacyjny: tytuł kroku 4',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => '04 · Decyzja',
        ],
        'dolacz.krok_4_opis' => [
          'etykieta' => 'Proces rekrutacyjny: opis kroku 4',
          'typ' => 'tekst',
          'max' => 1500,
          'domyslny' => 'Dajemy Ci znać, czy zapraszamy Cię do koła.',
        ],
      ],
    ],
    'kontakt' => [
      'etykieta' => 'Kontakt',
      'plik' => 'kontakt.html',
      'pola' => [
        'kontakt.mail_naglowek' => [
          'etykieta' => 'Dane kontaktowe: nagłówek „Napisz do nas maila”',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Napisz do nas maila',
        ],
        'kontakt.adres_naglowek' => [
          'etykieta' => 'Dane kontaktowe: nagłówek „Gdzie nas znajdziesz”',
          'typ' => 'krotki',
          'max' => 300,
          'domyslny' => 'Gdzie nas znajdziesz',
        ],
      ],
    ],
    'wspolne' => [
      'etykieta' => 'Stopka i wspólne',
      'plik' => '',
      'pola' => [
        'stopka.adres' => [
          'etykieta' => 'Stopka i wspólne: adres koła (dwie linie: ulica i wydział)',
          'typ' => 'tekst',
          'max' => 300,
          'domyslny' => 'Łukasiewicza 5, 50-370 Wrocław
Wydział Zarządzania Politechniki Wrocławskiej',
          'gdzie' => 'stopka każdej strony i strona Kontakt',
        ],
        'stopka.przycisk_gora' => [
          'etykieta' => 'Stopka i wspólne: przycisk „Wróć na górę”',
          'typ' => 'krotki',
          'max' => 100,
          'domyslny' => 'Wróć na górę',
          'gdzie' => 'stopka każdej strony',
        ],
      ],
    ],
  ],
];
