<?php
// Teksty stron zmienione w panelu (moduł „Treści stron”) jako JSON (czyta js/main.js): {"tresci": {"klucz": "tekst", ...}}.
// Tylko odczyt, tylko klucze z listy pól (tresci-pola.php) i tylko niepuste wartości; brak klucza = strona zostaje z tekstem z HTML.
require __DIR__ . '/lib.php';

try {
    $rows = pmg_db()->query('SELECT klucz, wartosc FROM pmg_tresci')->fetchAll();
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
$dozwolone = [];
$lista = require __DIR__ . '/tresci-pola.php';
foreach ($lista['zakladki'] as $z) {
    foreach ($z['pola'] as $k => $p) $dozwolone[$k] = true;
}
$tresci = [];
foreach ($rows as $r) {
    if (isset($dozwolone[$r['klucz']]) && trim($r['wartosc']) !== '') $tresci[$r['klucz']] = $r['wartosc'];
}
header('Cache-Control: public, max-age=300');
pmg_json(['tresci' => $tresci ?: new stdClass()]); // pusta tablica koduje się jako [] — wymuś {} dla pustego wyniku
