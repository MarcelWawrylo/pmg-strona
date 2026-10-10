<?php
// Opublikowane wpisy Aktualności jako JSON (czyta js/main.js). Najnowsze pierwsze.
require __DIR__ . '/lib.php';

try {
    $rows = pmg_db()->query(
        'SELECT slug, data, kategoria, kolor, tytul, lead, zajawka, tresc, zdjecie, zdjecie_alt, autor
         FROM pmg_aktualnosci WHERE opublikowany = 1 ORDER BY data DESC, id DESC LIMIT 50'
    )->fetchAll();
    // galerie wszystkich opublikowanych wpisów jednym zapytaniem; brak tabeli (schemat 8 jeszcze nie wdrożony z panelu) = wpisy bez galerii
    $galerie = [];
    try {
        $st = pmg_db()->query(
            'SELECT a.slug, g.zdjecie, g.podpis FROM pmg_aktualnosci_galeria g JOIN pmg_aktualnosci a ON a.id = g.wpis_id
             WHERE a.opublikowany = 1 ORDER BY g.kolejnosc, g.id'
        );
        foreach ($st as $g) $galerie[$g['slug']][] = ['zdjecie' => $g['zdjecie'], 'obraz' => pmg_obraz($g['zdjecie']), 'podpis' => $g['podpis']];
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e; // tylko brak tabeli; inny błąd bazy → {"error":"db"} niżej
        $galerie = [];
    }
    // zdjęcia z img/ mają warianty szerokości i WebP (jak w HTML) — strona składa z nich <picture>; wgrane z panelu: obraz = null
    foreach ($rows as &$r) {
        $r['obraz'] = pmg_obraz($r['zdjecie']);
        $r['galeria'] = $galerie[$r['slug']] ?? [];
    }
    unset($r);
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}
header('Cache-Control: public, max-age=300');
pmg_json(['wpisy' => $rows]);
