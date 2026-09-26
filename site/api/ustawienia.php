<?php
// Ustawienia strony (linki, kontakt, rekrutacja, liczby PMS) jako JSON (czyta js/main.js).
// Tylko klucze z białej listy USTAWIENIA i tylko niepuste wartości.
require __DIR__ . '/lib.php';

try {
    $rows = pmg_db()->query('SELECT klucz, wartosc FROM pmg_ustawienia')->fetchAll();
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
$dozwolone = array_flip(USTAWIENIA);
$dane = [];
foreach ($rows as $r) {
    if (isset($dozwolone[$r['klucz']]) && $r['wartosc'] !== '') $dane[$r['klucz']] = $r['wartosc'];
}
header('Cache-Control: public, max-age=300');
pmg_json($dane ?: new stdClass()); // pusta tablica koduje się jako [] — wymuś {} dla pustego wyniku
