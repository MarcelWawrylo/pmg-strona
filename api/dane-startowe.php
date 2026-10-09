<?php
// Dane startowe panelu: treści, które do tej pory były wpisane na sztywno w plikach HTML strony.
// Wczytywane na żądanie administratora przyciskiem „Wczytaj treści ze strony” (panel/_import.php) do PUSTYCH tabel.
// Źródło (stan HTML z 9.10.2026): aktualnosci.html (3 wpisy), o-nas.html (sekcje i struktura koła), pm-session-xiv.html
// (edycja XIV, prelegenci, harmonogram), stopka index.html i dolacz.html (linki, e-mail, rekrutacja), pm-session.html (liczby).
// Plik zwraca tablicę (nie JSON), żeby nie dało się go pobrać jako tekst. Zdjęcia: ścieżki do istniejących plików w img/
// (strona składa adres jako katalog_strony + ścieżka, panel jako ../ + ścieżka) — nic nie jest kopiowane do uploads/.
// Pominięte, bo ich nie ma w HTML: rekrutacja_tekst (zostaje pusty), opisy prelekcji (tylko w HTML), edycja XV.
return [
    // Ustawienia ogólne (Ustawienia strony).
    'ustawienia' => [
        'instagram' => 'https://www.instagram.com/pmgroup_pwr/',
        'facebook' => 'https://www.facebook.com/PMGroupPWr',
        'linkedin' => 'https://www.linkedin.com/company/project-management-group-pmg/',
        'tiktok' => 'https://www.tiktok.com/@pmgroup_',
        'email' => 'pmgroup.kontakt@gmail.com',
        'rekrutacja_otwarta' => '1',
        'rekrutacja_link' => 'https://docs.google.com/forms/d/e/1FAIpQLSdkxTQxnu-W3-GALJ9eVjRGK6Q7pfnFZ5SbGeVr137aiUrDbw/viewform',
    ],
    // Liczby „PM Session w liczbach” — wczytywane razem z edycją XIV (moduł PM Session).
    'ustawienia_pms' => [
        'pms_edycji' => '14',
        'pms_prelekcji' => '68',
        'pms_prelegentow' => '201',
        'pms_uczestnikow' => '1604',
        'pms_warsztatow' => '111',
        'pms_symulacji' => '8',
    ],
    // Aktualności. W HTML data to „do ustalenia”, a kolumna data jest NOT NULL: null = data dnia wczytania, wpis jako SZKIC.
    // Kolejność tablicy = kolejność wstawiania; najnowszy id (ostatni) trafia na stronie na górę, więc tablica jest odwrócona
    // względem HTML (HTML: stary-zarzad, nowy-zarzad, das). Slugi jak w kotwicach #wpis-… w HTML.
    // kategoria nie występuje w HTML — „Życie koła” / „Wydarzenie” to propozycja do zatwierdzenia.
    'aktualnosci' => [
        [
            'slug' => 'das',
            'data' => null,
            'kategoria' => 'Wydarzenie',
            'kolor' => 'pink',
            'tytul' => 'DAS 2026: tak prezentowaliśmy PMG',
            'zajawka' => '12 marca 2026 roku, jako Koło Naukowe Project Management Group, mieliśmy przyjemność wziąć udział w Dniu Aktywności Studenckiej – DAS, gdzie jak co semestr z dumą prezentowaliśmy naszą działalność.',
            'tresc' => 'Wydarzenie cieszyło się ogromnym zainteresowaniem, a wspaniała atmosfera sprzyjała wielu inspirującym rozmowom ze studentami. Nasze stoisko wyróżniało się niebieskimi i różowymi balonami, materiałami promocyjnymi z kodami QR i uśmiechniętą ekipą, która skutecznie przyciągała uwagę odwiedzających.

Chcąc zaprezentować nasze kreatywne podejście, przygotowaliśmy dla uczestników serię angażujących wyzwań marketingowych. Odwiedzający mogli spróbować swoich sił w „Bazarze Absurdu”, gdzie mieli zaledwie 30 sekund na opracowanie strategii sprzedaży dla tak nietypowych przedmiotów, jak na przykład dziurawa skarpetka. Kolejnym zadaniem był „Brainstorm 2.0”, polegający na błyskawicznym rozwiązywaniu abstrakcyjnych problemów, takich jak zaplanowanie promocji wydarzenia przy zerowym budżecie. Z kolei na osoby lubiące działać pod presją czasu czekała „Puszka Pandory”, w której za uratowanie wizerunku marki w obliczu wylosowanego kryzysu w jedyne 10 sekund nagradzaliśmy śmiałków ciastkami i cukierkami.

Serdecznie dziękujemy wszystkim za obecność, zaangażowanie i każdą owocną rozmowę! A w tym roku zespół PMG wymyśli coś jeszcze ciekawszego!',
            'zdjecie' => 'img/aktualnosci-das-2026-1600.jpg',
            'zdjecie_alt' => 'Stoisko PMG na Dniu Aktywności Studenckiej 2026: roll-upy koła, niebieskie i różowe balony oraz członkowie koła z materiałami promocyjnymi',
            'autor' => 'Sekcja Marketingu',
        ],
        [
            'slug' => 'nowy-zarzad',
            'data' => null,
            'kategoria' => 'Życie koła',
            'kolor' => 'purple',
            'tytul' => 'Poznaj nowy zarząd PMG, kadencja 2026/2027',
            'zajawka' => 'Z ogromną radością i nową energią do działania prezentujemy nasz nowy zarząd Koła Naukowego Project Management Group na kadencję 2026/2027!',
            'tresc' => 'Na najnowszym pamiątkowym zdjęciu, stoją od lewej, reprezentują nas: Martyna Strzecha (Koordynatorka sekcji HR), Agnieszka Jachimiak (Wiceprezes), Łucja Próchnicka (Koordynatorka sekcji Marketingu), Michał Golisz (Koordynator sekcji Partnerów i Kontaktów), Jakub Porada (Prezes koła) oraz Michał Zajdel (Koordynator sekcji Finansów i Logistyki). W nowym roku akademickim oferują gotowość na podjęcie nadchodzących wyzwań i zamierzają z ogromnym zaangażowaniem kontynuować nasze dotychczasowe, flagowe projekty.

Priorytetem nowego zarządu jest prężny rozwój koła oraz budowanie trwałych, wartościowych relacji – zarówno wewnątrz naszej rosnącej społeczności, jak i ze środowiskiem akademickim oraz partnerami biznesowymi. Celem jest nieustanne poszerzenie praktycznej wiedzy z zakresu zarządzania projektami, inspirowanie innych studentów do działania i udowadnianie, że wspólnymi siłami potrafimy zrealizować każdą wizję. Przed nami wyjątkowy rok pełen ambitnych celów i z pewnością dadzą z siebie wszystko, aby wynieść PMGroup na jeszcze wyższy poziom!',
            'zdjecie' => 'img/aktualnosci-nowy-zarzad-1600.jpg',
            'zdjecie_alt' => 'Zarząd PMG kadencji 2026/2027, od lewej: Martyna Strzecha, Agnieszka Jachimiak, Łucja Próchnicka, Michał Golisz, Jakub Porada i Michał Zajdel',
            'autor' => 'Sekcja Marketingu',
        ],
        [
            'slug' => 'stary-zarzad',
            'data' => null,
            'kategoria' => 'Życie koła',
            'kolor' => 'violet',
            'tytul' => 'Dziękujemy zarządowi kadencji 2025/2026',
            'zajawka' => 'Pragniemy z dumą przypomnieć i jednocześnie złożyć serdeczne podziękowania zarządowi naszego koła z kadencji 2025/2026, który z ogromnym zaangażowaniem oraz profesjonalizmem prowadził naszą organizację do kolejnych sukcesów.',
            'tresc' => 'Na pamiątkowej fotografii stoją od lewej: Jakub Bęben (Koordynator sekcji HR), Marta Zakierska (Koordynatorka sekcji Finansów i Logistyki), Marcel Wawryło (Koordynator sekcji Partnerzy i Kontakty), Nikol Khmura (Koordynatorka sekcji Marketingu), Łukasz Kowalski (Prezes koła) oraz Magdalena Skoczek (Wiceprezes). To właśnie za czasów ich niezwykle owocnej pracy poszerzyliśmy nasze horyzonty, powołując do życia zupełnie nową inicjatywę – regularnie prowadzony projekt Podcast. Ponadto, z dbałością o najwyższe standardy, kontynuowane były nasze dotychczasowe, kluczowe projekty, w tym Mój Idealny Pracodawca (MIP) realizowany w ścisłej współpracy z Biurem Karier Politechniki Wrocławskiej, edukacyjny Case Koła oraz prestiżowa, XIV edycja konferencji PM Session.

Był to okres pełen dynamicznych działań, wyzwań i niesamowitej energii, z którymi ówczesny zarząd radził sobie wzorowo, nieustannie dbając o rozwój członków oraz pozytywny wizerunek koła. W imieniu całej społeczności Koła Naukowego Project Management Group chcemy wyrazić ogromną wdzięczność za Wasz trud, poświęcony czas, wsparcie i inspirujące przywództwo. Dziękujemy za wspaniałą kadencję i życzymy Wam dalszych, równie imponujących sukcesów na nowych ścieżkach kariery!',
            'zdjecie' => 'img/aktualnosci-stary-zarzad-1600.jpg',
            'zdjecie_alt' => 'Zarząd PMG kadencji 2025/2026, od lewej: Jakub Bęben, Marta Zakierska, Marcel Wawryło, Nikol Khmura, Łukasz Kowalski i Magdalena Skoczek',
            'autor' => 'Sekcja Marketingu',
        ],
    ],
    // Członkowie: sekcje (kolejność jak na stronie) i osoby (sekcja = nazwa sekcji albo null dla zarządu).
    'sekcje' => [
        ['nazwa' => 'Finanse i Logistyka', 'kolor' => 'pink', 'opis' => 'Zajmujemy się finansami koła, bilansami i ofertami, a także organizacją wydarzeń, przygotowaniem materiałów i obsługą komunikatorów. Dbamy, by projekty były dopięte na ostatni guzik.', 'kolejnosc' => 1],
        ['nazwa' => 'Partnerzy i Kontakty', 'kolor' => 'purple', 'opis' => 'My reprezentujemy koło w terenie. Jako pierwsi nawiązujemy kontakt z prelegentami zewnętrznymi i pozyskujemy partnerów do naszych wydarzeń. To z nami poznasz najciekawszych ludzi „świata PM-u”!', 'kolejnosc' => 2],
        ['nazwa' => 'Marketing', 'kolor' => 'blue', 'opis' => 'Jesteśmy odpowiedzialni za promowanie koła. To właśnie my tworzymy koncepcje marketingowe oraz odpowiadamy za nasze profile społecznościowe na Facebooku i LinkedInie. Tworzymy też grafiki do wydarzeń i plakatów. Chcesz dołożyć do tego swój wkład?', 'kolejnosc' => 3],
        ['nazwa' => 'HR', 'kolor' => 'violet', 'opis' => 'Jeśli lubisz wyzwania związane z organizacją rekrutacji lub wiążesz swoją przyszłość z zarządzaniem zasobami ludzkimi – to miejsce właśnie dla Ciebie! Dbamy o to, żeby atmosfera w ciągu roku sprzyjała miłej i przyjemnej pracy. Integracje i wyjazdy – to właśnie nasze zadanie.', 'kolejnosc' => 4],
    ],
    'osoby' => [
        ['imie' => 'Jakub', 'nazwisko' => 'Porada', 'funkcja' => 'Prezes', 'sekcja' => null, 'koordynator' => 0, 'email' => 'jakub.porada.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/jakub-porada', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 1],
        ['imie' => 'Agnieszka', 'nazwisko' => 'Jachimiak', 'funkcja' => 'Wiceprezes', 'sekcja' => null, 'koordynator' => 0, 'email' => 'agnieszka.jachimiak.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/agnieszka-jachimiak', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 2],
        ['imie' => 'Michał', 'nazwisko' => 'Zajdel', 'funkcja' => 'Koordynator', 'sekcja' => 'Finanse i Logistyka', 'koordynator' => 1, 'email' => 'michal.zajdel.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/zajdel-michal', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 1],
        ['imie' => 'Magdalena', 'nazwisko' => 'Kotas', 'funkcja' => '', 'sekcja' => 'Finanse i Logistyka', 'koordynator' => 0, 'email' => 'magdalena.kotas.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/magdalena-kotas', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 2],
        ['imie' => 'Wiktoria', 'nazwisko' => 'Pocztańska', 'funkcja' => '', 'sekcja' => 'Finanse i Logistyka', 'koordynator' => 0, 'email' => 'wiktoria.pocztanska.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/wiktoria-poczta%C5%84ska-8b7b8930a', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 3],
        ['imie' => 'Michał', 'nazwisko' => 'Golisz', 'funkcja' => 'Koordynator', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 1, 'email' => 'michal.golisz.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/micha%C5%82-golisz-19661b394', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 1],
        ['imie' => 'Szymon', 'nazwisko' => 'Adamczyk', 'funkcja' => '', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 0, 'email' => 'szymon.adamczyk.pmg@gmail.com', 'linkedin' => '', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 2],
        ['imie' => 'Kacper', 'nazwisko' => 'Kotas', 'funkcja' => '', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 0, 'email' => 'kacper.kotas.pmg@gmail.com', 'linkedin' => '', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 3],
        ['imie' => 'Magdalena', 'nazwisko' => 'Maćkowiak', 'funkcja' => '', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 0, 'email' => 'magdalena.mackowiak.pmg@gmail.com', 'linkedin' => '', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 4],
        ['imie' => 'Michał', 'nazwisko' => 'Miszczuk', 'funkcja' => '', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 0, 'email' => 'michal.miszczuk.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/micha%C5%82-miszczuk-ab1b4a406/', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 5],
        ['imie' => 'Maksymilian', 'nazwisko' => 'Pordzik', 'funkcja' => '', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 0, 'email' => 'maksymilian.pordzik.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/maksymilianpordzik', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 6],
        ['imie' => 'Marcel', 'nazwisko' => 'Wawryło', 'funkcja' => '', 'sekcja' => 'Partnerzy i Kontakty', 'koordynator' => 0, 'email' => 'marcel.wawrylo.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/marcel-wawry%C5%82o', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 7],
        ['imie' => 'Łucja', 'nazwisko' => 'Próchnicka', 'funkcja' => 'Koordynator', 'sekcja' => 'Marketing', 'koordynator' => 1, 'email' => 'lucja.prochnicka.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/%C5%82ucja-pr%C3%B3chnicka-73173b35a', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 1],
        ['imie' => 'Maja', 'nazwisko' => 'Haładus', 'funkcja' => '', 'sekcja' => 'Marketing', 'koordynator' => 0, 'email' => 'maja.haladus.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/maja-ha%C5%82adus-7abb71411', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 2],
        ['imie' => 'Nikol', 'nazwisko' => 'Khmura', 'funkcja' => '', 'sekcja' => 'Marketing', 'koordynator' => 0, 'email' => 'nikol.khmura.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/nikolkhmura', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 3],
        ['imie' => 'Bartosz', 'nazwisko' => 'Kozłowski', 'funkcja' => '', 'sekcja' => 'Marketing', 'koordynator' => 0, 'email' => 'bartosz.kozlowski.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/bartosz-koz%C5%82owski22', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 4],
        ['imie' => 'Julia', 'nazwisko' => 'Twaróg', 'funkcja' => '', 'sekcja' => 'Marketing', 'koordynator' => 0, 'email' => 'julia.twarog.pmg@gmail.com', 'linkedin' => '', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 5],
        ['imie' => 'Martyna', 'nazwisko' => 'Strzecha', 'funkcja' => 'Koordynator', 'sekcja' => 'HR', 'koordynator' => 1, 'email' => 'martyna.strzecha.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/martyna-strzecha', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 1],
        ['imie' => 'Lena', 'nazwisko' => 'Bąkowska', 'funkcja' => '', 'sekcja' => 'HR', 'koordynator' => 0, 'email' => 'lena.bakowska.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/lena-b%C4%85kowska-223081378', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 2],
        ['imie' => 'Jakub', 'nazwisko' => 'Bęben', 'funkcja' => '', 'sekcja' => 'HR', 'koordynator' => 0, 'email' => 'jakub.beben.pmg@gmail.com', 'linkedin' => 'https://www.linkedin.com/in/jakub-b%C4%99ben', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 3],
        ['imie' => 'Julia', 'nazwisko' => 'Rybka', 'funkcja' => '', 'sekcja' => 'HR', 'koordynator' => 0, 'email' => 'julia.rybka.pmg@gmail.com', 'linkedin' => '', 'zdjecie' => null, 'zdjecie_alt' => '', 'kolejnosc' => 4],
    ],
    // PM Session: edycja XIV. Status „szkic” (nie publiczny): HTML zawiera pełne opisy prelekcji i zdjęcia 4:3,
    // których model panelu nie przechowuje — publikacja z panelu zastąpiłaby je skróconą wersją (patrz README).
    'edycje' => [
        [
            'numer' => 'XIV', 'temat' => 'Zarządzanie zmianą', 'data' => '2026-04-25',
            'miejsce' => 'Budynek B-4, Politechnika Wrocławska',
            'opis' => 'W dzisiejszych projektach jedyną stałą jest... zmiana. Chcieliśmy pokazać Wam, jak przez nią nawigować i jak z sukcesem przeprowadzać przez nią zespoły.',
            'status' => 'szkic',
            'prelegenci' => [
                [
                    'imie_nazwisko' => 'Paweł Sawicki',
                    'temat' => 'Prelekcja: „Project Manager – influencer zmiany”',
                    'bio' => 'Lider transformacji technologicznej i operacyjnej łączący doświadczenie inżynieryjne i menedżerskie z umiejętnością budowania pozytywnej narracji zmiany. Przewodził strategicznym inicjatywom w finansach i przemyśle: od platform chmurowych i integracji danych dla bankowości, przez globalne programy transformacji cyfrowej w sektorze FMCG, po projekty R&D i produkcyjne dla BMW i Scanii oraz programy infrastrukturalne dla agencji bezpieczeństwa UE. Specjalizuje się w łączeniu strategii z delivery, pracy ze złożonymi grupami interesariuszy oraz przywództwie, które realnie uruchamia zmianę. Wykładowca Coventry University Wrocław, mentor i doradca liderów, ekspert NCBR. Laureat wyróżnienia „Business Tiger 2023” oraz nagrody Prezydenta Wrocławia za wkład w innowacje.

Związany z BPX S.A., globalną firmą konsultingową specjalizującą się w cyfrowej transformacji przedsiębiorstw oraz kompleksowym wsparciu IT.

Executive Transformation & Delivery Leader | BPX S.A.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-05-pawel-sawicki-560.jpg',
                    'zdjecie_alt' => 'Portret: Paweł Sawicki',
                    'linkedin' => 'https://www.linkedin.com/in/pawel-sawicki-6169116a/',
                    'kolejnosc' => 1,
                ],
                [
                    'imie_nazwisko' => 'Marcin Orocz',
                    'temat' => 'Warsztat: „Zapomnij o wszystkim, czego się nauczyłeś na studiach. Oduczanie jako Twoja supermoc w zarządzaniu projektami”',
                    'bio' => 'Doświadczony doradca biznesowy i konsultant ds. optymalizacji procesów, który przez ponad 15 lat pracował w różnych obszarach zarządzania, włączając w to zarządzanie IT, sprzedażą oraz omnichannel. Jego bogate doświadczenie obejmuje pełnienie funkcji w globalnych organizacjach oraz udział w cyfrowej transformacji firm. Marcin jest ekspertem w budowaniu skutecznych zespołów, kierowaniu zmianami i zarządzaniu wartością dla klienta. Jako mówca motywacyjny i konferansjer dzieli się swoją wiedzą i inspiruje innych do osiągania sukcesu.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-03-marcin-orocz-560.jpg',
                    'zdjecie_alt' => 'Portret: Marcin Orocz',
                    'linkedin' => 'https://www.linkedin.com/in/marcinorocz/',
                    'kolejnosc' => 2,
                ],
                [
                    'imie_nazwisko' => 'Bartosz Misiurek',
                    'temat' => 'Warsztat: „Od strategii do codziennych działań: czego podejście Toyoty uczy o zarządzaniu zmianą”',
                    'bio' => 'Od 2007 roku aktywnie rozwija program Training Within Industry (TWI) w Polsce, będąc jednym z jego głównych propagatorów i praktyków. Autor ponad 50 publikacji na temat TWI i Lean Management, wydanych w Polsce, Niemczech, Brazylii i Japonii, a także książki poświęconej integracji Lean i TWI w środowisku produkcyjnym wydanej w USA.

Od lat występuje jako prelegent na krajowych i międzynarodowych konferencjach, dzieląc się wiedzą zdobytą podczas ponad 300 warsztatów praktycznych, w których uczestniczyło kilka tysięcy osób. Pracował m.in. z takimi firmami jak Volvo, Electrolux, Philips, IKEA, Wedel, Heinz, Philip Morris, Tarczyński, Autoliv, Avio, BRW, BSH i Cooper Standard.

Pełnił funkcję TWI Global Coach w Cooper Standard Automotive, wspierając wdrożenia programu TWI w ponad 60 zakładach produkcyjnych na czterech kontynentach.

Doktor nauk technicznych – tytuł uzyskał na Politechnice Wrocławskiej, gdzie badał metody rozwoju kompetencji pracowników w środowisku przemysłowym.

Od listopada 2024 wykłada na Politechnice Wrocławskiej na Wydziale Zarządzania, gdzie pracuje jako adiunkt.

Rozwija temat Lean Blockchain, zajmując się połączeniem technologii blockchain, AI wraz ze szczupłym zarządzaniem.

Współtwórca największej w Polsce konferencji Lean oraz AI.

Prywatnie – tata trzech synów, pasjonat tenisa, biegania i sportów wytrzymałościowych. Ukończył wiele maratonów i półmaratonów w Polsce i za granicą.',
                    'notatka' => 'Wspólny warsztat z Wiktorem Wołoszczukiem',
                    'zdjecie' => 'img/portret-09-bartosz-misiurek-560.jpg',
                    'zdjecie_alt' => 'Portret: Bartosz Misiurek',
                    'linkedin' => 'https://www.linkedin.com/in/bartosz-misiurek/',
                    'kolejnosc' => 3,
                ],
                [
                    'imie_nazwisko' => 'Wiktor Wołoszczuk',
                    'temat' => 'Warsztat: „Od strategii do codziennych działań: czego podejście Toyoty uczy o zarządzaniu zmianą”',
                    'bio' => 'Wiktor Wołoszczuk jest psychologiem z wykształcenia oraz doświadczonym konsultantem Lean Management, trenerem, coachem i mentorem kadry kierowniczej. Ukończył studia magisterskie na Wydziale Prawa i Administracji Uniwersytetu Wrocławskiego oraz psychologię na Uniwersytecie SWPS we Wrocławiu.

Posiada ponad 20 lat doświadczenia menedżerskiego na stanowisku dyrektora regionalnego w instytucjach finansowych, z udziałem w dużych projektach HR i zarządzania realizowanych wspólnie z BCG.

Specjalizuje się w rozwoju ludzi, liderów, zespołów i organizacji funkcjonujących w środowiskach VUCA. Ekspert psychologii relacji, zarządzania zmianą, budowania zaangażowania i kultury opartej na wartościach turkusowych.

Jako asesor AC i DC prowadzi szkolenia i oceny kompetencji, a także wykłada w Wyższej Szkole Bankowej we Wrocławiu – m.in. na temat relacji pracowniczych i przywództwa. Jest praktykiem Lean Management i programów TWI.

W LeanTrix zajmuje się wdrażaniem metodologii Toyota Kata i TWI, współrealizując programową linię wsparcia dla liderów i zmian operacyjnych.

Wiktor jest również współzałożycielem Kata School Poland – pierwszej oficjalnie działającej szkoły Kata w Polsce, tworząc forum praktyków doskonalenia codziennego i coachingu Kata oraz integrując lokalną społeczność Kata.

Dzięki wieloletniemu doświadczeniu operacyjnemu, psychologicznemu i trenerskiemu, łączy podejście analityczne z coachingiem ludzkiego potencjału – umożliwia liderom lepsze zrozumienie podejścia systemowego do rozwoju organizacji i budowania kultury ciągłego doskonalenia.',
                    'notatka' => 'Wspólny warsztat z Bartoszem Misiurkiem',
                    'zdjecie' => 'img/portret-10-wiktor-woloszczuk-560.jpg',
                    'zdjecie_alt' => 'Portret: Wiktor Wołoszczuk',
                    'linkedin' => 'https://www.linkedin.com/in/wiktorwoloszczuk/',
                    'kolejnosc' => 4,
                ],
                [
                    'imie_nazwisko' => 'Paweł Sukiennik',
                    'temat' => 'Prelekcja: „Dyrektywa NIS2 jako projekt zmiany w czasie transformacji energetycznej”',
                    'bio' => 'Dyrektor ds. Cyberbezpieczeństwa OT w Transition Technologies-Control Solutions odpowiedzialny za realizację projektów związanych z bezpieczeństwem systemów OT. Absolwent Wydziału Elektroniki na kierunku Automatyka i Robotyka na Politechnice Wrocławskiej. Członek stowarzyszenia w ISA oraz ISSA Polska. Certyfikowany specjalista w zakresie normy ISA/IEC 62443 dotyczącej bezpieczeństwa przemysłowych systemów automatyki i sterowania. Praktyk, posiadający 10-letni staż pracy jako integrator rozwiązań z zakresu automatyki przemysłowej oraz cyberbezpieczeństwa na wielu instalacjach infrastruktury krytycznej w Polsce i za granicą.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-07-pawel-sukiennik-560.jpg',
                    'zdjecie_alt' => 'Portret: Paweł Sukiennik',
                    'linkedin' => 'https://www.linkedin.com/in/pawe%C5%82-sukiennik-242386199/',
                    'kolejnosc' => 5,
                ],
                [
                    'imie_nazwisko' => 'Wojciech Buła',
                    'temat' => 'Prelekcja: „21,5 miliona złotych i zero przychodów – jak zarządzać zmianą, kiedy kończy się paliwo”',
                    'bio' => 'Wojciech Buła, PhD, EMBA – z wykształcenia inżynier elektronik (Politechnika Wrocławska), z doktoratu nanotechnolog (MESA+ Institute for Nanotechnology, Uniwersytet Twente, NL), z praktyki konstruktor urządzeń analitycznych i medycznych, a z powołania – człowiek, który buduje rzeczy, których jeszcze nie ma, i czuje się w chaosie jak w domu. Przez 20 lat projektował mikroreaktory w Holandii, analizatory zanieczyszczeń wody w Hiroszimie, diagnostykę biomarkerów na platformie lab-on-a-chip do zastosowań domowych – zanim stało się to modne – w Tokio i San Francisco, nawiązując po drodze współpracę z ASICS i SpaceX – bo dlaczego nie testować zdrowia zarówno na maratonie, jak i w kosmosie? Po drodze przeszedł przez największy accelerator dla hardware’owych startupów HAX/SOSV na kampusie w Shenzhen, zebrał Gold Edison Award, wygrał CES Innovation Award i zdobył MBA w SGH z pierwszą lokatą — bo skoro już zmieniać branżę, to z przytupem (i dyplomem). Współzałożyciel dwóch (i pół, z tendencją rosnącą) startupów deep-tech, z których każdy nauczył go czegoś nowego o zarządzaniu zmianą — głównie tego, że teoria rzadko przeżywa kontakt z rzeczywistością. Na co dzień CTO Orthoget S.A., gdzie współtworzy implanty ortopedyczne do korekty wzrostu, partner w software house wprowadzający AI w procesy biznesowe, a w wolnych chwilach wykładowca SGH i członek rady programowej MBA for Startups, gdzie dzieli się ze studentami wiedzą, której sam nie miał, kiedy jej najbardziej potrzebował. Przeszedł drogę od naukowca, startupera, konsultanta do nauczyciela, świadomy, że najlepsze decyzje biznesowe podejmuje się zwykle z niepełną informacją i zimną kawą w ręku. Widział, jak nie działa akademia, nauka, startupy i wielkie korporacje przywiązane są do tego, jak się robiło rzeczy w przeszłości. Startupową krwią i potem zapłacił za zrozumienie, że jedyną stałą rzeczą w nowoczesnym świecie jest ciągła zmiana.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-02-wojciech-bula-560.jpg',
                    'zdjecie_alt' => 'Portret: Wojciech Buła',
                    'linkedin' => 'https://www.linkedin.com/in/wpbula/',
                    'kolejnosc' => 6,
                ],
                [
                    'imie_nazwisko' => 'Yevhen Khimichuk',
                    'temat' => 'Warsztat: „Zarządzanie zmianą w formie projektu: wyznaczenie i sterowanie zakresem zmiany”',
                    'bio' => 'Yevhen Khimichuk (po polsku Eugeniusz Chimiczuk) jest kierownikiem działu finansów oraz przewodniczącym komisji rewizyjnej IPMA Young Crew, absolwentem studiów podyplomowych „Zarządzanie projektami” na Politechnice Warszawskiej oraz magistrem stosunków międzynarodowych na Uczelni Łazarskiego. Jest wieloletnim analitykiem ds. bezpieczeństwa i obronności Klubu Jagiellońskiego (od 2016 roku), ze specjalizacją przemysł i technologie obronne, oraz wieloletnim pracownikiem międzynarodowych korporacji.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-04-yevhen-khimichuk-560.jpg',
                    'zdjecie_alt' => 'Portret: Yevhen Khimichuk',
                    'linkedin' => 'https://www.linkedin.com/in/yevhen-khimichuk-272198b2/',
                    'kolejnosc' => 7,
                ],
                [
                    'imie_nazwisko' => 'Marek Malinowski',
                    'temat' => 'Warsztat: „Zmiana 2.0 – Rapid Change Management z pomocą AI”',
                    'bio' => 'Marek Malinowski – człowiek, który od blisko 20 lat działa w IT, a od kilku lat konsekwentnie próbuje przekonać świat, że technologia może być jednocześnie skuteczna i… ludzka. Na co dzień odpowiada za obszar innowacji, narzędzi i współpracy z vendorami w IT Operations & Support, gdzie łączy kropki między biznesem, technologią i zdrowym rozsądkiem (czasem w tej właśnie kolejności, czasem nie).

Specjalizuje się w usprawnianiu pracy zespołów, automatyzacji i wykorzystywaniu AI tam, gdzie naprawdę ma to sens – czyli nie „bo modne”, tylko „bo działa”. W swojej karierze prowadził inicjatywy, które realnie poprawiały efektywność zespołów, redukowały koszty i… ratowały sanity niejednego support engineera.

Po godzinach (a czasem i w godzinach) Marek zamienia się w warsztatowego ninja – prowadzi szkolenia z AI, Design Thinking, etyki technologii i szeroko pojętego „jak ogarnąć ten chaos wokół nas”. Na koncie ma setki przeszkolonych osób: od studentów, przez specjalistów, po liderów. Występował na konferencjach, prowadził warsztaty dla dzieci, młodzieży i dorosłych – czasem w jednej grupie (true story).

Lubi tłumaczyć skomplikowane rzeczy w prosty sposób, najlepiej z odrobiną humoru i przykładami, które zostają w głowie dłużej niż poniedziałkowe stand-upy. Wierzy, że AI nie zastąpi ludzi – ale może bardzo pomóc tym, którzy wiedzą, jak z niego korzystać.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-06-marek-malinowski-560.jpg',
                    'zdjecie_alt' => 'Portret: Marek Malinowski',
                    'linkedin' => 'https://www.linkedin.com/in/malinowski-marek/',
                    'kolejnosc' => 8,
                ],
                [
                    'imie_nazwisko' => 'Aleksandra Penza',
                    'temat' => 'Warsztat: „Kapitał psychologiczny: ukryty zasób Project Managera”',
                    'bio' => 'Psycholożka biznesu, trenerka, wykładowczyni akademicka oraz badaczka specjalizująca się w obszarze psychologii pracy i organizacji.

Posiada bogate, ponad 18-letnie doświadczenie w obszarze HR, rekrutacji i rozwoju pracowników, pracując m.in. na stanowiskach HR Business Partnerka oraz Kierowniczka Sekcji Rozwoju zasobów ludzkich w dużych organizacjach.

Obecnie łączy praktykę z nauką, prowadząc wykłady na Uniwersytecie SWPS we Wrocławiu, gdzie dzieli się swoją wiedzą i doświadczeniem. Kontynuuje także działalność jako konsultantka, realizując projekty rozwojowe dla firm i pracowników, a także prowadząc badania naukowe, których celem jest lepsze zrozumienie mechanizmów zaangażowania w pracę.

Jej podejście opiera się na integracji wiedzy akademickiej z realiami biznesu, co pozwala na tworzenie skutecznych i opartych na dowodach rozwiązań dla organizacji.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-08-aleksandra-penza-560.jpg',
                    'zdjecie_alt' => 'Portret: Aleksandra Penza',
                    'linkedin' => 'https://www.linkedin.com/in/aleksandrapenza/',
                    'kolejnosc' => 9,
                ],
                [
                    'imie_nazwisko' => 'Andrzej Klose',
                    'temat' => 'Prelekcja: „Kim jestem tak naprawdę, czyli dlaczego ta zmiana znowu mnie przerosła?”',
                    'bio' => 'Przedsiębiorca, trener i coach biznesu, speaker.

Dyrektor zarządzający w Klose Brothers Polska, wykładowca PASB.

25+ lat doświadczenia zawodowego w tradycyjnych i nowoczesnych przedsiębiorstwach.

Wspiera organizacje w głębokich, zwinnych transformacjach.

Pomaga małym i średnim firmom zrozumieć wyzwania współczesnego biznesu i rozwijać się niezależnie od zewnętrznej koniunktury.

Twórca programu „Wewnętrzna Siła”, będącego inspiracją dla ludzi stojących na różnego rodzaju zakrętach życia i kariery.',
                    'notatka' => '',
                    'zdjecie' => 'img/portret-01-andrzej-klose-560.jpg',
                    'zdjecie_alt' => 'Portret: Andrzej Klose',
                    'linkedin' => 'https://www.linkedin.com/in/andrzej-klose/',
                    'kolejnosc' => 10,
                ],
            ],
            // godzina, tytuł, prowadzący (jak w tabeli harmonogramu; „PMG” przy punktach organizacyjnych)
            'harmonogram' => [
                ['8:30', 'Rejestracja', 'PMG'],
                ['9:00', 'Oficjalne rozpoczęcie konferencji', 'PMG'],
                ['9:15', '„Project Manager – influencer zmiany”', 'Paweł Sawicki'],
                ['10:30', '„Zapomnij o wszystkim, czego się nauczyłeś na studiach. Oduczanie jako Twoja supermoc w zarządzaniu projektami”', 'Marcin Orocz'],
                ['10:30', '„Od strategii do codziennych działań: czego podejście Toyoty uczy o zarządzaniu zmianą”', 'Bartosz Misiurek + Wiktor Wołoszczuk'],
                ['10:30', '„Dyrektywa NIS2 jako projekt zmiany w czasie transformacji energetycznej”', 'Paweł Sukiennik'],
                ['12:15', '„21,5 miliona złotych i zero przychodów – jak zarządzać zmianą, kiedy kończy się paliwo”', 'Wojciech Buła'],
                ['13:15', 'Poczęstunek w Kawiarence', 'PMG'],
                ['13:45', 'Networking', 'PMG'],
                ['14:15', '„Zarządzanie zmianą w formie projektu: wyznaczenie i sterowanie zakresem zmiany”', 'Yevhen Khimichuk'],
                ['14:15', '„Zmiana 2.0 – Rapid Change Management z pomocą AI”', 'Marek Malinowski'],
                ['14:15', '„Kapitał psychologiczny: ukryty zasób Project Managera”', 'Aleksandra Penza'],
                ['16:00', '„Kim jestem tak naprawdę, czyli dlaczego ta zmiana znowu mnie przerosła?”', 'Andrzej Klose'],
                ['17:00', 'Oficjalne zakończenie konferencji', 'PMG'],
            ],
        ],
    ],
];
