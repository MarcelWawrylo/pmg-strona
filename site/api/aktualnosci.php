<?php
// Opublikowane wpisy Aktualności jako JSON (czyta js/main.js). Najnowsze pierwsze.
require __DIR__ . '/lib.php';

try {
    $rows = pmg_db()->query(
        'SELECT slug, data, kategoria, kolor, tytul, lead, zajawka, tresc, zdjecie, zdjecie_alt, autor
         FROM pmg_aktualnosci WHERE opublikowany = 1 ORDER BY data DESC, id DESC LIMIT 50'
    )->fetchAll();
    // zdjęcia z img/ mają warianty szerokości i WebP (jak w HTML) — strona składa z nich <picture>; wgrane z panelu: obraz = null
    foreach ($rows as &$r) $r['obraz'] = pmg_obraz($r['zdjecie']);
    unset($r);
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
header('Cache-Control: public, max-age=300');
pmg_json(['wpisy' => $rows]);
