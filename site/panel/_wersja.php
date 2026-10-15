<?php
// Wersja panelu i lista zmian („Co nowego” w panelu). Przy każdym wdrożeniu na serwer dopisz NOWY wpis NA GÓRZE listy:
// wersja (1.2, 1.3…; większa zmiana panelu = 2.0), data wdrożenia (RRRR-MM-DD) i zdania po polsku, bez żargonu, osobno dla strony i panelu.
// Numer wersji panelu = wersja pierwszego wpisu. Pusta lista 'strona' albo 'panel' = brak zmian w tej części.
defined('PMG_PANEL') || exit;

return [
    [
        'wersja' => '1.1',
        'data' => '2026-10-15',
        'strona' => [
            'Pozycja „Podcast” w menu, na stronie głównej i w tytule strony nazywa się teraz „Projekt Podcast”.',
            'Podmenu PM Session w nagłówku pokazuje edycje w kolejności ustawionej w panelu.',
            'Na stronie głównej przypięte wpisy Aktualności są pokazywane przed najnowszymi.',
        ],
        'panel' => [
            'PM Session: strzałki do zmiany kolejności edycji (ta sama kolejność w podmenu na stronie).',
            'Aktualności: pole „Przypnij na stronie głównej” — wpis zostaje na głównej, dopóki nie odznaczysz pola.',
            'Numer wersji panelu w menu i ta strona „Co nowego”.',
            'Moduł „Podcast” nazywa się teraz „Projekt Podcast”.',
            'Formularze (np. „Nowy wpis”) nie pokazują już wartości z poprzedniego zapisu po powrocie przyciskiem „Wstecz” przeglądarki.',
        ],
    ],
    [
        'wersja' => '1.0',
        'data' => '2026-10-10',
        'strona' => [
            'Nowa strona koła na pmgroup.pwr.edu.pl: Aktualności, PM Session, Projekt Podcast, Case Koła, O nas, Dołącz, Kontakt.',
            'Opisy dla wyszukiwarek i mediów społecznościowych oraz mapa strony (sitemap.xml) dla Google.',
        ],
        'panel' => [
            'Pierwsza wersja panelu: treści wszystkich stron, konta i uprawnienia, dziennik zmian, kopia zapasowa, wiadomości z formularza.',
        ],
    ],
];
