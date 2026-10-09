<?php
// Dane startowe modułu Podcast: 4 odcinki Edycji 1 (2025/2026), przeniesione z dawnego podcast.html.
// Wczytywane tylko przez pmg_migrate() (lib.php) przy pierwszym tworzeniu tabeli pmg_odcinki.
// Kolejność pól: numer, tytuł, data, minuty, opis, prowadzący, gość, o gościu, spotify_id, apple_url, zdjęcie, alt zdjęcia, kolejność.
return [
    [1, 'Robert Kamiński: zmiana zarządzania na przestrzeni lat', '2026-02-12', 41,
     'Rozmowa o tym, jak zarządzanie zmieniło się na przestrzeni ostatnich trzech dekad — od sztywnych, podręcznikowych reguł z początku lat 90., gdy Robert Kamiński zaczynał studia na Wydziale Zarządzania, po dzisiejsze podejście oparte na realnych wyzwaniach organizacji i praktyce konsultingowej. Gość dzieli się doświadczeniem z wielu lat pracy jako niezależny konsultant przy projektach zarządzania strategicznego i zmian organizacyjnych dla dużych firm przemysłowych i usługowych. W rozmowie pojawia się też wątek sztucznej inteligencji — teza, że to człowiek musi nadawać technologii sens i wartości — a także „garażowe podejście” do budowania kariery i pomysł na granie we własną grę zamiast kopiowania cudzych ścieżek.',
     'Michał Miszczuk, Szymon Adamczyk', 'dr hab. inż. Robert Kamiński',
     'Wykładowca Wydziału Zarządzania PWr, konsultant zarządzania strategicznego i zmian organizacyjnych (m.in. dla KGHM), autor kilku książek o zarządzaniu i kulturze organizacyjnej, wykładowca programu Executive MBA, profesor wizytujący na Uniwersytecie Technicznym w Dreźnie, były prodziekan Wydziału Zarządzania PWr.',
     '6T35IL3gRjOmpxLmnIvTQa', 'https://podcasts.apple.com/ch/podcast/projekt-podcast-1-robert-kami%C5%84ski-zmiana-zarz%C4%85dzania/id1474385852?i=1000748705513',
     'img/odcinek-1-800.jpg', 'Zdjęcie z nagrania odcinka #1', 1],
    [2, 'Bartosz Stec: dlaczego doktorant zarządzania mówi „nie” korporacjom?', '2026-03-24', 37,
     'Rozmowa o doświadczeniach Bartosza Steca, który zamiast klasycznej kariery korporacyjnej wybrał ścieżkę akademicką na Wydziale Zarządzania PWr, gdzie zajmuje się m.in. tradycyjnym i zwinnym zarządzaniem projektami. Poruszamy temat jego filozofii kierowania zespołem, tego, co konkretnie odrzuca go od modelu pracy w wielkich organizacjach, oraz co motywuje go do działania poza utartymi schematami — łącznie z zaangażowaniem w rozwój studenckich organizacji i inicjatyw poza samą dydaktyką.',
     'Michał Miszczuk, Szymon Adamczyk', 'Bartosz Stec',
     'Związany z Katedrą Zarządzania Projektami, Jakością i Logistyką na Wydziale Zarządzania PWr, zajmuje się tradycyjnym i zwinnym zarządzaniem projektami oraz rozwojem organizacji studenckich.',
     '33e905HBCvSbiu3wboIA0U', 'https://podcasts.apple.com/pl/podcast/project-podcast-2-bartosz-stec-dlaczego-doktorant-zarz%C4%85dzania/id1474385852?i=1000757065519',
     'img/odcinek-2-800.jpg', 'Zdjęcie z nagrania odcinka #2', 2],
    [3, 'Marcel Sobecki: od organizacji studenckich do przewodniczącego SURE!', '2026-06-09', 36,
     'Marcel Sobecki zaczynał od studiów inżynierii biomedycznej — dopiero udział w konferencji PM Session przekonał go, żeby zrobić magisterkę z zarządzania na Politechnice Wrocławskiej. Dziś jako przewodniczący SURE! w europejskim sojuszu uczelni technicznych Unite! odpowiada za koordynację międzynarodowych zespołów studenckich. Rozmawiamy o łączeniu nauk technicznych z kompetencjami miękkimi, wyzwaniach zarządzania zespołem rozproszonym po kilku krajach oraz o tym, co realnie daje studentom zaangażowanie w organizacje akademickie.',
     'Michał Miszczuk, Marcel Wawryło', 'Marcel Sobecki',
     'Z wykształcenia inżynier biomedyczny, po udziale w PM Session zdecydował się na studia magisterskie z zarządzania na PWr. Obecnie przewodniczący SURE! w europejskim sojuszu uczelni technicznych Unite!, odpowiada za współpracę międzynarodowych zespołów studenckich.',
     '5RWo3SLd0dhH5Qnak8deex', 'https://podcasts.apple.com/pl/podcast/projekt-podcast-3-marcel-sobecki-od-organizacji-studenckich/id1474385852?i=1000771859482',
     'img/odcinek-3-800.jpg', 'Zdjęcie z nagrania odcinka #3', 3],
    [4, 'Karol Żuradzki: jak wspiąć się na szczyt firmy bez papierów', '2026-07-26', 44,
     'Karol Żuradzki ma za sobą ponad dekadę w zarządzaniu dużymi markami gastronomicznymi i kawiarnianymi — od sieci typu KFC i Pizza Hut, przez rolę dyrektora regionalnego Starbucks w kilku krajach (Polska, Rumunia, Bułgaria), po obecne zaangażowanie w grupę Etno Cafe i platformę Pyszne.pl. Rozmawiamy o różnicach w zarządzaniu gastronomią w różnych krajach, codziennych problemach zarządczych w branży restauracyjnej i o tym, jak — bez formalnych kwalifikacji na starcie — dojść do stanowisk dyrektorskich w wielkich firmach gastronomicznych, oraz jakie cechy organizacji faktycznie sprzyjają rozwojowi pracownika.',
     'Szymon Adamczyk, Marcel Wawryło', 'Karol Żuradzki',
     'Manager z ponad 12-letnim doświadczeniem w branży gastronomicznej i kawiarnianej (m.in. KFC, Pizza Hut, Starbucks, Etno Cafe), pracował jako dyrektor regionalny/generalny w kilku krajach (Polska, Rumunia, Bułgaria). Obecnie związany z grupą Etno Cafe oraz platformą Pyszne.pl, prowadzi też własną działalność konsultingową w gastronomii.',
     '75Ak2UVnfOqnopGTDIYJzA', 'https://podcasts.apple.com/pl/podcast/projekt-podcast-4-karol-%C5%BCuradzki-jak-wspi%C4%85%C4%87-si%C4%99-na/id1474385852?i=1000777699816',
     'img/odcinek-4-800.jpg', 'Zdjęcie z nagrania odcinka #4', 4],
];
