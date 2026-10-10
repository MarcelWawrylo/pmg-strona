<?php
// Edycje Case Koła jako JSON (czyta js/main.js). Tylko widoczne, w kolejności z panelu.
// Bez parametru: karty do huba (case-kola.html) + 'wszystkich' = liczba rekordów w tabeli (także ukrytych): gdy > 0, karty
// ze strony pochodzą z bazy (nawet pusta lista), a gdy 0 — hub zostaje w wersji statycznej.
// ?nr=N: pełna edycja (treść podstrony case-kola-edycja.html); nieznana albo ukryta → 404.
require __DIR__ . '/lib.php';

$nr = null;
if (isset($_GET['nr'])) {
    if (!preg_match('~^[0-9]{1,3}$~', (string) $_GET['nr'])) pmg_json(['error' => 'nr'], 400);
    $nr = (int) $_GET['nr'];
}

try {
    $pdo = pmg_db();
    if ($nr !== null) {
        $st = $pdo->prepare('SELECT * FROM pmg_case_edycje WHERE numer = ? AND widoczna = 1');
        $st->execute([$nr]);
        $e = $st->fetch();
        if (!$e) pmg_json(['error' => 'brak'], 404);
        $st = $pdo->prepare('SELECT zdjecie, pelne, podpis FROM pmg_case_galeria WHERE edycja_id = ? ORDER BY kolejnosc, id');
        $st->execute([$e['id']]);
        $odp = ['edycja' => pmg_case_pelna($e, $st->fetchAll())];
    } else {
        $rows = $pdo->query('SELECT * FROM pmg_case_edycje WHERE widoczna = 1 ORDER BY kolejnosc, id')->fetchAll();
        $karty = [];
        foreach ($rows as $e) $karty[] = pmg_case_karta($e);
        $odp = ['edycje' => $karty, 'wszystkich' => (int) $pdo->query('SELECT COUNT(*) FROM pmg_case_edycje')->fetchColumn()];
    }
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
header('Cache-Control: public, max-age=300');
pmg_json($odp);
