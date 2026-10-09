<?php
// Odcinki podcastu jako JSON (czyta js/main.js). Tylko opublikowane, w kolejności z panelu.
// 'wszystkich' = liczba rekordów w tabeli (także szkiców): gdy > 0, lista ze strony pochodzi z bazy
// (nawet pusta), a gdy 0 — podcast.html zostaje w wersji statycznej.
require __DIR__ . '/lib.php';

try {
    $rows = pmg_db()->query(
        'SELECT numer, tytul, data, czas_min, opis, prowadzacy, gosc, gosc_bio, spotify_id, apple_url, youtube_url, zdjecie, zdjecie_alt
         FROM pmg_odcinki WHERE opublikowany = 1 ORDER BY kolejnosc, numer, id'
    )->fetchAll();
    $wszystkich = (int) pmg_db()->query('SELECT COUNT(*) FROM pmg_odcinki')->fetchColumn();
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
foreach ($rows as &$r) {
    $r['numer'] = (int) $r['numer'];
    $r['czas_min'] = $r['czas_min'] === null ? null : (int) $r['czas_min'];
}
unset($r);
header('Cache-Control: public, max-age=300');
pmg_json(['odcinki' => $rows, 'wszystkich' => $wszystkich]);
