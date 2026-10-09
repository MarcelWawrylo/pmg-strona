<?php
// Edycja PM Session (edycja + prelegenci + harmonogram) jako JSON (czyta js/main.js).
// Bez parametru: bieżąca edycja (status='biezaca', dokładnie jedna albo brak).
// ?numer=XIV: edycja o tym numerze (cyfry rzymskie) — tylko bieżąca albo zakończona; szkic nigdy nie jest publiczny.
// Każda strona edycji (pm-session-xiv.html, pm-session-xv.html) prosi o własny numer, więc nie dostaje danych innej edycji.
require __DIR__ . '/lib.php';

function pms_prelegent_pub($p)
{
    return [
        'imie_nazwisko' => $p['imie_nazwisko'], 'temat' => $p['temat'], 'bio' => $p['bio'],
        'notatka' => $p['notatka'], 'zdjecie' => $p['zdjecie'], 'zdjecie_alt' => $p['zdjecie_alt'], 'linkedin' => $p['linkedin'],
    ];
}

$numer = isset($_GET['numer']) ? strtoupper(trim((string) $_GET['numer'])) : '';
if ($numer !== '' && !preg_match('~^[IVXLC]{1,10}$~', $numer)) {
    pmg_json(['error' => 'numer'], 400);
}

try {
    if ($numer === '') {
        $edycjaRow = pmg_db()->query("SELECT id, numer, temat, data, miejsce, opis FROM pmg_edycje WHERE status = 'biezaca' LIMIT 1")->fetch();
    } else {
        $st0 = pmg_db()->prepare("SELECT id, numer, temat, data, miejsce, opis FROM pmg_edycje WHERE numer = ? AND status IN ('biezaca','zakonczona') LIMIT 1");
        $st0->execute([$numer]);
        $edycjaRow = $st0->fetch();
    }
    $prelegenci = [];
    $harmonogram = [];
    if ($edycjaRow) {
        $st = pmg_db()->prepare('SELECT imie_nazwisko, temat, bio, notatka, zdjecie, zdjecie_alt, linkedin FROM pmg_prelegenci WHERE edycja_id = ? ORDER BY kolejnosc, id');
        $st->execute([$edycjaRow['id']]);
        foreach ($st->fetchAll() as $p) $prelegenci[] = pms_prelegent_pub($p);

        // Alias TIME_FORMAT(...) AS godzina zacieniałby kolumnę godzina w ORDER BY (MySQL/MariaDB sortowałyby
        // wtedy po sformatowanym tekście: "10:00" < "8:30" leksykalnie) — dlatego osobny alias godzina_fmt.
        $st2 = pmg_db()->prepare("SELECT TIME_FORMAT(godzina, '%k:%i') AS godzina_fmt, tytul, prelegent FROM pmg_harmonogram WHERE edycja_id = ? ORDER BY godzina, id");
        $st2->execute([$edycjaRow['id']]);
        foreach ($st2->fetchAll() as $h) $harmonogram[] = ['godzina' => $h['godzina_fmt'], 'tytul' => $h['tytul'], 'prelegent' => $h['prelegent']];
    }
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}

header('Cache-Control: public, max-age=300');
pmg_json([
    'edycja' => $edycjaRow ? ['numer' => $edycjaRow['numer'], 'temat' => $edycjaRow['temat'], 'data' => $edycjaRow['data'], 'miejsce' => $edycjaRow['miejsce'], 'opis' => $edycjaRow['opis']] : null,
    'prelegenci' => $prelegenci,
    'harmonogram' => $harmonogram,
]);
