<?php
// Odcinki podcastu jako JSON (czyta js/main.js). Tylko opublikowane, pogrupowane w edycje podcastu (widoczne,
// w kolejności z panelu). 'odcinki' = ta sama lista odcinków w jednej płaskiej tablicy (w kolejności wyświetlania).
// 'wszystkich' = liczba rekordów w tabeli (także szkiców): gdy > 0, lista ze strony pochodzi z bazy
// (nawet pusta), a gdy 0 — podcast.html zostaje w wersji statycznej.
require __DIR__ . '/lib.php';

try {
    $pdo = pmg_db();
    $rows = $pdo->query(
        'SELECT numer, tytul, data, czas_min, opis, prowadzacy, gosc, gosc_bio, spotify_id, apple_url, youtube_url, zdjecie, zdjecie_alt, edycja_id
         FROM pmg_odcinki WHERE opublikowany = 1 ORDER BY kolejnosc, numer, id'
    )->fetchAll();
    $edycje = $pdo->query(
        'SELECT id, numer, lata, koordynator, mentorzy, zespol, opis FROM pmg_podcast_edycje WHERE widoczna = 1 ORDER BY kolejnosc, id'
    )->fetchAll();
    $wszystkich = (int) $pdo->query('SELECT COUNT(*) FROM pmg_odcinki')->fetchColumn();
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
foreach ($rows as &$r) {
    $r['numer'] = (int) $r['numer'];
    $r['czas_min'] = $r['czas_min'] === null ? null : (int) $r['czas_min'];
}
unset($r);
list($grupy, $odcinki) = pmg_podcast_grupuj($edycje, $rows);
header('Cache-Control: public, max-age=300');
pmg_json(['edycje' => $grupy, 'odcinki' => $odcinki, 'wszystkich' => $wszystkich]);
