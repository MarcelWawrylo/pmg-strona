<?php
// Edycja PM Session (edycja + prelegenci + harmonogram) jako JSON (czyta js/main.js).
// Bez parametru: aktualna edycja (status 'biezaca' albo 'zapowiedz' — panel pilnuje, by była najwyżej jedna).
// ?numer=XIV: edycja o tym numerze (cyfry rzymskie) — tylko bieżąca, zapowiedź albo zakończona; szkic nigdy nie jest publiczny.
// Pole edycja.status mówi, którą wersję strony pokazać. Zapowiedź („Aktualna edycja – wkrótce więcej”) ma tylko numer i status:
// bez tematu, daty, miejsca, opisu, prelegentów i harmonogramu (strona pokazuje nagłówek i komunikat „wkrótce”).
// Każda strona edycji (pm-session-xiv.html, pm-session-xv.html) prosi o własny numer, więc nie dostaje danych innej edycji.
require __DIR__ . '/lib.php';

// Zdjęcie prelegenta: okno prelegenta używa kadru 4:3 (modal), kafelek kwadratu 1:1 (karta). Dla plików z img/ nazwanych
// <baza>-43-<szerokość>.jpg kwadratowy kadr tej samej fotografii to <baza>-560.jpg (z wariantami), jeśli istnieje; dla zdjęć
// wgranych z panelu (uploads/) oba obrazy są null, a strona używa zwykłego <img> z pliku `zdjecie` (CSS przycina do kadru).
function pms_prelegent_pub($p)
{
    $modal = pmg_obraz($p['zdjecie']);
    $karta = null;
    if ($modal && preg_match('~^(img/[a-z0-9-]+)-43-\d+\.(jpg|png)$~', $p['zdjecie'], $m)) $karta = pmg_obraz($m[1] . '-560.' . $m[2]);
    return [
        'imie_nazwisko' => $p['imie_nazwisko'], 'temat' => $p['temat'], 'bio' => $p['bio'], 'opis' => $p['opis'], 'plec' => $p['plec'],
        'notatka' => $p['notatka'], 'zdjecie' => $p['zdjecie'], 'zdjecie_alt' => $p['zdjecie_alt'], 'linkedin' => $p['linkedin'],
        'obraz_modal' => $modal, 'obraz_karta' => $karta,
    ];
}

// ?lista=1: edycje do podmenu PM Session w nagłówku strony (js/main.js). Tylko bieżąca/zapowiedź i zakończone, które mają własny plik
// pm-session-<numer>.html (jak pms_adres_strony() w panelu); szkic nigdy. Zwraca wyłącznie numer, status i adres strony.
// 'wszystkich' = liczba edycji w bazie (także szkiców): gdy 0 (baza przed importem), menu zostaje w wersji statycznej z HTML.
if (($_GET['lista'] ?? '') === '1') {
    try {
        $pdo = pmg_db();
        $lista = [];
        foreach ($pdo->query("SELECT numer, status FROM pmg_edycje WHERE status IN ('biezaca','zapowiedz','zakonczona') ORDER BY status IN ('biezaca','zapowiedz') DESC, data DESC, id DESC")->fetchAll() as $r) {
            if (!preg_match('~^[IVXLC]{1,10}$~', $r['numer'])) continue;
            $plik = 'pm-session-' . strtolower($r['numer']) . '.html';
            if (!is_file(__DIR__ . '/../' . $plik)) continue;
            $lista[] = ['numer' => $r['numer'], 'status' => $r['status'], 'adres' => $plik];
        }
        $wszystkich = (int) $pdo->query('SELECT COUNT(*) FROM pmg_edycje')->fetchColumn();
    } catch (PDOException $e) {
        pmg_json(['error' => 'db'], 500);
    }
    header('Cache-Control: public, max-age=300');
    pmg_json(['edycje' => $lista, 'wszystkich' => $wszystkich]);
}

$numer = isset($_GET['numer']) ? strtoupper(trim((string) $_GET['numer'])) : '';
if ($numer !== '' && !preg_match('~^[IVXLC]{1,10}$~', $numer)) {
    pmg_json(['error' => 'numer'], 400);
}

try {
    if ($numer === '') {
        $edycjaRow = pmg_db()->query("SELECT id, numer, temat, data, miejsce, opis, status FROM pmg_edycje WHERE status IN ('biezaca','zapowiedz') ORDER BY status = 'biezaca' DESC LIMIT 1")->fetch();
    } else {
        $st0 = pmg_db()->prepare("SELECT id, numer, temat, data, miejsce, opis, status FROM pmg_edycje WHERE numer = ? AND status IN ('biezaca','zapowiedz','zakonczona') LIMIT 1");
        $st0->execute([$numer]);
        $edycjaRow = $st0->fetch();
    }
    $prelegenci = [];
    $harmonogram = [];
    if ($edycjaRow && $edycjaRow['status'] !== 'zapowiedz') {
        $st = pmg_db()->prepare('SELECT imie_nazwisko, temat, bio, opis, plec, notatka, zdjecie, zdjecie_alt, linkedin FROM pmg_prelegenci WHERE edycja_id = ? ORDER BY kolejnosc, id');
        $st->execute([$edycjaRow['id']]);
        foreach ($st->fetchAll() as $p) $prelegenci[] = pms_prelegent_pub($p);

        // Alias TIME_FORMAT(...) AS godzina zacieniałby kolumnę godzina w ORDER BY (MySQL/MariaDB sortowałyby
        // wtedy po sformatowanym tekście: "10:00" < "8:30" leksykalnie) — dlatego osobny alias godzina_fmt.
        $st2 = pmg_db()->prepare("SELECT TIME_FORMAT(godzina, '%k:%i') AS godzina_fmt, tytul, prelegent, znacznik FROM pmg_harmonogram WHERE edycja_id = ? ORDER BY godzina, id");
        $st2->execute([$edycjaRow['id']]);
        foreach ($st2->fetchAll() as $h) $harmonogram[] = ['godzina' => $h['godzina_fmt'], 'tytul' => $h['tytul'], 'prelegent' => $h['prelegent'], 'znacznik' => $h['znacznik']];
    }
} catch (PDOException $e) {
    pmg_json(['error' => 'db'], 500);
}

header('Cache-Control: public, max-age=300');
if (!$edycjaRow) {
    $edycjaPub = null;
} elseif ($edycjaRow['status'] === 'zapowiedz') {
    $edycjaPub = ['numer' => $edycjaRow['numer'], 'status' => 'zapowiedz'];
} else {
    $edycjaPub = ['numer' => $edycjaRow['numer'], 'status' => $edycjaRow['status'], 'temat' => $edycjaRow['temat'], 'data' => $edycjaRow['data'], 'miejsce' => $edycjaRow['miejsce'], 'opis' => $edycjaRow['opis']];
}
pmg_json([
    'edycja' => $edycjaPub,
    'prelegenci' => $prelegenci,
    'harmonogram' => $harmonogram,
]);
