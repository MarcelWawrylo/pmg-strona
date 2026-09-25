<?php
// Moduł PM Session: edycje, prelegenci, harmonogram, liczby "PM Session w liczbach". Wołany wyłącznie z index.php (?m=pmsession).
defined('PMG_PANEL') || exit;

const PMS_LICZBY = ['pms_edycji' => 'Edycji', 'pms_prelekcji' => 'Prelekcji', 'pms_prelegentow' => 'Prelegentów', 'pms_uczestnikow' => 'Uczestników', 'pms_warsztatow' => 'Warsztatów', 'pms_symulacji' => 'Symulacji'];

// Jedyne miejsce ustawiania statusu 'biezaca' — w transakcji: obecna bieżąca -> zakończona, wybrana -> bieżąca.
function ustaw_biezaca($id)
{
    $pdo = pmg_db();
    $pdo->beginTransaction();
    $pdo->exec("UPDATE pmg_edycje SET status = 'zakonczona' WHERE status = 'biezaca'");
    $pdo->prepare("UPDATE pmg_edycje SET status = 'biezaca' WHERE id = ?")->execute([$id]);
    $pdo->commit();
    loguj('pmsession', 'biezaca', $id);
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
        ustaw_biezaca($id);
        $_SESSION['flash'] = 'Ustawiono jako bieżącą edycję.';
        go('?m=pmsession');

    } elseif ($action === 'edycja_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT status FROM pmg_edycje WHERE id = ?');
        $st->execute([$id]);
        $status = $st->fetchColumn();
        if ($status === false) {
            $error = 'Nie znaleziono edycji.';
        } elseif ($status === 'biezaca') {
            $error = 'Nie można usunąć bieżącej edycji.';
        } else {
            try {
                pmg_db()->prepare('DELETE FROM pmg_edycje WHERE id = ?')->execute([$id]);
                loguj('pmsession', 'usuniecie', $id);
                $_SESSION['flash'] = 'Edycja usunięta.';
                go('?m=pmsession');
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

        if (!preg_match('~^[IVXLC]+$~', $f['numer'])) {
            $error = 'Numer edycji: użyj tylko cyfr rzymskich (I, V, X, L, C), np. XIV.';
        } elseif ($f['temat'] === '') {
            $error = 'Podaj temat edycji.';
        } elseif (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['data'])) {
            $error = 'Podaj poprawną datę.';
        } elseif ($f['miejsce'] === '') {
            $error = 'Podaj miejsce.';
        } else {
            try {
                if ($id) {
                    pmg_db()->prepare('UPDATE pmg_edycje SET numer=?, temat=?, data=?, miejsce=?, status=? WHERE id=?')
                        ->execute([$f['numer'], $f['temat'], $f['data'], $f['miejsce'], $status, $id]);
                    loguj('pmsession', 'edycja', $id);
                } else {
                    pmg_db()->prepare('INSERT INTO pmg_edycje (numer, temat, data, miejsce, status) VALUES (?,?,?,?,?)')
                        ->execute([$f['numer'], $f['temat'], $f['data'], $f['miejsce'], $status]);
                    $id = (int) pmg_db()->lastInsertId();
                    loguj('pmsession', 'dodanie', $id);
                }
                $_SESSION['flash'] = 'Zapisano.';
                go('?m=pmsession');
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Edycja o tym numerze już istnieje.' : 'Błąd zapisu.';
            }
        }
        if ($error !== '') $editEdycja = array_merge($f, ['id' => $id, 'status' => $obecnyStatus ?: $status]);

    // ---------- Prelegenci ----------
    } elseif ($action === 'prelegent_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $st = pmg_db()->prepare('SELECT zdjecie FROM pmg_prelegenci WHERE id = ?');
        $st->execute([$id]);
        $img = $st->fetchColumn();
        pmg_db()->prepare('DELETE FROM pmg_prelegenci WHERE id = ?')->execute([$id]);
        drop_image($img ?: null);
        loguj('pmsession', 'usuniecie', $id);
        $_SESSION['flash'] = 'Prelegent usunięty.';
        go('?m=pmsession&e=' . $edycjaId);

    } elseif ($action === 'prelegent_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $f = [
            'imie_nazwisko' => mb_substr(trim((string) ($_POST['imie_nazwisko'] ?? '')), 0, 100),
            'temat' => mb_substr(trim((string) ($_POST['temat'] ?? '')), 0, 300),
            'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 1500),
            'notatka' => mb_substr(trim((string) ($_POST['notatka'] ?? '')), 0, 200),
            'linkedin' => mb_substr(trim((string) ($_POST['linkedin'] ?? '')), 0, 200),
            'zdjecie_alt' => mb_substr(trim((string) ($_POST['zdjecie_alt'] ?? '')), 0, 200),
            'kolejnosc' => (int) ($_POST['kolejnosc'] ?? 0),
        ];
        $stE = pmg_db()->prepare('SELECT 1 FROM pmg_edycje WHERE id = ?');
        $stE->execute([$edycjaId]);
        $old = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_prelegenci WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        if (!$stE->fetchColumn()) {
            $error = 'Nieprawidłowa edycja.';
        } elseif ($f['imie_nazwisko'] === '') {
            $error = 'Podaj imię i nazwisko.';
        } elseif ($f['temat'] === '') {
            $error = 'Podaj temat wystąpienia.';
        } elseif (!url_ok($f['linkedin'])) {
            $error = 'LinkedIn: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        }
        if ($error === '') {
            try {
                $stareZdjecie = $old['zdjecie'] ?? null;
                $f['zdjecie'] = zdjecie('pmsession', $stareZdjecie);
                if ($id && $old) {
                    $st = pmg_db()->prepare('UPDATE pmg_prelegenci SET imie_nazwisko=?, temat=?, bio=?, notatka=?, zdjecie=?, zdjecie_alt=?, linkedin=?, kolejnosc=? WHERE id=?');
                    $st->execute([$f['imie_nazwisko'], $f['temat'], $f['bio'], $f['notatka'], $f['zdjecie'], $f['zdjecie_alt'], $f['linkedin'], $f['kolejnosc'], $id]);
                    if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                    loguj('pmsession', 'edycja', $id);
                } else {
                    $st = pmg_db()->prepare('INSERT INTO pmg_prelegenci (edycja_id, imie_nazwisko, temat, bio, notatka, zdjecie, zdjecie_alt, linkedin, kolejnosc) VALUES (?,?,?,?,?,?,?,?,?)');
                    $st->execute([$edycjaId, $f['imie_nazwisko'], $f['temat'], $f['bio'], $f['notatka'], $f['zdjecie'], $f['zdjecie_alt'], $f['linkedin'], $f['kolejnosc']]);
                    $id = (int) pmg_db()->lastInsertId();
                    loguj('pmsession', 'dodanie', $id);
                }
                $_SESSION['flash'] = 'Zapisano.';
                go('?m=pmsession&e=' . $edycjaId);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
        if ($error !== '') $editPrelegent = array_merge($old ?: [], $f, ['id' => $id, 'edycja_id' => $edycjaId]);

    // ---------- Harmonogram ----------
    } elseif ($action === 'harmonogram_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        pmg_db()->prepare('DELETE FROM pmg_harmonogram WHERE id = ?')->execute([$id]);
        loguj('pmsession', 'usuniecie', $id);
        $_SESSION['flash'] = 'Punkt harmonogramu usunięty.';
        go('?m=pmsession&e=' . $edycjaId);

    } elseif ($action === 'harmonogram_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $edycjaId = (int) ($_POST['edycja_id'] ?? 0);
        $f = [
            'godzina' => (string) ($_POST['godzina'] ?? ''),
            'tytul' => mb_substr(trim((string) ($_POST['tytul'] ?? '')), 0, 300),
            'prelegent' => mb_substr(trim((string) ($_POST['prelegent'] ?? '')), 0, 150),
        ];
        $stE = pmg_db()->prepare('SELECT 1 FROM pmg_edycje WHERE id = ?');
        $stE->execute([$edycjaId]);
        if (!$stE->fetchColumn()) {
            $error = 'Nieprawidłowa edycja.';
        } elseif (!preg_match('~^([01]\d|2[0-3]):[0-5]\d$~', $f['godzina'])) {
            $error = 'Podaj poprawną godzinę.';
        } elseif ($f['tytul'] === '') {
            $error = 'Podaj tytuł punktu harmonogramu.';
        } else {
            if ($id) {
                pmg_db()->prepare('UPDATE pmg_harmonogram SET godzina=?, tytul=?, prelegent=? WHERE id=?')
                    ->execute([$f['godzina'], $f['tytul'], $f['prelegent'], $id]);
                loguj('pmsession', 'edycja', $id);
            } else {
                pmg_db()->prepare('INSERT INTO pmg_harmonogram (edycja_id, godzina, tytul, prelegent) VALUES (?,?,?,?)')
                    ->execute([$edycjaId, $f['godzina'], $f['tytul'], $f['prelegent']]);
                $id = (int) pmg_db()->lastInsertId();
                loguj('pmsession', 'dodanie', $id);
            }
            $_SESSION['flash'] = 'Zapisano.';
            go('?m=pmsession&e=' . $edycjaId);
        }
        if ($error !== '') $editHarmonogram = array_merge($f, ['id' => $id, 'edycja_id' => $edycjaId]);

    // ---------- Liczby "PM Session w liczbach" ----------
    } elseif ($action === 'liczby_zapisz') {
        $wejscie = [];
        foreach (PMS_LICZBY as $k => $etykieta) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v !== '' && !preg_match('~^\d{1,6}$~', $v)) {
                $error = $etykieta . ': podaj liczbę (do 6 cyfr) albo zostaw puste pole.';
                break;
            }
            $wejscie[$k] = $v;
        }
        if ($error === '') {
            $pdo = pmg_db();
            $pdo->beginTransaction();
            $upd = $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?,?)');
            foreach ($wejscie as $k => $v) $upd->execute([$k, $v]);
            $pdo->commit();
            loguj('pmsession', 'edycja');
            $_SESSION['flash'] = 'Zapisano. Zmiany widać na stronie w ciągu 5 minut.';
            go('?m=pmsession');
        }
    }
}

// ---------- Widoki z GET, gdy nie ma już $edit z POST powyżej ----------
if ($editEdycja === null && isset($_GET['edycja'])) {
    if ($_GET['edycja'] === 'nowa') {
        $editEdycja = ['id' => 0, 'numer' => '', 'temat' => '', 'data' => '', 'miejsce' => '', 'status' => 'szkic'];
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
            $editPrelegent = ['id' => 0, 'edycja_id' => $eid, 'imie_nazwisko' => '', 'temat' => '', 'bio' => '', 'notatka' => '', 'linkedin' => '', 'zdjecie_alt' => '', 'kolejnosc' => 0];
        } else {
            $st = pmg_db()->prepare('SELECT * FROM pmg_prelegenci WHERE id = ? AND edycja_id = ?');
            $st->execute([(int) $_GET['p'], $eid]);
            $editPrelegent = $st->fetch() ?: null;
        }
    }
    if ($editHarmonogram === null && isset($_GET['h'])) {
        if ($_GET['h'] === 'nowy') {
            $editHarmonogram = ['id' => 0, 'edycja_id' => $eid, 'godzina' => '', 'tytul' => '', 'prelegent' => ''];
        } else {
            $st = pmg_db()->prepare('SELECT * FROM pmg_harmonogram WHERE id = ? AND edycja_id = ?');
            $st->execute([(int) $_GET['h'], $eid]);
            $editHarmonogram = $st->fetch() ?: null;
        }
    }
}

$fmtGodzina = function ($g) { return substr((string) $g, 0, 5); };
?>
<h2>PM Session</h2>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($editPrelegent !== null): $v = function ($k) use (&$editPrelegent) { return h($editPrelegent[$k] ?? ''); }; ?>
  <form class="box" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="prelegent_zapisz"><input type="hidden" name="id" value="<?= (int) $editPrelegent['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editPrelegent['edycja_id'] ?>">
    <h3><?= $editPrelegent['id'] ? 'Edycja prelegenta' : 'Nowy prelegent' ?></h3>
    <label for="imie_nazwisko">Imię i nazwisko</label>
    <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" value="<?= $v('imie_nazwisko') ?>" required autofocus>
    <label for="temat">Temat wystąpienia</label>
    <p class="hint" id="temat_h">Np. Prelekcja: „Tytuł” albo Warsztat: „Tytuł”.</p>
    <input type="text" id="temat" name="temat" maxlength="300" value="<?= $v('temat') ?>" required aria-describedby="temat_h">
    <label for="notatka">Notatka (opcjonalnie)</label>
    <p class="hint" id="notatka_h">Np. „Wspólny warsztat z Anną Nowak”. Widoczna nad tematem na stronie.</p>
    <input type="text" id="notatka" name="notatka" maxlength="200" value="<?= $v('notatka') ?>" aria-describedby="notatka_h">
    <label for="bio">Biogram</label>
    <p class="hint" id="bio_h">Maksymalnie 1500 znaków.</p>
    <textarea id="bio" name="bio" maxlength="1500" aria-describedby="bio_h"><?= $v('bio') ?></textarea>
    <label for="linkedin">LinkedIn</label>
    <p class="hint" id="linkedin_h">Pełny adres zaczynający się od https://. Pole opcjonalne.</p>
    <input type="text" id="linkedin" name="linkedin" maxlength="200" value="<?= $v('linkedin') ?>" aria-describedby="linkedin_h">
    <label for="zdjecie">Zdjęcie 1:1 — kwadrat (JPG, PNG albo WebP)</label>
    <p class="hint" id="zdjecie_h">Opcjonalne.</p>
    <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp" aria-describedby="zdjecie_h">
    <?php if (!empty($editPrelegent['zdjecie'])): ?><img class="preview" src="../<?= h($editPrelegent['zdjecie']) ?>" alt=""><p class="hint">Wgranie nowego pliku zastąpi to zdjęcie.</p><?php endif; ?>
    <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
    <p class="hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
    <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>" aria-describedby="zdjecie_alt_h">
    <label for="kolejnosc">Kolejność</label>
    <p class="hint" id="kolejnosc_h">Mniejsza liczba = wyżej na liście.</p>
    <input type="number" id="kolejnosc" name="kolejnosc" value="<?= (int) ($editPrelegent['kolejnosc'] ?? 0) ?>" aria-describedby="kolejnosc_h">
    <div class="row"><button type="submit">Zapisz</button><a class="btn btn--light" href="?m=pmsession&e=<?= (int) $editPrelegent['edycja_id'] ?>">Anuluj</a></div>
  </form>
  <?php if ($editPrelegent['id']): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="prelegent_usun"><input type="hidden" name="id" value="<?= (int) $editPrelegent['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editPrelegent['edycja_id'] ?>">
      <div class="row"><label style="margin:0;font-weight:400"><input type="checkbox" required> Tak, usuń tego prelegenta na stałe</label><button class="btn--danger" type="submit">Usuń prelegenta</button></div>
    </form>
  <?php endif; ?>

<?php elseif ($editHarmonogram !== null): $v = function ($k) use (&$editHarmonogram) { return h($editHarmonogram[$k] ?? ''); }; ?>
  <form class="box" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="harmonogram_zapisz"><input type="hidden" name="id" value="<?= (int) $editHarmonogram['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editHarmonogram['edycja_id'] ?>">
    <h3><?= $editHarmonogram['id'] ? 'Edycja punktu harmonogramu' : 'Nowy punkt harmonogramu' ?></h3>
    <label for="godzina">Godzina</label>
    <input type="time" id="godzina" name="godzina" value="<?= $v('godzina') !== '' ? h($fmtGodzina($editHarmonogram['godzina'])) : '' ?>" required autofocus>
    <label for="tytul">Tytuł</label>
    <input type="text" id="tytul" name="tytul" maxlength="300" value="<?= $v('tytul') ?>" required>
    <label for="prelegent">Prelegent</label>
    <p class="hint" id="prelegent_h">Opcjonalne — tekst dowolny, np. Jan Kowalski albo Jan Kowalski + Anna Nowak. Zostaw puste dla punktów bez prelegenta (np. Rejestracja).</p>
    <input type="text" id="prelegent" name="prelegent" maxlength="150" value="<?= $v('prelegent') ?>" aria-describedby="prelegent_h">
    <div class="row"><button type="submit">Zapisz</button><a class="btn btn--light" href="?m=pmsession&e=<?= (int) $editHarmonogram['edycja_id'] ?>">Anuluj</a></div>
  </form>
  <?php if ($editHarmonogram['id']): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="harmonogram_usun"><input type="hidden" name="id" value="<?= (int) $editHarmonogram['id'] ?>"><input type="hidden" name="edycja_id" value="<?= (int) $editHarmonogram['edycja_id'] ?>">
      <div class="row"><label style="margin:0;font-weight:400"><input type="checkbox" required> Tak, usuń ten punkt harmonogramu na stałe</label><button class="btn--danger" type="submit">Usuń punkt</button></div>
    </form>
  <?php endif; ?>

<?php elseif ($editEdycja !== null): $v = function ($k) use (&$editEdycja) { return h($editEdycja[$k] ?? ''); }; ?>
  <form class="box" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="edycja_zapisz"><input type="hidden" name="id" value="<?= (int) $editEdycja['id'] ?>">
    <h3><?= $editEdycja['id'] ? 'Edycja edycji' : 'Nowa edycja' ?></h3>
    <label for="numer">Numer (cyfry rzymskie)</label>
    <p class="hint" id="numer_h">Np. XIV.</p>
    <input type="text" id="numer" name="numer" maxlength="10" value="<?= $v('numer') ?>" required aria-describedby="numer_h" autofocus>
    <label for="temat">Temat</label>
    <input type="text" id="temat" name="temat" maxlength="200" value="<?= $v('temat') ?>" required>
    <label for="data">Data</label>
    <input type="date" id="data" name="data" value="<?= $v('data') ?>" required>
    <label for="miejsce">Miejsce</label>
    <input type="text" id="miejsce" name="miejsce" maxlength="200" value="<?= $v('miejsce') ?>" required>
    <?php if (($editEdycja['status'] ?? '') === 'biezaca'): ?>
      <p class="hint">To jest bieżąca edycja — status zmienia się przyciskiem „Ustaw jako bieżącą” na liście edycji, nie w tym formularzu.</p>
    <?php else: ?>
      <fieldset>
        <legend>Status</legend>
        <label><input type="radio" name="status" value="szkic"<?= ($editEdycja['status'] ?? 'szkic') !== 'zakonczona' ? ' checked' : '' ?>> Szkic</label>
        <label><input type="radio" name="status" value="zakonczona"<?= ($editEdycja['status'] ?? '') === 'zakonczona' ? ' checked' : '' ?>> Zakończona</label>
      </fieldset>
    <?php endif; ?>
    <div class="row"><button type="submit">Zapisz</button><a class="btn btn--light" href="?m=pmsession">Anuluj</a></div>
  </form>
  <?php if ($editEdycja['id'] && ($editEdycja['status'] ?? '') !== 'biezaca'): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="edycja_usun"><input type="hidden" name="id" value="<?= (int) $editEdycja['id'] ?>">
      <div class="row"><label style="margin:0;font-weight:400"><input type="checkbox" required> Tak, usuń tę edycję na stałe</label><button class="btn--danger" type="submit">Usuń edycję</button></div>
    </form>
  <?php endif; ?>

<?php elseif ($edycjaWidok !== null): ?>
  <p><a href="?m=pmsession">← Wszystkie edycje</a></p>
  <div class="box">
    <div class="row" style="margin-top:0;justify-content:space-between">
      <h3 style="margin:0">Edycja <?= h($edycjaWidok['numer']) ?> <span class="hint" style="display:inline"><?= $edycjaWidok['status'] === 'biezaca' ? 'Bieżąca' : ($edycjaWidok['status'] === 'zakonczona' ? 'Zakończona' : 'Szkic') ?></span></h3>
      <a href="?m=pmsession&edycja=<?= (int) $edycjaWidok['id'] ?>">Edytuj edycję</a>
    </div>
    <p class="hint"><?= h($edycjaWidok['temat']) ?> · <?= h($edycjaWidok['data']) ?> · <?= h($edycjaWidok['miejsce']) ?></p>
  </div>

  <div class="row" style="margin: 0 0 18px">
    <a class="btn" href="?m=pmsession&e=<?= $eid ?>&p=nowy">+ Nowy prelegent</a>
    <a class="btn btn--light" href="?m=pmsession&e=<?= $eid ?>&h=nowy">+ Punkt harmonogramu</a>
  </div>

  <h3>Prelegenci</h3>
  <div class="box tabela">
    <table>
      <thead><tr><th>Imię i nazwisko</th><th>Temat</th><th>Kolejność</th></tr></thead>
      <tbody>
      <?php $stP = pmg_db()->prepare('SELECT * FROM pmg_prelegenci WHERE edycja_id = ? ORDER BY kolejnosc, id'); $stP->execute([$eid]); $prelegenci = $stP->fetchAll(); ?>
      <?php foreach ($prelegenci as $p): ?>
        <tr>
          <td><a href="?m=pmsession&e=<?= $eid ?>&p=<?= (int) $p['id'] ?>"><?= h($p['imie_nazwisko']) ?></a></td>
          <td><?= h($p['temat']) ?></td>
          <td><?= (int) $p['kolejnosc'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$prelegenci): ?><tr><td colspan="3">Brak prelegentów.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <h3>Harmonogram</h3>
  <div class="box tabela">
    <table>
      <thead><tr><th>Godzina</th><th>Tytuł</th><th>Prelegent</th></tr></thead>
      <tbody>
      <?php $stH = pmg_db()->prepare('SELECT * FROM pmg_harmonogram WHERE edycja_id = ? ORDER BY godzina, id'); $stH->execute([$eid]); $harmonogram = $stH->fetchAll(); ?>
      <?php foreach ($harmonogram as $hh): ?>
        <tr>
          <td><a href="?m=pmsession&e=<?= $eid ?>&h=<?= (int) $hh['id'] ?>"><?= h($fmtGodzina($hh['godzina'])) ?></a></td>
          <td><?= h($hh['tytul']) ?></td>
          <td><?= h($hh['prelegent']) ?: '—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$harmonogram): ?><tr><td colspan="3">Brak punktów harmonogramu.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <div class="row" style="margin: 0 0 18px">
    <a class="btn" href="?m=pmsession&edycja=nowa">+ Nowa edycja</a>
    <a class="btn btn--light" href="../pm-session.html" target="_blank" rel="noopener">Zobacz stronę</a>
  </div>

  <div class="box tabela">
    <table>
      <thead><tr><th>Numer</th><th>Temat</th><th>Data</th><th>Status</th><th>Akcje</th></tr></thead>
      <tbody>
      <?php $edycjeLista = pmg_db()->query('SELECT * FROM pmg_edycje ORDER BY data DESC, id DESC')->fetchAll(); ?>
      <?php foreach ($edycjeLista as $e): ?>
        <tr>
          <td><a href="?m=pmsession&e=<?= (int) $e['id'] ?>"><?= h($e['numer']) ?></a></td>
          <td><?= h($e['temat']) ?></td>
          <td><?= h($e['data']) ?></td>
          <td><?= $e['status'] === 'biezaca' ? 'Bieżąca' : ($e['status'] === 'zakonczona' ? 'Zakończona' : 'Szkic') ?></td>
          <td>
            <a href="?m=pmsession&e=<?= (int) $e['id'] ?>">Zobacz</a>
            <a href="?m=pmsession&edycja=<?= (int) $e['id'] ?>">Edytuj</a>
            <?php if ($e['status'] !== 'biezaca'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="edycja_biezaca"><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><button class="btn--light" type="submit">Ustaw jako bieżącą</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$edycjeLista): ?><tr><td colspan="5">Brak edycji — dodaj pierwszą przyciskiem powyżej.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
  $stL = pmg_db()->prepare('SELECT klucz, wartosc FROM pmg_ustawienia WHERE klucz IN (' . implode(',', array_fill(0, count(PMS_LICZBY), '?')) . ')');
  $stL->execute(array_keys(PMS_LICZBY));
  $liczby = array_fill_keys(array_keys(PMS_LICZBY), '');
  foreach ($stL->fetchAll() as $r) $liczby[$r['klucz']] = $r['wartosc'];
  ?>
  <form class="box" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="liczby_zapisz">
    <h3>PM Session w liczbach</h3>
    <p class="hint">Suma wszystkich edycji (nie tylko bieżącej). Puste pole = strona pokazuje obecną liczbę.</p>
    <?php foreach (PMS_LICZBY as $k => $etykieta): ?>
      <label for="<?= $k ?>"><?= h($etykieta) ?></label>
      <input type="text" inputmode="numeric" id="<?= $k ?>" name="<?= $k ?>" maxlength="6" value="<?= h($liczby[$k]) ?>">
    <?php endforeach; ?>
    <div class="row"><button type="submit">Zapisz</button></div>
  </form>
<?php endif; ?>
