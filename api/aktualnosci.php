<?php
// Opublikowane wpisy Aktualności jako JSON (czyta js/main.js). Najnowsze pierwsze.
require __DIR__ . '/lib.php';

try {
    $rows = pmg_db()->query(
        'SELECT slug, data, kategoria, kolor, tytul, zajawka, tresc, zdjecie, zdjecie_alt, autor
         FROM pmg_aktualnosci WHERE opublikowany = 1 ORDER BY data DESC, id DESC LIMIT 50'
    )->fetchAll();
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
header('Cache-Control: public, max-age=300');
pmg_json(['wpisy' => $rows]);
