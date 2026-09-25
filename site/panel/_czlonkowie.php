<?php
// Moduł Członkowie: sekcje koła + osoby (zarząd i sekcje). Wołany wyłącznie z index.php (?m=czlonkowie).
defined('PMG_PANEL') || exit;

$editSekcja = null;
$editOsoba = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    // ---------- Sekcje ----------
    if ($action === 'sekcja_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            pmg_db()->prepare('DELETE FROM pmg_sekcje WHERE id = ?')->execute([$id]);
            loguj('czlonkowie', 'usuniecie', $id);
            $_SESSION['flash'] = 'Sekcja usunięta.';
            go('?m=czlonkowie');
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? 'W sekcji są osoby — przenieś je najpierw.' : 'Błąd usuwania.';
        }
    } elseif ($action === 'sekcja_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [
            'nazwa' => mb_substr(trim((string) ($_POST['nazwa'] ?? '')), 0, 60),
            'kolor' => array_key_exists((string) ($_POST['kolor'] ?? ''), KOLORY) ? $_POST['kolor'] : 'pink',
            'opis' => mb_substr(trim((string) ($_POST['opis'] ?? '')), 0, 300),
            'kolejnosc' => (int) ($_POST['kolejnosc'] ?? 0),
        ];
        if ($f['nazwa'] === '') {
            $error = 'Podaj nazwę sekcji.';
        } else {
            if ($id) {
                pmg_db()->prepare('UPDATE pmg_sekcje SET nazwa=?, kolor=?, opis=?, kolejnosc=? WHERE id=?')
                    ->execute([$f['nazwa'], $f['kolor'], $f['opis'], $f['kolejnosc'], $id]);
                loguj('czlonkowie', 'edycja', $id);
            } else {
                pmg_db()->prepare('INSERT INTO pmg_sekcje (nazwa, kolor, opis, kolejnosc) VALUES (?,?,?,?)')
                    ->execute([$f['nazwa'], $f['kolor'], $f['opis'], $f['kolejnosc']]);
                $id = (int) pmg_db()->lastInsertId();
                loguj('czlonkowie', 'dodanie', $id);
            }
            $_SESSION['flash'] = 'Zapisano.';
            go('?m=czlonkowie');
        }
        if ($error !== '') $editSekcja = array_merge($f, ['id' => $id]);

    // ---------- Osoby ----------
    } elseif ($action === 'osoba_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT zdjecie FROM pmg_osoby WHERE id = ?');
        $st->execute([$id]);
        $img = $st->fetchColumn();
        pmg_db()->prepare('DELETE FROM pmg_osoby WHERE id = ?')->execute([$id]);
        drop_image($img ?: null);
        loguj('czlonkowie', 'usuniecie', $id);
        $_SESSION['flash'] = 'Osoba usunięta.';
        go('?m=czlonkowie');
    } elseif ($action === 'osoba_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $sekcjaRaw = trim((string) ($_POST['sekcja_id'] ?? ''));
        $f = [
            'imie' => mb_substr(trim((string) ($_POST['imie'] ?? '')), 0, 50),
            'nazwisko' => mb_substr(trim((string) ($_POST['nazwisko'] ?? '')), 0, 60),
            'funkcja' => mb_substr(trim((string) ($_POST['funkcja'] ?? '')), 0, 60),
            'sekcja_id' => $sekcjaRaw === '' ? null : (int) $sekcjaRaw,
            'koordynator' => empty($_POST['koordynator']) ? 0 : 1,
            'email' => trim((string) ($_POST['email'] ?? '')),
            'linkedin' => mb_substr(trim((string) ($_POST['linkedin'] ?? '')), 0, 200),
            'zdjecie_alt' => mb_substr(trim((string) ($_POST['zdjecie_alt'] ?? '')), 0, 200),
            'kolejnosc' => (int) ($_POST['kolejnosc'] ?? 0),
            'aktywna' => empty($_POST['aktywna']) ? 0 : 1,
        ];
        $old = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_osoby WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        if ($f['imie'] === '' || $f['nazwisko'] === '') {
            $error = 'Podaj imię i nazwisko.';
        } elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Podaj poprawny adres e-mail.';
        } elseif (!url_ok($f['linkedin'])) {
            $error = 'LinkedIn: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        } elseif ($f['sekcja_id'] !== null) {
            $st = pmg_db()->prepare('SELECT 1 FROM pmg_sekcje WHERE id = ?');
            $st->execute([$f['sekcja_id']]);
            if (!$st->fetchColumn()) $error = 'Nieprawidłowa sekcja.';
        }
        if ($error === '') {
            try {
                $stareZdjecie = $old['zdjecie'] ?? null;
                $f['zdjecie'] = zdjecie('czlonkowie', $stareZdjecie);
                if ($id && $old) {
                    $st = pmg_db()->prepare('UPDATE pmg_osoby SET imie=?, nazwisko=?, funkcja=?, sekcja_id=?, koordynator=?, email=?, linkedin=?, zdjecie=?, zdjecie_alt=?, kolejnosc=?, aktywna=? WHERE id=?');
                    $st->execute([$f['imie'], $f['nazwisko'], $f['funkcja'], $f['sekcja_id'], $f['koordynator'], $f['email'], $f['linkedin'], $f['zdjecie'], $f['zdjecie_alt'], $f['kolejnosc'], $f['aktywna'], $id]);
                    if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                    loguj('czlonkowie', 'edycja', $id);
                } else {
                    $st = pmg_db()->prepare('INSERT INTO pmg_osoby (imie, nazwisko, funkcja, sekcja_id, koordynator, email, linkedin, zdjecie, zdjecie_alt, kolejnosc, aktywna) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $st->execute([$f['imie'], $f['nazwisko'], $f['funkcja'], $f['sekcja_id'], $f['koordynator'], $f['email'], $f['linkedin'], $f['zdjecie'], $f['zdjecie_alt'], $f['kolejnosc'], $f['aktywna']]);
                    $id = (int) pmg_db()->lastInsertId();
                    loguj('czlonkowie', 'dodanie', $id);
                }
                $_SESSION['flash'] = 'Zapisano.';
                go('?m=czlonkowie');
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
        if ($error !== '') $editOsoba = array_merge($old ?: [], $f, ['id' => $id]);
    }
}

// ---------- Widok formularza z GET, gdy nie ma już $edit z POST powyżej ----------
if ($editSekcja === null && isset($_GET['sekcja'])) {
    if ($_GET['sekcja'] === 'nowa') {
        $editSekcja = ['id' => 0, 'nazwa' => '', 'kolor' => 'pink', 'opis' => '', 'kolejnosc' => 0];
    } else {
        $st = pmg_db()->prepare('SELECT * FROM pmg_sekcje WHERE id = ?');
        $st->execute([(int) $_GET['sekcja']]);
        $editSekcja = $st->fetch() ?: null;
    }
}
if ($editOsoba === null && isset($_GET['osoba'])) {
    if ($_GET['osoba'] === 'nowa') {
        $editOsoba = ['id' => 0, 'imie' => '', 'nazwisko' => '', 'funkcja' => '', 'sekcja_id' => '', 'koordynator' => 0, 'email' => '', 'linkedin' => '', 'zdjecie_alt' => '', 'kolejnosc' => 0, 'aktywna' => 1];
    } else {
        $st = pmg_db()->prepare('SELECT * FROM pmg_osoby WHERE id = ?');
        $st->execute([(int) $_GET['osoba']]);
        $editOsoba = $st->fetch() ?: null;
    }
}

$sekcjeLista = pmg_db()->query('SELECT * FROM pmg_sekcje ORDER BY kolejnosc, id')->fetchAll();
?>
<h2>Członkowie</h2>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($editOsoba !== null): $v = function ($k) use (&$editOsoba) { return h($editOsoba[$k] ?? ''); }; ?>
  <form class="box" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="osoba_zapisz"><input type="hidden" name="id" value="<?= (int) $editOsoba['id'] ?>">
    <h3><?= $editOsoba['id'] ? 'Edycja osoby' : 'Nowa osoba' ?></h3>
    <label for="imie">Imię</label>
    <input type="text" id="imie" name="imie" maxlength="50" value="<?= $v('imie') ?>" required autofocus>
    <label for="nazwisko">Nazwisko</label>
    <input type="text" id="nazwisko" name="nazwisko" maxlength="60" value="<?= $v('nazwisko') ?>" required>
    <label for="funkcja">Funkcja</label>
    <p class="hint" id="funkcja_h">Np. Prezes, Koordynator, Koordynatorka, Członek.</p>
    <input type="text" id="funkcja" name="funkcja" maxlength="60" value="<?= $v('funkcja') ?>" aria-describedby="funkcja_h">
    <label for="sekcja_id">Sekcja</label>
    <select id="sekcja_id" name="sekcja_id">
      <option value=""<?= ($editOsoba['sekcja_id'] ?? '') === '' ? ' selected' : '' ?>>Zarząd</option>
      <?php foreach ($sekcjeLista as $s): ?>
        <option value="<?= (int) $s['id'] ?>"<?= (string) ($editOsoba['sekcja_id'] ?? '') === (string) $s['id'] ? ' selected' : '' ?>><?= h($s['nazwa']) ?></option>
      <?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="koordynator" value="1"<?= !empty($editOsoba['koordynator']) ? ' checked' : '' ?>> Koordynator/-ka sekcji</label>
    <label for="email">E-mail</label>
    <p class="hint" id="email_h">Adres będzie widoczny na stronie O nas.</p>
    <input type="email" id="email" name="email" maxlength="150" value="<?= $v('email') ?>" required aria-describedby="email_h">
    <label for="linkedin">LinkedIn</label>
    <p class="hint" id="linkedin_h">Pełny adres zaczynający się od https://. Pole opcjonalne.</p>
    <input type="text" id="linkedin" name="linkedin" maxlength="200" value="<?= $v('linkedin') ?>" aria-describedby="linkedin_h">
    <label for="zdjecie">Zdjęcie 1:1 — kwadrat (JPG, PNG albo WebP)</label>
    <p class="hint" id="zdjecie_h">Opcjonalne. Obecnie niewidoczne na stronie (brak miejsca w projekcie graficznym „O nas”) — zapisujemy je na zapas.</p>
    <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp" aria-describedby="zdjecie_h">
    <?php if (!empty($editOsoba['zdjecie'])): ?><img class="preview" src="../<?= h($editOsoba['zdjecie']) ?>" alt=""><p class="hint">Wgranie nowego pliku zastąpi to zdjęcie.</p><?php endif; ?>
    <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
    <p class="hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
    <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>" aria-describedby="zdjecie_alt_h">
    <label for="kolejnosc">Kolejność</label>
    <p class="hint" id="kolejnosc_h">Mniejsza liczba = wyżej na liście.</p>
    <input type="number" id="kolejnosc" name="kolejnosc" value="<?= (int) ($editOsoba['kolejnosc'] ?? 0) ?>" aria-describedby="kolejnosc_h">
    <label><input type="checkbox" name="aktywna" value="1"<?= ($editOsoba['id'] ?? 0) === 0 || !empty($editOsoba['aktywna']) ? ' checked' : '' ?>> Aktywna</label>
    <p class="hint">Odznacz, żeby ukryć osobę na stronie bez usuwania.</p>
    <div class="row"><button type="submit">Zapisz</button><a class="btn btn--light" href="?m=czlonkowie">Anuluj</a></div>
  </form>
  <?php if ($editOsoba['id']): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="osoba_usun"><input type="hidden" name="id" value="<?= (int) $editOsoba['id'] ?>">
      <div class="row"><label style="margin:0;font-weight:400"><input type="checkbox" required> Tak, usuń tę osobę na stałe</label><button class="btn--danger" type="submit">Usuń osobę</button></div>
    </form>
  <?php endif; ?>

<?php elseif ($editSekcja !== null): $v = function ($k) use (&$editSekcja) { return h($editSekcja[$k] ?? ''); }; ?>
  <form class="box" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="sekcja_zapisz"><input type="hidden" name="id" value="<?= (int) $editSekcja['id'] ?>">
    <h3><?= $editSekcja['id'] ? 'Edycja sekcji' : 'Nowa sekcja' ?></h3>
    <label for="nazwa">Nazwa</label>
    <input type="text" id="nazwa" name="nazwa" maxlength="60" value="<?= $v('nazwa') ?>" required autofocus>
    <label for="kolor">Kolor</label>
    <select id="kolor" name="kolor"><?php foreach (KOLORY as $k => $n): ?><option value="<?= $k ?>"<?= ($editSekcja['kolor'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
    <label for="opis">Opis</label>
    <input type="text" id="opis" name="opis" maxlength="300" value="<?= $v('opis') ?>">
    <label for="kolejnosc">Kolejność</label>
    <p class="hint" id="kolejnosc_h">Mniejsza liczba = wyżej na liście.</p>
    <input type="number" id="kolejnosc" name="kolejnosc" value="<?= (int) ($editSekcja['kolejnosc'] ?? 0) ?>" aria-describedby="kolejnosc_h">
    <div class="row"><button type="submit">Zapisz</button><a class="btn btn--light" href="?m=czlonkowie">Anuluj</a></div>
  </form>
  <?php if ($editSekcja['id']): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="sekcja_usun"><input type="hidden" name="id" value="<?= (int) $editSekcja['id'] ?>">
      <div class="row"><label style="margin:0;font-weight:400"><input type="checkbox" required> Tak, usuń tę sekcję na stałe</label><button class="btn--danger" type="submit">Usuń sekcję</button></div>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="row" style="margin: 0 0 18px">
    <a class="btn" href="?m=czlonkowie&osoba=nowa">+ Nowa osoba</a>
    <a class="btn btn--light" href="?m=czlonkowie&sekcja=nowa">+ Nowa sekcja</a>
    <a class="btn btn--light" href="../o-nas.html" target="_blank" rel="noopener">Zobacz stronę</a>
  </div>

  <h3>Zarząd</h3>
  <div class="box tabela">
    <table>
      <thead><tr><th>Imię i nazwisko</th><th>Funkcja</th><th>E-mail</th><th>Kolejność</th><th>Status</th></tr></thead>
      <tbody>
      <?php $zarzad = pmg_db()->query('SELECT * FROM pmg_osoby WHERE sekcja_id IS NULL ORDER BY kolejnosc, nazwisko'); $bylZarzad = false; ?>
      <?php foreach ($zarzad as $o): $bylZarzad = true; ?>
        <tr>
          <td><a href="?m=czlonkowie&osoba=<?= (int) $o['id'] ?>"><?= h($o['imie'] . ' ' . $o['nazwisko']) ?></a></td>
          <td><?= h($o['funkcja']) ?></td>
          <td><?= h($o['email']) ?></td>
          <td><?= (int) $o['kolejnosc'] ?></td>
          <td><?= $o['aktywna'] ? 'Aktywna' : '<span class="draft">Ukryta</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bylZarzad): ?><tr><td colspan="5">Brak osób w zarządzie.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php foreach ($sekcjeLista as $s): ?>
    <div class="box">
      <div class="row" style="margin-top:0;justify-content:space-between">
        <h3 style="margin:0"><?= h($s['nazwa']) ?> <span class="hint" style="display:inline"><?= h(KOLORY[$s['kolor']] ?? $s['kolor']) ?></span></h3>
        <a href="?m=czlonkowie&sekcja=<?= (int) $s['id'] ?>">Edytuj sekcję</a>
      </div>
      <?php if ($s['opis'] !== ''): ?><p class="hint"><?= h($s['opis']) ?></p><?php endif; ?>
      <div class="tabela">
        <table>
          <thead><tr><th>Imię i nazwisko</th><th>Funkcja</th><th>E-mail</th><th>Koordynator</th><th>Kolejność</th><th>Status</th></tr></thead>
          <tbody>
          <?php $st = pmg_db()->prepare('SELECT * FROM pmg_osoby WHERE sekcja_id = ? ORDER BY koordynator DESC, kolejnosc, nazwisko'); $st->execute([$s['id']]); $osoby = $st->fetchAll(); ?>
          <?php foreach ($osoby as $o): ?>
            <tr>
              <td><a href="?m=czlonkowie&osoba=<?= (int) $o['id'] ?>"><?= h($o['imie'] . ' ' . $o['nazwisko']) ?></a></td>
              <td><?= h($o['funkcja']) ?></td>
              <td><?= h($o['email']) ?></td>
              <td><?= $o['koordynator'] ? 'Tak' : '—' ?></td>
              <td><?= (int) $o['kolejnosc'] ?></td>
              <td><?= $o['aktywna'] ? 'Aktywna' : '<span class="draft">Ukryta</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$osoby): ?><tr><td colspan="6">Brak osób w tej sekcji.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$sekcjeLista): ?><p class="hint">Brak sekcji — dodaj pierwszą przyciskiem powyżej.</p><?php endif; ?>

<?php endif; ?>
