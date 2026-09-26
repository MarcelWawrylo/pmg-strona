<?php
// Zarząd i sekcje koła (tylko aktywne osoby) jako JSON (czyta js/main.js).
// Sekcje bez żadnej aktywnej osoby (koordynatora ani członka) są pomijane.
require __DIR__ . '/lib.php';

function osoba_pub($o)
{
    return [
        'imie' => $o['imie'], 'nazwisko' => $o['nazwisko'], 'funkcja' => $o['funkcja'],
        'email' => $o['email'], 'linkedin' => $o['linkedin'], 'zdjecie' => $o['zdjecie'], 'zdjecie_alt' => $o['zdjecie_alt'],
    ];
}

try {
    $osoby = pmg_db()->query(
        'SELECT imie, nazwisko, funkcja, sekcja_id, koordynator, email, linkedin, zdjecie, zdjecie_alt
         FROM pmg_osoby WHERE aktywna = 1 ORDER BY kolejnosc, nazwisko'
    )->fetchAll();
    $sekcjeRows = pmg_db()->query('SELECT id, nazwa, kolor, opis FROM pmg_sekcje ORDER BY kolejnosc, id')->fetchAll();
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}

$zarzad = [];
$bySekcja = [];
foreach ($osoby as $o) {
    if ($o['sekcja_id'] === null) $zarzad[] = osoba_pub($o);
    else $bySekcja[$o['sekcja_id']][] = $o;
}

$sekcje = [];
foreach ($sekcjeRows as $s) {
    $wsekcji = $bySekcja[$s['id']] ?? [];
    $koordynatorzy = [];
    $czlonkowie = [];
    foreach ($wsekcji as $o) {
        if ($o['koordynator']) $koordynatorzy[] = osoba_pub($o);
        else $czlonkowie[] = osoba_pub($o);
    }
    if (!$koordynatorzy && !$czlonkowie) continue; // sekcja bez aktywnych osób — pomijamy na stronie
    $sekcje[] = ['nazwa' => $s['nazwa'], 'kolor' => $s['kolor'], 'opis' => $s['opis'], 'koordynatorzy' => $koordynatorzy, 'czlonkowie' => $czlonkowie];
}

header('Cache-Control: public, max-age=300');
pmg_json(['zarzad' => $zarzad, 'sekcje' => $sekcje]);
