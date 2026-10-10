<?php
// Moduł PM Session: edycje, prelegenci, harmonogram, liczby "PM Session w liczbach". Wołany wyłącznie z index.php (?m=pmsession).
defined('PMG_PANEL') || exit;

const PMS_LICZBY = ['pms_edycji' => 'Edycji', 'pms_prelekcji' => 'Prelekcji', 'pms_prelegentow' => 'Prelegentów', 'pms_uczestnikow' => 'Uczestników', 'pms_warsztatow' => 'Warsztatów', 'pms_symulacji' => 'Symulacji'];

// Jedyne miejsce ustawiania statusu 'biezaca' — w transakcji: obecna bieżąca -> zakończona, wybrana -> bieżąca.
// Zwraca false (bez żadnych zmian), gdy edycja o tym id nie istnieje — inaczej stara bieżąca zostałaby zdjęta, a nowej nie byłoby.
function ustaw_biezaca($id)
{
    $pdo = pmg_db();
    $st = $pdo->prepare('SELECT numer, temat FROM pmg_edycje WHERE id = ?');
    $st->execute([$id]);
    $ed = $st->fetch();
    if (!$ed) return false;
    $pdo->beginTransaction();
    $pdo->exec("UPDATE pmg_edycje SET status = 'zakonczona' WHERE status = 'biezaca'");
    $pdo->prepare("UPDATE pmg_edycje SET status = 'biezaca' WHERE id = ?")->execute([$id]);
    $pdo->commit();
    loguj('pmsession', 'biezaca', $id, 'Edycja ' . $ed['numer'] . ': ' . $ed['temat']);
    return true;
}

// Edycja jest widoczna na stronie, gdy jest bieżąca albo zakończona (szkic nie, patrz api/pmsession.php).
function pms_widoczna($status)
{
    return $status === 'biezaca' || $status === 'zakonczona';
}

// Adres publicznej strony edycji: własny plik pm-session-<numer>.html, a gdy go nie ma — wspólna pm-session.html.
function pms_adres_strony($numer)
{
    $numer = strtolower((string) $numer);
    if (preg_match('~^[ivxlc]+$~', $numer) && is_file(__DIR__ . '/../pm-session-' . $numer . '.html')) return '../pm-session-' . $numer . '.html';
    return '../pm-session.html';
}

// Komunikat po zapisie prelegenta lub punktu harmonogramu: dopisek o opóźnieniu tylko, gdy edycja jest widoczna na stronie.
function pms_komunikat_zapisu($edycjaId)
{
    $st = pmg_db()->prepare('SELECT status FROM pmg_edycje WHERE id = ?');
    $st->execute([(int) $edycjaId]);
    return pms_widoczna($st->fetchColumn()) ? 'Zapisano. Na stronie zmiana pojawi się w ciągu 5 minut.' : 'Zapisano.';
}

$error = '';
$editEdycja = null;
$editPrelegent = null;
$editHarmonogram = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    // ---------- Edycje ----------
    if ($action === 'edycja_biezaca') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!ustaw_biezaca($id)) {
            $error = 'Nie znaleziono edycji.';
        } else {
            $_SESSION['flash'] = 'Ustawiono jako bieżącą edycję.';
            go('?m=pmsession');
        }

    } elseif ($action === 'import') {
        pmg_import_wykonaj('pmsession');
    } elseif ($action === 'edycja_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT status, numer, temat FROM pmg_edycje WHERE id = ?'); // numer i temat do dziennika, zanim wiersz zniknie
        $st->execute([$id]);
        $usuwana = $st->fetch();
        $status = $usuwana ? $usuwana['status'] : false;
        if ($status === false) {
            $error = 'Nie znaleziono edycji.';
        } elseif ($status === 'biezaca') {
            $error = 'Nie można usunąć bieżącej edycji.';
        } else {
            try {
                $st = pmg_db()->prepare('DELETE FROM pmg_edycje WHERE id = ?');
                $st->execute([$id]);
                if ($st->rowCount() > 0) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
                    loguj('pmsession', 'usuniecie', $id, 'Edycja ' . $usuwana['numer'] . ': ' . $usuwana['temat']);
                    $_SESSION['flash'] = 'Edycja usunięta.';
                    go('?m=pmsession');
                }
                $error = 'Nie znaleziono edycji — nic nie usunięto.';
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Najpierw usuń prelegentów i harmonogram tej edycji.' : 'Błąd usuwania.';
            }
        }

    } elseif ($action === 'edycja_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [
            'numer' => mb_strtoupper(trim((string) ($_POST['numer'] ?? '')), 'UTF-8'),
            'temat' => mb_substr(trim((string) ($_POST['temat'] ?? '')), 0, 200),
            'data' => (string) ($_POST['data'] ?? ''),
            'miejsce' => mb_substr(trim((string) ($_POST['miejsce'] ?? '')), 0, 200),
            'opis' => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['opis'] ?? ''))), 0, 600),
        ];
        // Formularz udostępnia tylko szkic/zakonczona; nieprawidłowa wartość -> szkic.
        $status = ($_POST['status'] ?? '') === 'zakonczona' ? 'zakonczona' : 'szkic';
        $obecnyStatus = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT status FROM pmg_edycje WHERE id = ?');
            $st->execute([$id]);
            $obecnyStatus = $st->fetchColumn();
            // Bieżącej edycji formularz NIGDY nie zmienia statusu — nawet przy spreparowanym POST-cie.
            if ($obecnyStatus === 'biezaca') $status = 'biezaca';
        }

        // Każde pole sprawdzane osobno, w kolejności pól formularza — użytkownik widzi wszystkie błędy naraz.
        if (!preg_match('~^[IVXLC]+$~', $f['numer'])) $bledyPol['numer'] = 'Numer edycji: użyj tylko cyfr rzymskich (I, V, X, L, C), np. XIV.';
        if ($f['temat'] === '') $bledyPol['temat'] = 'Podaj temat edycji.';
        if (!data_ok($f['data'])) $bledyPol['data'] = 'Podaj poprawną datę.';
        if ($f['miejsce'] === '') $bledyPol['miejsce'] = 'Podaj miejsce.';
        if (!$bledyPol) {
            try {
                if ($id) {
                    pmg_db()->prepare('UPDATE pmg_edycje SET numer=?, temat=?, data=?, miejsce=?, opis=?, status=? WHERE id=?')
                        ->execute([$f['numer'], $f['temat'], $f['data'], $f['miejsce'], $f['opis'], $status, $id]);
                    loguj('pmsession', 'edycja', $id, 'Edycja ' . $f['numer'] . ': ' . $f['temat']);
                } else {
                    pmg_db()->prepare('INSERT INTO pmg_edycje (numer, temat, data, miejsce, opis, status) VALUES (?,?,?,?,?,?)')
                        ->execute([$f['numer'], $f['temat'], $f['data'], $f['miejsce'], $f['opis'], $status]);
                    $id = (int) pmg_db()->lastInsertId();
                    loguj('pmsession', 'dodanie', $id, 'Edycja ' . $f['numer'] . ': ' . $f['temat']);
                    // nowa edycja: od razu widok tej edycji (prelegenci i harmonogram), żeby dalszy krok był oczywisty
                    $_SESSION['flash'] = 'Edycja zapisana. Dodaj teraz prelegentów i harmonogram.' . (pms_widoczna($status) ? ' Na stronie zmiana pojawi się w ciągu 5 minut.' : '');
                    go('?m=pmsession&e=' . $id);
                }
                $_SESSION['flash'] = pms_widoczna($status) ? 'Zapisano. Na stronie zmiana pojawi się w ciągu 5 minut.' : 'Zapisano.';
                go('?m=pmsession');
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Edycja o tym numerze już istnieje.' : 'Błąd zapisu.';
            }
        }
        if ($error !== '' || $bledyPol) $editEdycja = array_merge($f, ['id' => $id, 'status' => $obecnyStatus ?: $status]);

    // ---------- Prelegenci ----------
    } elseif ($action === 'prelegent_gora' || $action === 'prelegent_dol') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        if (przesun('pmg_prelegenci', 'kolejnosc, id', $id, $action === 'prelegent_gora' ? 'gora' : 'dol', 'edycja_id', $edycjaId)) loguj('pmsession', 'kolejnosc', $id, 'Prelegent ' . nazwa_rekordu('SELECT imie_nazwisko FROM pmg_prelegenci WHERE id = ?', $id));
        go_po_przesunieciu('?m=pmsession&e=' . $edycjaId, 'p' . $id, $action === 'prelegent_gora' ? 'gora' : 'dol');

    } elseif ($action === 'prelegent_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $st = pmg_db()->prepare('SELECT zdjecie, imie_nazwisko FROM pmg_prelegenci WHERE id = ?'); // imię do dziennika, zanim wiersz zniknie
        $st->execute([$id]);
        $prel = $st->fetch();
        $img = $prel ? $prel['zdjecie'] : null;
        $st = pmg_db()->prepare('DELETE FROM pmg_prelegenci WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
            $_SESSION['flash'] = 'Nie znaleziono prelegenta — nic nie usunięto.';
            go('?m=pmsession&e=' . $edycjaId);
        }
        drop_image($img ?: null);
        loguj('pmsession', 'usuniecie', $id, 'Prelegent ' . $prel['imie_nazwisko']);
        $_SESSION['flash'] = 'Prelegent usunięty.';
        go('?m=pmsession&e=' . $edycjaId);

    } elseif ($action === 'prelegent_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $f = [
            'imie_nazwisko' => mb_substr(trim((string) ($_POST['imie_nazwisko'] ?? '')), 0, 100),
            'temat' => mb_substr(trim((string) ($_POST['temat'] ?? '')), 0, 300),
            'bio' => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['bio'] ?? ''))), 0, 2500),
            'opis' => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['opis'] ?? ''))), 0, 4000),
            'plec' => ($_POST['plec'] ?? '') === 'k' ? 'k' : 'm',
            'notatka' => mb_substr(trim((string) ($_POST['notatka'] ?? '')), 0, 200),
            'linkedin' => mb_substr(trim((string) ($_POST['linkedin'] ?? '')), 0, 200),
            'zdjecie_alt' => mb_substr(trim((string) ($_POST['zdjecie_alt'] ?? '')), 0, 200),
        ];
        $stE = pmg_db()->prepare('SELECT 1 FROM pmg_edycje WHERE id = ?');
        $stE->execute([$edycjaId]);
        $old = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_prelegenci WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        if (!$stE->fetchColumn()) $error = 'Nieprawidłowa edycja.'; // nie dotyczy żadnego pola
        // Każde pole sprawdzane osobno, w kolejności pól formularza — użytkownik widzi wszystkie błędy naraz.
        if ($f['imie_nazwisko'] === '') $bledyPol['imie_nazwisko'] = 'Podaj imię i nazwisko.';
        if ($f['temat'] === '') $bledyPol['temat'] = 'Podaj temat wystąpienia.';
        if (!url_ok($f['linkedin'])) $bledyPol['linkedin'] = 'LinkedIn: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        blad_opisu_zdjecia($old['zdjecie'] ?? null);
        if ($error === '' && !$bledyPol) {
            $noweZdjecie = null; // plik wgrany w tym żądaniu — usuwany, jeśli zapis do bazy się nie uda
            try {
                $stareZdjecie = $old['zdjecie'] ?? null;
                $f['zdjecie'] = zdjecie('pmsession', $stareZdjecie);
                if ($f['zdjecie'] !== $stareZdjecie) $noweZdjecie = $f['zdjecie'];
                if ($id && $old) {
                    $st = pmg_db()->prepare('UPDATE pmg_prelegenci SET imie_nazwisko=?, temat=?, bio=?, opis=?, plec=?, notatka=?, zdjecie=?, zdjecie_alt=?, linkedin=? WHERE id=?');
                    $st->execute([$f['imie_nazwisko'], $f['temat'], $f['bio'], $f['opis'], $f['plec'], $f['notatka'], $f['zdjecie'], $f['zdjecie_alt'], $f['linkedin'], $id]);
                    $noweZdjecie = null;
                    if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                    loguj('pmsession', 'edycja', $id, 'Prelegent ' . $f['imie_nazwisko']);
                } else {
                    // Nowy prelegent trafia na koniec listy swojej edycji (kolejność zmieniasz strzałkami na liście).
                    $stN = pmg_db()->prepare('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_prelegenci WHERE edycja_id = ?');
                    $stN->execute([$edycjaId]);
                    $st = pmg_db()->prepare('INSERT INTO pmg_prelegenci (edycja_id, imie_nazwisko, temat, bio, opis, plec, notatka, zdjecie, zdjecie_alt, linkedin, kolejnosc) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $st->execute([$edycjaId, $f['imie_nazwisko'], $f['temat'], $f['bio'], $f['opis'], $f['plec'], $f['notatka'], $f['zdjecie'], $f['zdjecie_alt'], $f['linkedin'], (int) $stN->fetchColumn()]);
                    $noweZdjecie = null;
                    $id = (int) pmg_db()->lastInsertId();
                    loguj('pmsession', 'dodanie', $id, 'Prelegent ' . $f['imie_nazwisko']);
                }
                $_SESSION['flash'] = pms_komunikat_zapisu($edycjaId);
                go('?m=pmsession&e=' . $edycjaId);
            } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
                error_log('pmsession prelegent zapis: ' . $e->getMessage());
                drop_image($noweZdjecie);
                $f['zdjecie'] = $old['zdjecie'] ?? null;
                $error = 'Błąd zapisu — nic nie zapisano. Spróbuj ponownie.';
            } catch (BladPliku $e) {
                $bledyPol['zdjecie'] = $e->getMessage();
            }
        }
        if ($error !== '' || $bledyPol) $editPrelegent = array_merge($old ?: [], $f, ['id' => $id, 'edycja_id' => $edycjaId]);

    // ---------- Harmonogram ----------
    } elseif ($action === 'harmonogram_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $nazwa = nazwa_rekordu("SELECT CONCAT('Harmonogram ', TIME_FORMAT(godzina, '%H:%i'), ' ', tytul) FROM pmg_harmonogram WHERE id = ?", $id); // do dziennika, zanim wiersz zniknie
        $st = pmg_db()->prepare('DELETE FROM pmg_harmonogram WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
            $_SESSION['flash'] = 'Nie znaleziono punktu harmonogramu — nic nie usunięto.';
            go('?m=pmsession&e=' . $edycjaId);
        }
        loguj('pmsession', 'usuniecie', $id, $nazwa);
        $_SESSION['flash'] = 'Punkt harmonogramu usunięty.';
        go('?m=pmsession&e=' . $edycjaId);

    } elseif ($action === 'harmonogram_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $f = [
            'godzina' => (string) ($_POST['godzina'] ?? ''),
            'tytul' => mb_substr(trim((string) ($_POST['tytul'] ?? '')), 0, 300),
            'prelegent' => mb_substr(trim((string) ($_POST['prelegent'] ?? '')), 0, 150),
            'znacznik' => mb_substr(trim((string) ($_POST['znacznik'] ?? '')), 0, 40),
        ];
        $stE = pmg_db()->prepare('SELECT 1 FROM pmg_edycje WHERE id = ?');
        $stE->execute([$edycjaId]);
        if (!$stE->fetchColumn()) $error = 'Nieprawidłowa edycja.'; // nie dotyczy żadnego pola
        if (!preg_match('~^([01]\d|2[0-3]):[0-5]\d$~', $f['godzina'])) $bledyPol['godzina'] = 'Podaj poprawną godzinę.';
        if ($f['tytul'] === '') $bledyPol['tytul'] = 'Podaj tytuł punktu harmonogramu.';
        if ($error === '' && !$bledyPol) {
            if ($id) {
                pmg_db()->prepare('UPDATE pmg_harmonogram SET godzina=?, tytul=?, prelegent=?, znacznik=? WHERE id=?')
                    ->execute([$f['godzina'], $f['tytul'], $f['prelegent'], $f['znacznik'], $id]);
                loguj('pmsession', 'edycja', $id, 'Harmonogram ' . $f['godzina'] . ' ' . $f['tytul']);
            } else {
                pmg_db()->prepare('INSERT INTO pmg_harmonogram (edycja_id, godzina, tytul, prelegent, znacznik) VALUES (?,?,?,?,?)')
                    ->execute([$edycjaId, $f['godzina'], $f['tytul'], $f['prelegent'], $f['znacznik']]);
                $id = (int) pmg_db()->lastInsertId();
                loguj('pmsession', 'dodanie', $id, 'Harmonogram ' . $f['godzina'] . ' ' . $f['tytul']);
            }
            $_SESSION['flash'] = pms_komunikat_zapisu($edycjaId);
            go('?m=pmsession&e=' . $edycjaId);
        }
        if ($error !== '' || $bledyPol) $editHarmonogram = array_merge($f, ['id' => $id, 'edycja_id' => $edycjaId]);

    // ---------- Liczby "PM Session w liczbach" ----------
    } elseif ($action === 'liczby_zapisz') {
        $wejscie = [];
        foreach (PMS_LICZBY as $k => $etykieta) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v !== '' && !preg_match('~^\d{1,6}$~', $v)) {
                $bledyPol[$k] = $etykieta . ': podaj liczbę (do 6 cyfr) albo zostaw puste pole.';
            }
            $wejscie[$k] = $v;
        }
        if (!$bledyPol) {
            $pdo = pmg_db();
            $pdo->beginTransaction();
            $upd = $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?,?)');
            foreach ($wejscie as $k => $v) $upd->execute([$k, $v]);
            $pdo->commit();
            loguj('pmsession', 'edycja', null, 'PM Session w liczbach');
            $_SESSION['flash'] = 'Zapisano. Zmiany widać na stronie w ciągu 5 minut.';
            go('?m=pmsession');
        }
    }
}

// ---------- Widoki z GET, gdy nie ma już $edit z POST powyżej ----------
if ($editEdycja === null && isset($_GET['edycja'])) {
    if ($_GET['edycja'] === 'nowa') {
        $editEdycja = ['id' => 0, 'numer' => '', 'temat' => '', 'data' => '', 'miejsce' => '', 'opis' => '', 'status' => 'szkic'];
    } else {
        $st = pmg_db()->prepare('SELECT * FROM pmg_edycje WHERE id = ?');
        $st->execute([(int) $_GET['edycja']]);
        $editEdycja = $st->fetch() ?: null;
    }
}

$eid = isset($_GET['e']) ? (int) $_GET['e'] : 0;
$edycjaWidok = null;
if ($eid && $editEdycja === null) {
    $st = pmg_db()->prepare('SELECT * FROM pmg_edycje WHERE id = ?');
    $st->execute([$eid]);
    $edycjaWidok = $st->fetch() ?: null;
}

if ($edycjaWidok !== null) {
    if ($editPrelegent === null && isset($_GET['p'])) {
        if ($_GET['p'] === 'nowy') {
            $editPrelegent = ['id' => 0, 'edycja_id' => $eid, 'imie_nazwisko' => '', 'temat' => '', 'bio' => '', 'opis' => '', 'plec' => 'm', 'notatka' => '', 'linkedin' => '', 'zdjecie_alt' => ''];
        } else {
            $st = pmg_db()->prepare('SELECT * FROM pmg_prelegenci WHERE id = ? AND edycja_id = ?');
            $st->execute([(int) $_GET['p'], $eid]);
            $editPrelegent = $st->fetch() ?: null;
        }
    }
    if ($editHarmonogram === null && isset($_GET['h'])) {
        if ($_GET['h'] === 'nowy') {
            $editHarmonogram = ['id' => 0, 'edycja_id' => $eid, 'godzina' => '', 'tytul' => '', 'prelegent' => '', 'znacznik' => ''];
        } else {
            $st = pmg_db()->prepare('SELECT * FROM pmg_harmonogram WHERE id = ? AND edycja_id = ?');
            $st->execute([(int) $_GET['h'], $eid]);
            $editHarmonogram = $st->fetch() ?: null;
        }
    }
}

$fmtGodzina = function ($g) { return substr((string) $g, 0, 5); };

$wsteczEdycja = $edycjaWidok
    ? ['href' => '?m=pmsession&e=' . $eid, 'etykieta' => 'Edycja ' . $edycjaWidok['numer']]
    : ['href' => '?m=pmsession', 'etykieta' => 'PM Session'];
$opisEdycja = $edycjaWidok ? ('Edycja ' . $edycjaWidok['numer'] . ' — ' . $edycjaWidok['temat']) : '';
$pmgStatusTekst = ['szkic' => 'Szkic', 'biezaca' => 'Bieżąca', 'zakonczona' => 'Zakończona'];
$pmgStatusWariant = ['szkic' => 'neutral', 'biezaca' => 'accent', 'zakonczona' => 'done'];

if ($editPrelegent !== null) {
    $pmgNaglowek = [
        'tytul' => $editPrelegent['id'] ? 'Edytuj prelegenta' : 'Nowy prelegent',
        'opis' => $opisEdycja,
        'wstecz' => $wsteczEdycja,
    ];
} elseif ($editHarmonogram !== null) {
    $pmgNaglowek = [
        'tytul' => $editHarmonogram['id'] ? 'Edytuj punkt harmonogramu' : 'Nowy punkt harmonogramu',
        'opis' => $opisEdycja,
        'wstecz' => $wsteczEdycja,
    ];
} elseif ($editEdycja !== null) {
    $pmgNaglowek = [
        'tytul' => $editEdycja['id'] ? 'Edytuj edycję ' . $editEdycja['numer'] : 'Nowa edycja',
        'opis' => '',
        'wstecz' => ['href' => '?m=pmsession', 'etykieta' => 'PM Session'],
    ];
    if (!empty($editEdycja['id']) && pms_widoczna($editEdycja['status'] ?? '')) {
        $pmgNaglowek['akcje'] = [['href' => pms_adres_strony($editEdycja['numer']), 'etykieta' => 'Zobacz na stronie', 'rodzaj' => 'secondary', 'nowaKarta' => true]];
    }
} elseif ($edycjaWidok !== null) {
    $pmgNaglowek = [
        'tytul' => 'Edycja ' . $edycjaWidok['numer'],
        'opis' => $edycjaWidok['temat'] . ' · ' . $edycjaWidok['data'] . ' · ' . $edycjaWidok['miejsce'],
        'wstecz' => ['href' => '?m=pmsession', 'etykieta' => 'Wszystkie edycje'],
        'chip' => ['tekst' => $pmgStatusTekst[$edycjaWidok['status']] ?? 'Szkic', 'wariant' => $pmgStatusWariant[$edycjaWidok['status']] ?? 'neutral'],
        'akcje' => [
            ['href' => '?m=pmsession&edycja=' . (int) $edycjaWidok['id'], 'etykieta' => 'Edytuj edycję', 'rodzaj' => 'secondary'],
        ],
    ];
    if (pms_widoczna($edycjaWidok['status'])) {
        $pmgNaglowek['akcje'][] = ['href' => pms_adres_strony($edycjaWidok['numer']), 'etykieta' => 'Zobacz na stronie', 'rodzaj' => 'secondary', 'nowaKarta' => true];
    }
} else {
    $pmgNaglowek = [
        'akcje' => [
            ['href' => '?m=pmsession&edycja=nowa', 'etykieta' => '+ Nowa edycja', 'rodzaj' => 'primary'],
            ['href' => '../pm-session.html', 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($editPrelegent !== null): $v = function ($k) use (&$editPrelegent) { return h($editPrelegent[$k] ?? ''); }; ?>
  <form class="pmg-card" method="post" enctype="multipart/form-data" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="prelegent_zapisz"><input type="hidden" name="id" value="<?= (int) $editPrelegent['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editPrelegent['edycja_id'] ?>">

    <section class="pmg-form-section" aria-labelledby="sek-wystapienie">
      <h2 class="pmg-form-section__title" id="sek-wystapienie">Wystąpienie</h2>
      <label for="imie_nazwisko">Imię i nazwisko</label>
      <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" value="<?= $v('imie_nazwisko') ?>" required<?= blad_pola('imie_nazwisko') ?>><?= komunikat_pola('imie_nazwisko') ?>
      <label for="temat">Temat wystąpienia</label>
      <p class="pmg-hint" id="temat_h">Np. Prelekcja: „Tytuł” albo Warsztat: „Tytuł”.</p>
      <input type="text" id="temat" name="temat" maxlength="300" value="<?= $v('temat') ?>" required<?= blad_pola('temat', 'temat_h') ?> data-pmg-licznik><?= komunikat_pola('temat') ?>
      <label for="notatka">Notatka <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="notatka_h">Np. „Wspólny warsztat z Anną Nowak”. Widoczna nad tematem na stronie.</p>
      <input type="text" id="notatka" name="notatka" maxlength="200" value="<?= $v('notatka') ?>" aria-describedby="notatka_h">
      <p class="pmg-hint">Kolejność prelegentów zmieniasz strzałkami na liście prelegentów. Nowy prelegent trafia na koniec listy.</p>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-opis">
      <h2 class="pmg-form-section__title" id="sek-opis">Opis prelekcji</h2>
      <label for="opis">Opis prelekcji lub warsztatu <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="opis_h">Pokazuje się w oknie prelegenta pod tematem. Akapity oddzielaj pustą linią. Maksymalnie 4000 znaków.</p>
      <textarea id="opis" name="opis" maxlength="4000" rows="8" aria-describedby="opis_h" data-pmg-licznik><?= $v('opis') ?></textarea>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-bio">
      <h2 class="pmg-form-section__title" id="sek-bio">Biogram i LinkedIn</h2>
      <label for="bio">Biogram</label>
      <p class="pmg-hint" id="bio_h">Akapity oddzielaj pustą linią — na stronie będą osobnymi akapitami. Maksymalnie 2500 znaków.</p>
      <textarea id="bio" name="bio" maxlength="2500" rows="8" aria-describedby="bio_h" data-pmg-licznik><?= $v('bio') ?></textarea>
      <fieldset class="pmg-fieldset">
        <legend class="pmg-legend">Nagłówek biogramu w oknie prelegenta</legend>
        <div class="pmg-options">
          <label class="pmg-option"><input type="radio" name="plec" value="m"<?= ($editPrelegent['plec'] ?? 'm') !== 'k' ? ' checked' : '' ?>><span>O prelegencie</span></label>
          <label class="pmg-option"><input type="radio" name="plec" value="k"<?= ($editPrelegent['plec'] ?? '') === 'k' ? ' checked' : '' ?>><span>O prelegentce</span></label>
        </div>
      </fieldset>
      <label for="linkedin">LinkedIn <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="linkedin_h">Pełny adres zaczynający się od https://. Pole opcjonalne.</p>
      <input type="text" id="linkedin" name="linkedin" maxlength="200" value="<?= $v('linkedin') ?>"<?= blad_pola('linkedin', 'linkedin_h') ?>><?= komunikat_pola('linkedin') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zdjecie">
      <h2 class="pmg-form-section__title" id="sek-zdjecie">Zdjęcie</h2>
      <label for="zdjecie">Zdjęcie 4:3 (JPG, PNG albo WebP)</label>
      <p class="pmg-hint" id="zdjecie_h">Maks. 10 MB. Zdjęcie zostanie przycięte do proporcji 4:3 (ze środka). Najlepiej wgraj zdjęcie w tych proporcjach. Np. 1600 × 1200 px. Opcjonalne. Okno prelegenta pokazuje całe zdjęcie 4:3, a kafelek na liście jego środek w kwadracie — twarz ustaw pośrodku.</p>
      <?php if (!empty($editPrelegent['zdjecie'])): ?>
        <figure class="pmg-photo pmg-photo--4x3"><img src="../<?= h($editPrelegent['zdjecie']) ?>" alt=""><figcaption class="pmg-hint">Obecne zdjęcie. Wgranie nowego pliku zastąpi to zdjęcie.</figcaption></figure>
      <?php endif; ?>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp"<?= blad_pola('zdjecie', 'zdjecie_h') ?>><?= komunikat_pola('zdjecie') ?>
      <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
      <p class="pmg-hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
      <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>"<?= blad_pola('zdjecie_alt', 'zdjecie_alt_h') ?>><?= komunikat_pola('zdjecie_alt') ?>
    </section>

    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=pmsession&e=<?= (int) $editPrelegent['edycja_id'] ?>">Anuluj</a>
    </div>
  </form>
  <?php if ($editPrelegent['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="prelegent_usun"><input type="hidden" name="id" value="<?= (int) $editPrelegent['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editPrelegent['edycja_id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Prelegent zniknie ze strony PM Session. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tego prelegenta na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń prelegenta</button>
    </form>
  <?php endif; ?>

<?php elseif ($editHarmonogram !== null): $v = function ($k) use (&$editHarmonogram) { return h($editHarmonogram[$k] ?? ''); }; ?>
  <form class="pmg-card" method="post" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="harmonogram_zapisz"><input type="hidden" name="id" value="<?= (int) $editHarmonogram['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editHarmonogram['edycja_id'] ?>">
    <label for="godzina">Godzina</label>
    <input type="time" id="godzina" name="godzina" value="<?= $v('godzina') !== '' ? h($fmtGodzina($editHarmonogram['godzina'])) : '' ?>" required<?= blad_pola('godzina') ?>><?= komunikat_pola('godzina') ?>
    <label for="tytul">Tytuł</label>
    <input type="text" id="tytul" name="tytul" maxlength="300" value="<?= $v('tytul') ?>" required<?= blad_pola('tytul') ?>><?= komunikat_pola('tytul') ?>
    <label for="prelegent">Prelegent <span class="pmg-opt">(opcjonalnie)</span></label>
    <p class="pmg-hint" id="prelegent_h">Tekst dowolny, np. Jan Kowalski albo Jan Kowalski + Anna Nowak. Zostaw puste dla punktów bez prelegenta (np. Rejestracja).</p>
    <input type="text" id="prelegent" name="prelegent" maxlength="150" value="<?= $v('prelegent') ?>" aria-describedby="prelegent_h">
    <label for="znacznik">Znacznik pod godziną <span class="pmg-opt">(opcjonalnie)</span></label>
    <p class="pmg-hint" id="znacznik_h">Mały napis pod godziną, np. „3 sesje równoległe”. Wpisz go przy jednym z punktów tej godziny (wystarczy przy pierwszym). Puste pole = bez znacznika.</p>
    <input type="text" id="znacznik" name="znacznik" maxlength="40" value="<?= $v('znacznik') ?>" aria-describedby="znacznik_h">
    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=pmsession&e=<?= (int) $editHarmonogram['edycja_id'] ?>">Anuluj</a>
    </div>
  </form>
  <?php if ($editHarmonogram['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="harmonogram_usun"><input type="hidden" name="id" value="<?= (int) $editHarmonogram['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editHarmonogram['edycja_id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Punkt zniknie z harmonogramu na stronie. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń ten punkt harmonogramu na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń punkt</button>
    </form>
  <?php endif; ?>

<?php elseif ($editEdycja !== null): $v = function ($k) use (&$editEdycja) { return h($editEdycja[$k] ?? ''); }; ?>
  <form class="pmg-card" method="post" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="edycja_zapisz"><input type="hidden" name="id" value="<?= (int) $editEdycja['id'] ?>">
    <label for="numer">Numer (cyfry rzymskie)</label>
    <p class="pmg-hint" id="numer_h">Np. XIV.</p>
    <input type="text" id="numer" name="numer" maxlength="10" value="<?= $v('numer') ?>" required<?= blad_pola('numer', 'numer_h') ?>><?= komunikat_pola('numer') ?>
    <label for="temat">Temat</label>
    <input type="text" id="temat" name="temat" maxlength="200" value="<?= $v('temat') ?>" required<?= blad_pola('temat') ?>><?= komunikat_pola('temat') ?>
    <label for="data">Data</label>
    <input type="date" id="data" name="data" value="<?= $v('data') ?>" required<?= blad_pola('data') ?>><?= komunikat_pola('data') ?>
    <label for="miejsce">Miejsce</label>
    <input type="text" id="miejsce" name="miejsce" maxlength="200" value="<?= $v('miejsce') ?>" required<?= blad_pola('miejsce') ?>><?= komunikat_pola('miejsce') ?>
    <label for="opis">Opis pod nagłówkiem strony <span class="pmg-opt">(opcjonalnie)</span></label>
    <p class="pmg-hint" id="opis_h">1–3 zdania pod tematem na stronie tej edycji. Puste pole = zostaje tekst wpisany w stronie. Maks. 600 znaków.</p>
    <textarea id="opis" name="opis" maxlength="600" aria-describedby="opis_h" data-pmg-licznik><?= $v('opis') ?></textarea>
    <?php if (($editEdycja['status'] ?? '') === 'biezaca'): ?>
      <div class="pmg-alert pmg-alert--info" role="status"><?= pmg_ikona('info') ?><p>To jest bieżąca edycja — status zmienia się przyciskiem „Ustaw jako bieżącą” na liście edycji, nie w tym formularzu.</p></div>
    <?php else: ?>
      <fieldset class="pmg-fieldset" aria-describedby="status_h">
        <legend class="pmg-legend">Status</legend>
        <p class="pmg-hint" id="status_h">Szkic: edycji nie widać na stronie. Zakończona: edycja ma swoją stronę, ale nie jest już pokazywana jako aktualna. Bieżącą edycję (jedną naraz, pokazywaną na stronie PM Session) ustawiasz przyciskiem na liście edycji.</p>
        <div class="pmg-options">
          <label class="pmg-option"><input type="radio" name="status" value="szkic"<?= ($editEdycja['status'] ?? 'szkic') !== 'zakonczona' ? ' checked' : '' ?>><span>Szkic</span></label>
          <label class="pmg-option"><input type="radio" name="status" value="zakonczona"<?= ($editEdycja['status'] ?? '') === 'zakonczona' ? ' checked' : '' ?>><span>Zakończona</span></label>
        </div>
      </fieldset>
    <?php endif; ?>
    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=pmsession">Anuluj</a>
    </div>
  </form>
  <?php if ($editEdycja['id'] && ($editEdycja['status'] ?? '') !== 'biezaca'): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="edycja_usun"><input type="hidden" name="id" value="<?= (int) $editEdycja['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Najpierw usuń prelegentów i harmonogram tej edycji. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tę edycję na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń edycję</button>
    </form>
  <?php endif; ?>

<?php elseif ($edycjaWidok !== null): ?>
  <div class="pmg-card" id="prelegenci">
    <div class="pmg-card__head">
      <h2 class="pmg-h2">Prelegenci</h2>
      <a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=pmsession&e=<?= $eid ?>&p=nowy">+ Nowy prelegent</a>
    </div>
    <div class="pmg-table-wrap pmg-table-wrap--flush">
      <table class="pmg-table pmg-table--klikalna">
        <caption class="pmg-vh">Prelegenci — Edycja <?= h($edycjaWidok['numer']) ?></caption>
        <thead><tr><th scope="col">Imię i nazwisko</th><th scope="col">Temat</th><th scope="col">Kolejność</th></tr></thead>
        <tbody>
        <?php $stP = pmg_db()->prepare('SELECT * FROM pmg_prelegenci WHERE edycja_id = ? ORDER BY kolejnosc, id'); $stP->execute([$eid]); $prelegenci = $stP->fetchAll(); ?>
        <?php foreach ($prelegenci as $i => $p): $wylG = $i === 0; $wylD = $i === count($prelegenci) - 1; ?>
          <tr id="wiersz-p<?= (int) $p['id'] ?>">
            <td class="pmg-td-main" data-label="Imię i nazwisko"><a class="pmg-row-link" href="?m=pmsession&e=<?= $eid ?>&p=<?= (int) $p['id'] ?>"><?= h($p['imie_nazwisko']) ?></a></td>
            <td data-label="Temat"><?= h($p['temat']) ?></td>
            <td class="pmg-td-actions" data-label="Kolejność">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="edycja_id" value="<?= $eid ?>">
                <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="prelegent_gora"<?= $wylG ? ' disabled' : '' ?><?= fokus_strzalki('p' . (int) $p['id'], 'gora', $wylG, $wylD) ?> aria-label="Przesuń wyżej: <?= h($p['imie_nazwisko']) ?>"><span aria-hidden="true">↑</span></button>
                <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="prelegent_dol"<?= $wylD ? ' disabled' : '' ?><?= fokus_strzalki('p' . (int) $p['id'], 'dol', $wylG, $wylD) ?> aria-label="Przesuń niżej: <?= h($p['imie_nazwisko']) ?>"><span aria-hidden="true">↓</span></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$prelegenci): ?>
          <tr><td colspan="3" class="pmg-empty">Brak prelegentów.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=pmsession&e=<?= $eid ?>&p=nowy">+ Dodaj prelegenta</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="pmg-card" id="harmonogram">
    <div class="pmg-card__head">
      <h2 class="pmg-h2">Harmonogram</h2>
      <a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=pmsession&e=<?= $eid ?>&h=nowy">+ Punkt harmonogramu</a>
    </div>
    <div class="pmg-table-wrap pmg-table-wrap--flush">
      <table class="pmg-table pmg-table--klikalna">
        <caption class="pmg-vh">Harmonogram — Edycja <?= h($edycjaWidok['numer']) ?></caption>
        <thead><tr><th scope="col">Godzina</th><th scope="col">Tytuł</th><th scope="col">Prelegent</th></tr></thead>
        <tbody>
        <?php $stH = pmg_db()->prepare('SELECT * FROM pmg_harmonogram WHERE edycja_id = ? ORDER BY godzina, id'); $stH->execute([$eid]); $harmonogram = $stH->fetchAll(); ?>
        <?php foreach ($harmonogram as $hh): ?>
          <tr>
            <td class="pmg-td-main" data-label="Godzina"><a class="pmg-row-link" href="?m=pmsession&e=<?= $eid ?>&h=<?= (int) $hh['id'] ?>"><?= h($fmtGodzina($hh['godzina'])) ?></a></td>
            <td data-label="Tytuł"><?= h($hh['tytul']) ?><?php if ($hh['znacznik'] !== ''): ?> <span class="pmg-chip pmg-chip--neutral"><?= h($hh['znacznik']) ?></span><?php endif; ?></td>
            <td data-label="Prelegent"><?= h($hh['prelegent']) ?: '—' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$harmonogram): ?>
          <tr><td colspan="3" class="pmg-empty">Brak punktów harmonogramu.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=pmsession&e=<?= $eid ?>&h=nowy">+ Dodaj punkt</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <?php pmg_import_blok('pmsession'); ?>
  <div class="pmg-card">
    <div class="pmg-card__head"><h2 class="pmg-h2">Edycje</h2></div>
    <div class="pmg-table-wrap pmg-table-wrap--flush">
      <table class="pmg-table pmg-table--klikalna">
        <caption class="pmg-vh">Edycje PM Session</caption>
        <thead><tr><th scope="col">Numer</th><th scope="col">Temat</th><th scope="col" class="pmg-num">Data</th><th scope="col">Status</th><th scope="col" class="pmg-num">Prelegenci</th><th scope="col" class="pmg-num">Harmonogram</th><th scope="col">Akcje</th></tr></thead>
        <tbody>
        <?php $edycjeLista = pmg_db()->query('SELECT e.*, (SELECT COUNT(*) FROM pmg_prelegenci p WHERE p.edycja_id = e.id) AS liczba_prelegentow, (SELECT COUNT(*) FROM pmg_harmonogram hh WHERE hh.edycja_id = e.id) AS liczba_punktow FROM pmg_edycje e ORDER BY e.data DESC, e.id DESC')->fetchAll(); ?>
        <?php foreach ($edycjeLista as $e): ?>
          <tr>
            <td class="pmg-td-main" data-label="Numer"><a class="pmg-row-link" href="?m=pmsession&e=<?= (int) $e['id'] ?>">Edycja <?= h($e['numer']) ?></a></td>
            <td data-label="Temat"><?= h($e['temat']) ?></td>
            <td class="pmg-num" data-label="Data"><time datetime="<?= h($e['data']) ?>"><?= h($e['data']) ?></time></td>
            <td data-label="Status">
              <?php $sw = $pmgStatusWariant[$e['status']] ?? 'neutral'; $st_ = $pmgStatusTekst[$e['status']] ?? 'Szkic'; ?>
              <span class="pmg-chip pmg-chip--<?= $sw ?>"><?= $st_ ?></span>
            </td>
            <td class="pmg-num" data-label="Prelegenci"><?= (int) $e['liczba_prelegentow'] ?></td>
            <td class="pmg-num" data-label="Harmonogram"><?= (int) $e['liczba_punktow'] ?></td>
            <td class="pmg-td-actions" data-label="Akcje">
              <a class="pmg-btn pmg-btn--text pmg-btn--sm" href="?m=pmsession&e=<?= (int) $e['id'] ?>#prelegenci">Prelegenci<span class="pmg-vh"> — edycja <?= h($e['numer']) ?></span></a>
              <a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=pmsession&e=<?= (int) $e['id'] ?>&p=nowy">+ Nowy prelegent<span class="pmg-vh"> — edycja <?= h($e['numer']) ?></span></a>
              <a class="pmg-btn pmg-btn--text pmg-btn--sm" href="?m=pmsession&e=<?= (int) $e['id'] ?>">Zobacz<span class="pmg-vh"> edycję <?= h($e['numer']) ?></span></a>
              <a class="pmg-btn pmg-btn--text pmg-btn--sm" href="?m=pmsession&edycja=<?= (int) $e['id'] ?>">Edytuj<span class="pmg-vh"> edycję <?= h($e['numer']) ?></span></a>
              <?php if ($e['status'] !== 'biezaca'): ?>
                <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="edycja_biezaca"><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit">Ustaw jako bieżącą</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$edycjeLista): ?>
          <tr><td colspan="7" class="pmg-empty">Nie ma jeszcze żadnej edycji. Najpierw dodaj edycję (numer, temat, data, miejsce), potem w jej widoku dodasz prelegentów i harmonogram.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=pmsession&edycja=nowa">+ Dodaj edycję</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
  $stL = pmg_db()->prepare('SELECT klucz, wartosc FROM pmg_ustawienia WHERE klucz IN (' . implode(',', array_fill(0, count(PMS_LICZBY), '?')) . ')');
  $stL->execute(array_keys(PMS_LICZBY));
  $liczby = array_fill_keys(array_keys(PMS_LICZBY), '');
  foreach ($stL->fetchAll() as $r) $liczby[$r['klucz']] = $r['wartosc'];
  ?>
  <form class="pmg-card" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="liczby_zapisz">
    <div class="pmg-card__head"><h2 class="pmg-h2">PM Session w liczbach</h2></div>
    <p class="pmg-hint">Suma wszystkich edycji (nie tylko bieżącej). Puste pole = strona pokazuje obecną liczbę.</p>
    <div class="pmg-fields-grid">
      <?php foreach (PMS_LICZBY as $k => $etykieta): ?>
        <div class="pmg-field">
          <label for="<?= $k ?>"><?= h($etykieta) ?></label>
          <input type="text" inputmode="numeric" id="<?= $k ?>" name="<?= $k ?>" maxlength="6" value="<?= h($wejscie[$k] ?? $liczby[$k]) ?>" class="pmg-num-input"<?= blad_pola($k) ?>><?= komunikat_pola($k) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
    </div>
  </form>
<?php endif; ?>
