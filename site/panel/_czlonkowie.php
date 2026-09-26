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

if ($editOsoba !== null) {
    $pmgNaglowek = [
        'tytul' => $editOsoba['id'] ? 'Edytuj osobę' : 'Nowa osoba',
        'opis' => $editOsoba['id'] ? trim($editOsoba['imie'] . ' ' . $editOsoba['nazwisko']) : '',
        'wstecz' => ['href' => '?m=czlonkowie', 'etykieta' => 'Członkowie'],
    ];
} elseif ($editSekcja !== null) {
    $pmgNaglowek = [
        'tytul' => $editSekcja['id'] ? 'Edytuj sekcję' : 'Nowa sekcja',
        'opis' => $editSekcja['id'] ? (string) $editSekcja['nazwa'] : '',
        'wstecz' => ['href' => '?m=czlonkowie', 'etykieta' => 'Członkowie'],
    ];
} else {
    $pmgNaglowek = [
        'akcje' => [
            ['href' => '?m=czlonkowie&osoba=nowa', 'etykieta' => '+ Nowa osoba', 'rodzaj' => 'primary'],
            ['href' => '?m=czlonkowie&sekcja=nowa', 'etykieta' => '+ Nowa sekcja', 'rodzaj' => 'secondary'],
            ['href' => '../o-nas.html', 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($editOsoba !== null): $v = function ($k) use (&$editOsoba) { return h($editOsoba[$k] ?? ''); }; ?>
  <form class="pmg-card" method="post" enctype="multipart/form-data" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="osoba_zapisz"><input type="hidden" name="id" value="<?= (int) $editOsoba['id'] ?>">

    <section class="pmg-form-section" aria-labelledby="sek-osoba">
      <h2 class="pmg-form-section__title" id="sek-osoba">Osoba</h2>
      <label for="imie">Imię</label>
      <input type="text" id="imie" name="imie" maxlength="50" value="<?= $v('imie') ?>" required>
      <label for="nazwisko">Nazwisko</label>
      <input type="text" id="nazwisko" name="nazwisko" maxlength="60" value="<?= $v('nazwisko') ?>" required>
      <label for="funkcja">Funkcja</label>
      <p class="pmg-hint" id="funkcja_h">Np. Prezes, Koordynator, Koordynatorka, Członek.</p>
      <input type="text" id="funkcja" name="funkcja" maxlength="60" value="<?= $v('funkcja') ?>" aria-describedby="funkcja_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-przydzial">
      <h2 class="pmg-form-section__title" id="sek-przydzial">Przydział</h2>
      <label for="sekcja_id">Sekcja</label>
      <select id="sekcja_id" name="sekcja_id">
        <option value=""<?= ($editOsoba['sekcja_id'] ?? '') === '' ? ' selected' : '' ?>>Zarząd</option>
        <?php foreach ($sekcjeLista as $s): ?>
          <option value="<?= (int) $s['id'] ?>"<?= (string) ($editOsoba['sekcja_id'] ?? '') === (string) $s['id'] ? ' selected' : '' ?>><?= h($s['nazwa']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="pmg-check"><input type="checkbox" name="koordynator" value="1"<?= !empty($editOsoba['koordynator']) ? ' checked' : '' ?>><span>Koordynator/-ka sekcji</span></label>
      <label for="kolejnosc">Kolejność</label>
      <p class="pmg-hint" id="kolejnosc_h">Mniejsza liczba = wyżej na liście.</p>
      <input type="number" id="kolejnosc" name="kolejnosc" value="<?= (int) ($editOsoba['kolejnosc'] ?? 0) ?>" aria-describedby="kolejnosc_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-kontakt">
      <h2 class="pmg-form-section__title" id="sek-kontakt">Kontakt</h2>
      <label for="email">E-mail</label>
      <p class="pmg-hint" id="email_h">Adres będzie widoczny na stronie O nas.</p>
      <input type="email" id="email" name="email" maxlength="150" value="<?= $v('email') ?>" required aria-describedby="email_h">
      <label for="linkedin">LinkedIn <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="linkedin_h">Pełny adres zaczynający się od https://. Pole opcjonalne.</p>
      <input type="text" id="linkedin" name="linkedin" maxlength="200" value="<?= $v('linkedin') ?>" aria-describedby="linkedin_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zdjecie">
      <h2 class="pmg-form-section__title" id="sek-zdjecie">Zdjęcie</h2>
      <label for="zdjecie">Zdjęcie 1:1 — kwadrat (JPG, PNG albo WebP)</label>
      <p class="pmg-hint" id="zdjecie_h">JPG, PNG albo WebP, maks. 10 MB. Proporcje 1:1 (kwadrat), np. 800 × 800 px. Opcjonalne. Obecnie niewidoczne na stronie (brak miejsca w projekcie graficznym „O nas”) — zapisujemy je na zapas.</p>
      <?php if (!empty($editOsoba['zdjecie'])): ?>
        <figure class="pmg-photo pmg-photo--1x1"><img src="../<?= h($editOsoba['zdjecie']) ?>" alt=""><figcaption class="pmg-hint">Obecne zdjęcie. Wgranie nowego pliku zastąpi to zdjęcie.</figcaption></figure>
      <?php endif; ?>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp" aria-describedby="zdjecie_h">
      <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
      <p class="pmg-hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
      <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>" aria-describedby="zdjecie_alt_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-widocznosc">
      <h2 class="pmg-form-section__title" id="sek-widocznosc">Widoczność</h2>
      <label class="pmg-check"><input type="checkbox" name="aktywna" value="1"<?= ($editOsoba['id'] ?? 0) === 0 || !empty($editOsoba['aktywna']) ? ' checked' : '' ?> aria-describedby="aktywna_h"><span>Aktywna</span></label>
      <p class="pmg-hint pmg-hint--check" id="aktywna_h">Odznacz, żeby ukryć osobę na stronie bez usuwania.</p>
    </section>

    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=czlonkowie">Anuluj</a>
    </div>
  </form>
  <?php if ($editOsoba['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="osoba_usun"><input type="hidden" name="id" value="<?= (int) $editOsoba['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Osoba zniknie ze strony O nas. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tę osobę na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń osobę</button>
    </form>
  <?php endif; ?>

<?php elseif ($editSekcja !== null): $v = function ($k) use (&$editSekcja) { return h($editSekcja[$k] ?? ''); }; ?>
  <form class="pmg-card" method="post" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="sekcja_zapisz"><input type="hidden" name="id" value="<?= (int) $editSekcja['id'] ?>">
    <label for="nazwa">Nazwa</label>
    <input type="text" id="nazwa" name="nazwa" maxlength="60" value="<?= $v('nazwa') ?>" required>
    <label for="kolor">Kolor</label>
    <select id="kolor" name="kolor"><?php foreach (KOLORY as $k => $n): ?><option value="<?= $k ?>"<?= ($editSekcja['kolor'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
    <label for="opis">Opis <span class="pmg-opt">(opcjonalnie)</span></label>
    <input type="text" id="opis" name="opis" maxlength="300" value="<?= $v('opis') ?>" data-pmg-licznik>
    <label for="kolejnosc">Kolejność</label>
    <p class="pmg-hint" id="kolejnosc_h">Mniejsza liczba = wyżej na liście.</p>
    <input type="number" id="kolejnosc" name="kolejnosc" value="<?= (int) ($editSekcja['kolejnosc'] ?? 0) ?>" aria-describedby="kolejnosc_h">
    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=czlonkowie">Anuluj</a>
    </div>
  </form>
  <?php if ($editSekcja['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="sekcja_usun"><input type="hidden" name="id" value="<?= (int) $editSekcja['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Sekcję można usunąć tylko wtedy, gdy nie ma w niej osób.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tę sekcję na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń sekcję</button>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="pmg-card">
    <div class="pmg-card__head"><h2 class="pmg-h2">Zarząd</h2></div>
    <div class="pmg-table-wrap pmg-table-wrap--flush">
      <table class="pmg-table pmg-table--klikalna">
        <caption class="pmg-vh">Zarząd</caption>
        <thead><tr><th scope="col">Imię i nazwisko</th><th scope="col">Funkcja</th><th scope="col">E-mail</th><th scope="col">Kolejność</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php $zarzad = pmg_db()->query('SELECT * FROM pmg_osoby WHERE sekcja_id IS NULL ORDER BY kolejnosc, nazwisko'); $bylZarzad = false; ?>
        <?php foreach ($zarzad as $o): $bylZarzad = true; ?>
          <tr>
            <td class="pmg-td-main" data-label="Imię i nazwisko"><a class="pmg-row-link" href="?m=czlonkowie&osoba=<?= (int) $o['id'] ?>"><?= h($o['imie'] . ' ' . $o['nazwisko']) ?></a></td>
            <td data-label="Funkcja"><?= h($o['funkcja']) ?></td>
            <td data-label="E-mail"><?= h($o['email']) ?></td>
            <td class="pmg-num" data-label="Kolejność"><?= (int) $o['kolejnosc'] ?></td>
            <td data-label="Status"><?php if (!$o['aktywna']): ?><span class="pmg-chip pmg-chip--outline">Ukryta</span><?php else: ?><span class="pmg-muted">Widoczna</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bylZarzad): ?>
          <tr><td colspan="5" class="pmg-empty">Brak osób w zarządzie.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&osoba=nowa">+ Dodaj osobę</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php foreach ($sekcjeLista as $s): ?>
    <div class="pmg-card">
      <div class="pmg-card__head">
        <div class="pmg-section-title">
          <h2 class="pmg-h2"><?= h($s['nazwa']) ?></h2>
          <span class="pmg-section-color"><span class="pmg-swatch pmg-swatch--<?= h($s['kolor']) ?>" aria-hidden="true"></span><?= h(KOLORY[$s['kolor']] ?? $s['kolor']) ?></span>
        </div>
        <a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&sekcja=<?= (int) $s['id'] ?>">Edytuj sekcję</a>
      </div>
      <?php if ($s['opis'] !== ''): ?><p class="pmg-hint"><?= h($s['opis']) ?></p><?php endif; ?>
      <div class="pmg-table-wrap pmg-table-wrap--flush">
        <table class="pmg-table pmg-table--klikalna">
          <caption class="pmg-vh">Sekcja <?= h($s['nazwa']) ?></caption>
          <thead><tr><th scope="col">Imię i nazwisko</th><th scope="col">Funkcja</th><th scope="col">E-mail</th><th scope="col">Kolejność</th><th scope="col">Status</th></tr></thead>
          <tbody>
          <?php $st = pmg_db()->prepare('SELECT * FROM pmg_osoby WHERE sekcja_id = ? ORDER BY koordynator DESC, kolejnosc, nazwisko'); $st->execute([$s['id']]); $osoby = $st->fetchAll(); ?>
          <?php foreach ($osoby as $o): ?>
            <tr>
              <td class="pmg-td-main" data-label="Imię i nazwisko"><a class="pmg-row-link" href="?m=czlonkowie&osoba=<?= (int) $o['id'] ?>"><?= h($o['imie'] . ' ' . $o['nazwisko']) ?></a></td>
              <td data-label="Funkcja"><?= h($o['funkcja']) ?></td>
              <td data-label="E-mail"><?= h($o['email']) ?></td>
              <td class="pmg-num" data-label="Kolejność"><?= (int) $o['kolejnosc'] ?></td>
              <td data-label="Status"><?php if ($o['koordynator']): ?><span class="pmg-chip pmg-chip--purple">Koordynator/-ka</span> <?php endif; ?><?php if (!$o['aktywna']): ?><span class="pmg-chip pmg-chip--outline">Ukryta</span><?php elseif (!$o['koordynator']): ?><span class="pmg-muted">Widoczna</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$osoby): ?>
            <tr><td colspan="5" class="pmg-empty">Brak osób w tej sekcji.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&osoba=nowa">+ Dodaj osobę</a></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$sekcjeLista): ?>
    <div class="pmg-empty">Brak sekcji — dodaj pierwszą przyciskiem powyżej.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&sekcja=nowa">+ Dodaj sekcję</a></div>
  <?php endif; ?>

<?php endif; ?>
