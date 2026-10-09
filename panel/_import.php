<?php
// Wczytywanie treści, które do tej pory były wpisane na sztywno w HTML (api/dane-startowe.php), do PUSTYCH tabel.
// Tylko administrator; przycisk „Wczytaj treści ze strony” pokazują moduły Aktualności, Członkowie, PM Session, Case Koła i Ustawienia.
// Wołany z modułów (pmg_import_blok w widoku listy, pmg_import_wykonaj w obsłudze POST a=import). Nie jest modułem do wywołania z ?m=.
defined('PMG_PANEL') || exit;

// Tabele, które muszą być puste, żeby moduł mógł wczytać swoje dane. Prelegenci i harmonogram mają klucz obcy do edycji,
// więc przy pustych edycjach ich też nie ma. Ustawienia sprawdza import_pusty() osobno (klucze, nie cała tabela).
const IMPORT_TABELE = [
    'aktualnosci' => ['pmg_aktualnosci'],
    'czlonkowie' => ['pmg_osoby', 'pmg_sekcje'],
    'pmsession' => ['pmg_edycje'],
    'case' => ['pmg_case_edycje', 'pmg_case_galeria'],
];

function import_dane()
{
    static $d = null;
    if ($d === null) $d = require __DIR__ . '/../api/dane-startowe.php';
    return $d;
}

// Czy moduł ma jeszcze pustą bazę, czyli czy wolno (i warto) wczytać dane.
function import_pusty($modul)
{
    $pdo = pmg_db();
    if ($modul === 'ustawienia') {
        $klucze = array_keys(import_dane()['ustawienia']);
        $st = $pdo->prepare("SELECT COUNT(*) FROM pmg_ustawienia WHERE wartosc <> '' AND klucz IN (" . implode(',', array_fill(0, count($klucze), '?')) . ')');
        $st->execute($klucze);
        return (int) $st->fetchColumn() === 0;
    }
    foreach (IMPORT_TABELE[$modul] as $t) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn() > 0) return false;
    }
    return true;
}

// Zapisuje klucze ustawień, które jeszcze nie mają wartości (istniejących, niepustych wartości nie rusza). Zwraca liczbę zapisanych.
function import_ustawienia($ust)
{
    $pdo = pmg_db();
    $ile = 0;
    $sel = $pdo->prepare('SELECT wartosc FROM pmg_ustawienia WHERE klucz = ?');
    $zapisz = $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?,?)');
    foreach ($ust as $klucz => $wartosc) {
        $sel->execute([$klucz]);
        $obecna = $sel->fetchColumn();
        if ($obecna !== false && $obecna !== '') continue;
        $zapisz->execute([$klucz, $wartosc]);
        $ile++;
    }
    return $ile;
}

// Przycisk w widoku listy modułu: tylko dla administratora i tylko przy pustej bazie danego modułu.
function pmg_import_blok($modul)
{
    if (!wolno('admin') || !import_pusty($modul)) return;
    $d = import_dane();
    $e = $d['edycje'][0];
    $opisy = [
        'aktualnosci' => 'Wpisy z plików strony (' . count($d['aktualnosci']) . ') nie są jeszcze w bazie, dlatego panel ich nie pokazuje. Po wczytaniu będą opublikowane z tymczasowymi datami (15, 20 i 25 września 2026) i strona zacznie je pokazywać z panelu, w tym samym wyglądzie (zamiast „data do ustalenia” zobaczysz datę). Prawdziwe daty ustawisz przy każdym wpisie.',
        'czlonkowie' => 'Osoby i sekcje z pliku O nas (osób: ' . count($d['osoby']) . ', sekcji: ' . count($d['sekcje']) . ') nie są jeszcze w bazie, dlatego panel ich nie pokazuje. Po wczytaniu strona O nas będzie pokazywać dane z panelu (wygląda tak samo) i będzie można je tu edytować.',
        'pmsession' => 'Edycja ' . $e['numer'] . ' z plików strony (prelegentów: ' . count($e['prelegenci']) . ', punktów harmonogramu: ' . count($e['harmonogram']) . ') oraz liczby „PM Session w liczbach” nie są jeszcze w bazie. Edycja zostanie wczytana jako zakończona (publiczna) z pełnymi biogramami i opisami prelekcji, a strona pokaże ją z panelu w tym samym wyglądzie. Edycji XV nie wczytujemy.',
        'case' => 'Edycje Case Koła z plików strony (' . count($d['case']) . ': karty w hubie oraz treść podstron, w tym galerie) nie są jeszcze w bazie, dlatego panel ich nie pokazuje. Po wczytaniu hub pokaże karty z panelu, a każda karta otworzy wspólną podstronę edycji (wygląda tak samo jak dotychczasowe), więc treść wszystkich edycji będzie można tu zmieniać. Stare adresy podstron (np. case-kola-solvro.html) nadal działają, ale pokazują wersję z pliku.',
        'ustawienia' => 'Linki do mediów społecznościowych, e-mail i link rekrutacyjny są dziś wpisane w HTML, a formularz poniżej jest pusty. Wczytaj ich obecne wartości, żeby je tu zobaczyć i zmieniać. Strona wygląda tak samo.',
    ];
    echo '<form class="pmg-card" method="post"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="a" value="import">'
        . '<div class="pmg-card__head"><h2 class="pmg-h2">Treści ze strony</h2></div>'
        . '<p class="pmg-hint">' . h($opisy[$modul]) . ' Możesz to zrobić tylko raz, dopóki baza jest pusta.</p>'
        . '<div class="pmg-form-actions"><button class="pmg-btn pmg-btn--secondary" type="submit">Wczytaj treści ze strony</button></div></form>';
}

// Wstawia dane modułu (w transakcji otwartej przez wywołującego) i zwraca komunikat o wyniku.
function import_wstaw($modul, $d)
{
    $pdo = pmg_db();
    if ($modul === 'aktualnosci') {
        $st = $pdo->prepare('INSERT INTO pmg_aktualnosci (slug, data, kategoria, kolor, tytul, lead, zajawka, tresc, zdjecie, zdjecie_alt, autor, opublikowany) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $szkice = 0;
        foreach ($d['aktualnosci'] as $a) {
            $szkic = $a['data'] === null; // data „do ustalenia” w HTML: wpis zostaje szkicem z datą dnia wczytania
            $szkice += $szkic ? 1 : 0;
            $st->execute([$a['slug'], $szkic ? date('Y-m-d') : $a['data'], $a['kategoria'], $a['kolor'], $a['tytul'], $a['lead'], $a['zajawka'], $a['tresc'], $a['zdjecie'], $a['zdjecie_alt'], $a['autor'], $szkic ? 0 : 1]);
        }
        $ile = count($d['aktualnosci']);
        $komunikat = 'Wczytano ' . pmg_odmiana($ile, 'wpis', 'wpisy', 'wpisów') . ($szkice ? ' jako szkice' : '') . '.'
            . ($szkice ? ' W HTML ich data to „do ustalenia”, więc mają datę dzisiejszą: ustaw prawdziwą datę i opublikuj. Do tego czasu strona pokazuje wersję z HTML.' : ' Daty są tymczasowe (w HTML było „do ustalenia”) — ustaw prawdziwe w każdym wpisie. Strona pokazuje teraz wpisy z panelu (w ciągu 5 minut).');
    } elseif ($modul === 'czlonkowie') {
        $stS = $pdo->prepare('INSERT INTO pmg_sekcje (nazwa, kolor, opis, kolejnosc) VALUES (?,?,?,?)');
        $idSekcji = [];
        foreach ($d['sekcje'] as $s) {
            $stS->execute([$s['nazwa'], $s['kolor'], $s['opis'], $s['kolejnosc']]);
            $idSekcji[$s['nazwa']] = (int) $pdo->lastInsertId();
        }
        $stO = $pdo->prepare('INSERT INTO pmg_osoby (imie, nazwisko, funkcja, sekcja_id, koordynator, email, linkedin, zdjecie, zdjecie_alt, kolejnosc, aktywna) VALUES (?,?,?,?,?,?,?,?,?,?,1)');
        foreach ($d['osoby'] as $o) {
            $stO->execute([$o['imie'], $o['nazwisko'], $o['funkcja'], $o['sekcja'] === null ? null : $idSekcji[$o['sekcja']], $o['koordynator'], $o['email'], $o['linkedin'], $o['zdjecie'], $o['zdjecie_alt'], $o['kolejnosc']]);
        }
        $komunikat = 'Wczytano ' . pmg_odmiana(count($d['osoby']), 'osobę', 'osoby', 'osób') . ' (sekcji: ' . count($d['sekcje']) . '). Strona O nas pokazuje teraz dane z panelu (w ciągu 5 minut).';
    } elseif ($modul === 'pmsession') {
        $prel = 0;
        $pkt = 0;
        $stE = $pdo->prepare('INSERT INTO pmg_edycje (numer, temat, data, miejsce, opis, status) VALUES (?,?,?,?,?,?)');
        $stP = $pdo->prepare('INSERT INTO pmg_prelegenci (edycja_id, imie_nazwisko, temat, bio, opis, plec, notatka, zdjecie, zdjecie_alt, linkedin, kolejnosc) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stH = $pdo->prepare('INSERT INTO pmg_harmonogram (edycja_id, godzina, tytul, prelegent, znacznik) VALUES (?,?,?,?,?)');
        foreach ($d['edycje'] as $e) {
            $stE->execute([$e['numer'], $e['temat'], $e['data'], $e['miejsce'], $e['opis'], $e['status']]);
            $eid = (int) $pdo->lastInsertId();
            foreach ($e['prelegenci'] as $p) {
                $stP->execute([$eid, $p['imie_nazwisko'], $p['temat'], $p['bio'], $p['opis'], $p['plec'], $p['notatka'], $p['zdjecie'], $p['zdjecie_alt'], $p['linkedin'], $p['kolejnosc']]);
                $prel++;
            }
            foreach ($e['harmonogram'] as $h) {
                $g = explode(':', $h[0]);
                $stH->execute([$eid, sprintf('%02d:%02d:00', (int) $g[0], (int) $g[1]), $h[1], $h[2], $h[3] ?? '']);
                $pkt++;
            }
        }
        $liczby = import_ustawienia($d['ustawienia_pms']);
        $komunikat = 'Wczytano edycję ' . $d['edycje'][0]['numer'] . ' jako ' . ($d['edycje'][0]['status'] === 'zakonczona' ? 'zakończoną' : 'szkic') . ' (prelegentów: ' . $prel . ', punktów harmonogramu: ' . $pkt . ')'
            . ($liczby ? ' oraz liczby „PM Session w liczbach” (' . $liczby . ')' : '') . '. Strona pokazuje teraz tę edycję z panelu (w ciągu 5 minut).';
    } elseif ($modul === 'case') {
        $stE = $pdo->prepare('INSERT INTO pmg_case_edycje (numer, nazwa, tytul_karty, naglowek, adres_strony, opis_meta, logo, logo_styl, hero, hero_alt, o_partnerze, wyzwanie, co_zrobilismy, rezultat, w_toku, kolejnosc, widoczna) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stG = $pdo->prepare('INSERT INTO pmg_case_galeria (edycja_id, zdjecie, pelne, podpis, kolejnosc) VALUES (?,?,?,?,?)');
        $zdj = 0;
        foreach ($d['case'] as $c) {
            $stE->execute([$c['numer'], $c['nazwa'], $c['tytul_karty'], $c['naglowek'], $c['adres_strony'], $c['opis_meta'], $c['logo'], $c['logo_styl'], $c['hero'], $c['hero_alt'], $c['o_partnerze'], $c['wyzwanie'], $c['co_zrobilismy'], $c['rezultat'], $c['w_toku'], $c['kolejnosc'], $c['widoczna']]);
            $eid = (int) $pdo->lastInsertId();
            foreach ($c['galeria'] as $i => $g) {
                $stG->execute([$eid, $g[0], $g[2], $g[1], $i + 1]);
                $zdj++;
            }
        }
        $komunikat = 'Wczytano ' . pmg_odmiana(count($d['case']), 'edycję', 'edycje', 'edycji') . ' Case Koła (zdjęć w galeriach: ' . $zdj . '). Hub pokazuje teraz karty z panelu (w ciągu 5 minut), a karty prowadzą do wspólnej podstrony edycji. Stare adresy podstron (case-kola-solvro.html itd.) nadal działają, ale pokazują wersję z pliku.';
    } elseif ($modul === 'ustawienia') {
        $ile = import_ustawienia($d['ustawienia']);
        $komunikat = 'Wczytano ' . pmg_odmiana($ile, 'ustawienie', 'ustawienia', 'ustawień') . '. Strona wygląda tak samo, a wartości można tu teraz zmieniać.';
    } else {
        throw new RuntimeException('Nieznany moduł importu.');
    }
    return $komunikat;
}

// Obsługa POST a=import: w transakcji, idempotentna (niepusta baza = nic się nie dzieje), z wpisem do dziennika.
// Sukces i „nic do zrobienia” kończą się przekierowaniem z komunikatem; błąd bazy ustawia $error i wraca do widoku.
function pmg_import_wykonaj($modul)
{
    global $error;
    if (!wolno('admin')) {
        http_response_code(403);
        exit('Tylko administrator może wczytać treści ze strony.');
    }
    $pdo = pmg_db();
    $nic = false;
    $pdo->beginTransaction();
    try {
        // Blokada tabel i ponowne sprawdzenie w transakcji: dwa równoczesne kliknięcia nie wczytają danych dwa razy.
        $komunikat = null;
        foreach (IMPORT_TABELE[$modul] ?? [] as $t) {
            if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $t . ' FOR UPDATE')->fetchColumn() > 0) $komunikat = 'Nic nie wczytano: ten moduł ma już dane. Wczytanie działa tylko na pustej bazie.';
        }
        if ($komunikat === null && $modul === 'ustawienia' && !import_pusty('ustawienia')) $komunikat = 'Nic nie wczytano: ustawienia mają już wartości. Wczytanie działa tylko na pustych ustawieniach.';
        if ($komunikat !== null) {
            $pdo->rollBack();
            $nic = true;
        } else {
            $komunikat = import_wstaw($modul, import_dane());
            $pdo->commit();
        }
    } catch (Exception $e) { // PDOException i RuntimeException; błąd bazy nie zostawia połowy danych
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('pmg_import_wykonaj(' . $modul . '): ' . $e->getMessage());
        $error = 'Nie udało się wczytać treści (błąd bazy danych) — nic nie zapisano. Spróbuj ponownie albo zgłoś problem administratorowi strony.';
        return;
    }
    if (!$nic) loguj($modul, 'import');
    $_SESSION['flash'] = $komunikat;
    go('?m=' . $modul);
}
