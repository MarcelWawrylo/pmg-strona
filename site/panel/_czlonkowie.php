<?php
// Moduł Członkowie: sekcje koła + osoby (zarząd i sekcje). Wołany wyłącznie z index.php (?m=czlonkowie).
defined('PMG_PANEL') || exit;

// Kolejność osób na liście = kolejność na stronie. W sekcji koordynatorzy są zawsze przed pozostałymi (strona też je rozdziela).
const OSOBY_ORDER_ZARZAD = 'kolejnosc, nazwisko, id';
const OSOBY_ORDER_SEKCJA = 'koordynator DESC, kolejnosc, nazwisko, id';

// Osoby jednej grupy w kolejności z listy: $sekcjaId === null to zarząd.
function osoby_grupy($sekcjaId)
{
    if ($sekcjaId === null) return pmg_db()->query('SELECT * FROM pmg_osoby WHERE sekcja_id IS NULL ORDER BY ' . OSOBY_ORDER_ZARZAD)->fetchAll();
    $st = pmg_db()->prepare('SELECT * FROM pmg_osoby WHERE sekcja_id = ? ORDER BY ' . OSOBY_ORDER_SEKCJA);
    $st->execute([(int) $sekcjaId]);
    return $st->fetchAll();
}

// Nowa pozycja na końcu grupy ($sekcjaId === null to zarząd): największa kolejność w grupie + 1.
function osoby_nastepna_kolejnosc($sekcjaId)
{
    if ($sekcjaId === null) return (int) pmg_db()->query('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_osoby WHERE sekcja_id IS NULL')->fetchColumn();
    $st = pmg_db()->prepare('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_osoby WHERE sekcja_id = ?');
    $st->execute([(int) $sekcjaId]);
    return (int) $st->fetchColumn();
}

// Przesuwa osobę o jedno miejsce w jej grupie. W sekcji nie przeskakuje koordynatorów (lista i tak ich rozdziela, więc
// zamiana nic by nie zmieniła). Zwraca false, gdy nie ma osoby albo jest już na brzegu.
function osoba_przesun($id, $kierunek)
{
    $st = pmg_db()->prepare('SELECT sekcja_id FROM pmg_osoby WHERE id = ?');
    $st->execute([$id]);
    $w = $st->fetch();
    if (!$w) return false;
    $sekcja = $w['sekcja_id'] === null ? null : (int) $w['sekcja_id'];
    if ($sekcja !== null) {
        $lista = osoby_grupy($sekcja);
        foreach ($lista as $i => $o) {
            if ((int) $o['id'] !== $id) continue;
            $cel = $kierunek === 'gora' ? $i - 1 : $i + 1;
            if (isset($lista[$cel]) && (int) $lista[$cel]['koordynator'] !== (int) $o['koordynator']) return false;
        }
    }
    return przesun('pmg_osoby', $sekcja === null ? OSOBY_ORDER_ZARZAD : OSOBY_ORDER_SEKCJA, $id, $kierunek, 'sekcja_id', $sekcja);
}

$editSekcja = null;
$editOsoba = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    // ---------- Sekcje ----------
    if ($action === 'sekcja_gora' || $action === 'sekcja_dol') {
        $id = (int) ($_POST['id'] ?? 0);
        $kier = $action === 'sekcja_gora' ? 'gora' : 'dol';
        if (przesun('pmg_sekcje', 'kolejnosc, id', $id, $kier)) loguj('czlonkowie', 'kolejnosc', $id, 'Sekcja ' . nazwa_rekordu('SELECT nazwa FROM pmg_sekcje WHERE id = ?', $id));
        go_po_przesunieciu('?m=czlonkowie', 's' . $id, $kier);

    } elseif ($action === 'osoba_gora' || $action === 'osoba_dol') {
        $id = (int) ($_POST['id'] ?? 0);
        $kier = $action === 'osoba_gora' ? 'gora' : 'dol';
        if (osoba_przesun($id, $kier)) loguj('czlonkowie', 'kolejnosc', $id, nazwa_rekordu("SELECT CONCAT(imie, ' ', nazwisko) FROM pmg_osoby WHERE id = ?", $id));
        go_po_przesunieciu('?m=czlonkowie', 'o' . $id, $kier);

    } elseif ($action === 'sekcja_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $st = pmg_db()->prepare('SELECT nazwa FROM pmg_sekcje WHERE id = ?'); // nazwa do dziennika, zanim wiersz zniknie
            $st->execute([$id]);
            $nazwa = (string) $st->fetchColumn();
            $st = pmg_db()->prepare('DELETE FROM pmg_sekcje WHERE id = ?');
            $st->execute([$id]);
            if ($st->rowCount() > 0) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
                loguj('czlonkowie', 'usuniecie', $id, 'Sekcja ' . $nazwa);
                $_SESSION['flash'] = 'Sekcja usunięta.';
                go('?m=czlonkowie');
            }
            $error = 'Nie znaleziono sekcji — nic nie usunięto.';
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? 'W sekcji są osoby — przenieś je najpierw.' : 'Błąd usuwania.';
        }
    } elseif ($action === 'import') {
        pmg_import_wykonaj('czlonkowie');
    } elseif ($action === 'sekcja_zapisz') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [
            'nazwa' => mb_substr(trim((string) ($_POST['nazwa'] ?? '')), 0, 60),
            'kolor' => array_key_exists((string) ($_POST['kolor'] ?? ''), KOLORY) ? $_POST['kolor'] : 'pink',
            'opis' => mb_substr(trim((string) ($_POST['opis'] ?? '')), 0, 300),
        ];
        if ($f['nazwa'] === '') {
            $bledyPol['nazwa'] = 'Podaj nazwę sekcji.';
        } else {
            if ($id) {
                pmg_db()->prepare('UPDATE pmg_sekcje SET nazwa=?, kolor=?, opis=? WHERE id=?')
                    ->execute([$f['nazwa'], $f['kolor'], $f['opis'], $id]);
                loguj('czlonkowie', 'edycja', $id, 'Sekcja ' . $f['nazwa']);
            } else {
                // Nowa sekcja ląduje na końcu listy (kolejność zmieniasz strzałkami na liście).
                $kolejnosc = (int) pmg_db()->query('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_sekcje')->fetchColumn();
                pmg_db()->prepare('INSERT INTO pmg_sekcje (nazwa, kolor, opis, kolejnosc) VALUES (?,?,?,?)')
                    ->execute([$f['nazwa'], $f['kolor'], $f['opis'], $kolejnosc]);
                $id = (int) pmg_db()->lastInsertId();
                loguj('czlonkowie', 'dodanie', $id, 'Sekcja ' . $f['nazwa']);
            }
            $_SESSION['flash'] = 'Zapisano.';
            go('?m=czlonkowie');
        }
        if ($error !== '' || $bledyPol) $editSekcja = array_merge($f, ['id' => $id]);

    // ---------- Osoby ----------
    } elseif ($action === 'osoba_usun') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT zdjecie, imie, nazwisko FROM pmg_osoby WHERE id = ?');
        $st->execute([$id]);
        $osoba = $st->fetch();
        $img = $osoba ? $osoba['zdjecie'] : null;
        $st = pmg_db()->prepare('DELETE FROM pmg_osoby WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() > 0) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
            drop_image($img ?: null);
            loguj('czlonkowie', 'usuniecie', $id, $osoba['imie'] . ' ' . $osoba['nazwisko']);
            $_SESSION['flash'] = 'Osoba usunięta.';
            go('?m=czlonkowie');
        }
        $error = 'Nie znaleziono osoby — nic nie usunięto.';
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
            'aktywna' => empty($_POST['aktywna']) ? 0 : 1,
        ];
        $old = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_osoby WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        // Każde pole sprawdzane osobno, w kolejności pól formularza — użytkownik widzi naraz wszystkie błędy pól (błąd pliku dopiero po ich poprawieniu, bo plik wgrywamy na końcu).
        if ($f['imie'] === '') $bledyPol['imie'] = 'Podaj imię.';
        if ($f['nazwisko'] === '') $bledyPol['nazwisko'] = 'Podaj nazwisko.';
        if ($f['sekcja_id'] !== null) {
            $st = pmg_db()->prepare('SELECT 1 FROM pmg_sekcje WHERE id = ?');
            $st->execute([$f['sekcja_id']]);
            if (!$st->fetchColumn()) $bledyPol['sekcja_id'] = 'Nieprawidłowa sekcja.';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $bledyPol['email'] = 'Podaj poprawny adres e-mail.';
        if (!url_ok($f['linkedin'])) $bledyPol['linkedin'] = 'LinkedIn: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        blad_opisu_zdjecia($old['zdjecie'] ?? null);
        if ($error === '' && !$bledyPol) {
            $noweZdjecie = null; // plik wgrany w tym żądaniu — usuwany, jeśli zapis do bazy się nie uda
            try {
                // Kolejność: bez zmian, dopóki osoba zostaje w tej samej grupie; nowa osoba i osoba przeniesiona
                // do innej sekcji (albo do zarządu) trafia na koniec swojej grupy.
                $zmianaGrupy = !$old || (string) ($old['sekcja_id'] ?? '') !== (string) ($f['sekcja_id'] ?? '');
                $f['kolejnosc'] = $zmianaGrupy ? osoby_nastepna_kolejnosc($f['sekcja_id']) : (int) $old['kolejnosc'];
                $stareZdjecie = $old['zdjecie'] ?? null;
                $f['zdjecie'] = zdjecie('czlonkowie', $stareZdjecie);
                if ($f['zdjecie'] !== $stareZdjecie) $noweZdjecie = $f['zdjecie'];
                if ($id && $old) {
                    $st = pmg_db()->prepare('UPDATE pmg_osoby SET imie=?, nazwisko=?, funkcja=?, sekcja_id=?, koordynator=?, email=?, linkedin=?, zdjecie=?, zdjecie_alt=?, kolejnosc=?, aktywna=? WHERE id=?');
                    $st->execute([$f['imie'], $f['nazwisko'], $f['funkcja'], $f['sekcja_id'], $f['koordynator'], $f['email'], $f['linkedin'], $f['zdjecie'], $f['zdjecie_alt'], $f['kolejnosc'], $f['aktywna'], $id]);
                    $noweZdjecie = null;
                    if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                    loguj('czlonkowie', 'edycja', $id, $f['imie'] . ' ' . $f['nazwisko']);
                } else {
                    $st = pmg_db()->prepare('INSERT INTO pmg_osoby (imie, nazwisko, funkcja, sekcja_id, koordynator, email, linkedin, zdjecie, zdjecie_alt, kolejnosc, aktywna) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $st->execute([$f['imie'], $f['nazwisko'], $f['funkcja'], $f['sekcja_id'], $f['koordynator'], $f['email'], $f['linkedin'], $f['zdjecie'], $f['zdjecie_alt'], $f['kolejnosc'], $f['aktywna']]);
                    $noweZdjecie = null;
                    $id = (int) pmg_db()->lastInsertId();
                    loguj('czlonkowie', 'dodanie', $id, $f['imie'] . ' ' . $f['nazwisko']);
                }
                $_SESSION['flash'] = $f['aktywna'] ? 'Zapisano i opublikowano. Na stronie zmiana pojawi się w ciągu 5 minut.' : 'Zapisano jako szkic (niewidoczny na stronie).';
                go('?m=czlonkowie');
            } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
                error_log('czlonkowie zapis: ' . $e->getMessage());
                drop_image($noweZdjecie);
                $f['zdjecie'] = $old['zdjecie'] ?? null;
                $error = 'Błąd zapisu — nic nie zapisano. Sprawdź długość pól (np. e-mail do 150 znaków) i spróbuj ponownie.';
            } catch (BladPliku $e) {
                $bledyPol['zdjecie'] = $e->getMessage();
            }
        }
        if ($error !== '' || $bledyPol) $editOsoba = array_merge($old ?: [], $f, ['id' => $id]);
    }
}

// ---------- Widok formularza z GET, gdy nie ma już $edit z POST powyżej ----------
if ($editSekcja === null && isset($_GET['sekcja'])) {
    if ($_GET['sekcja'] === 'nowa') {
        $editSekcja = ['id' => 0, 'nazwa' => '', 'kolor' => 'pink', 'opis' => ''];
    } else {
        $st = pmg_db()->prepare('SELECT * FROM pmg_sekcje WHERE id = ?');
        $st->execute([(int) $_GET['sekcja']]);
        $editSekcja = $st->fetch() ?: null;
    }
}
if ($editOsoba === null && isset($_GET['osoba'])) {
    if ($_GET['osoba'] === 'nowa') {
        $editOsoba = ['id' => 0, 'imie' => '', 'nazwisko' => '', 'funkcja' => '', 'sekcja_id' => '', 'koordynator' => 0, 'email' => '', 'linkedin' => '', 'zdjecie_alt' => '', 'aktywna' => 1];
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
        'wstecz' => ['href' => '?m=czlonkowie', 'etykieta' => 'Członkowie — osoby i sekcje'],
    ];
} elseif ($editSekcja !== null) {
    $pmgNaglowek = [
        'tytul' => $editSekcja['id'] ? 'Edytuj sekcję' : 'Nowa sekcja',
        'opis' => $editSekcja['id'] ? (string) $editSekcja['nazwa'] : '',
        'wstecz' => ['href' => '?m=czlonkowie', 'etykieta' => 'Członkowie — osoby i sekcje'],
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
      <input type="text" id="imie" name="imie" maxlength="50" value="<?= $v('imie') ?>" required<?= blad_pola('imie') ?>><?= komunikat_pola('imie') ?>
      <label for="nazwisko">Nazwisko</label>
      <input type="text" id="nazwisko" name="nazwisko" maxlength="60" value="<?= $v('nazwisko') ?>" required<?= blad_pola('nazwisko') ?>><?= komunikat_pola('nazwisko') ?>
      <label for="funkcja">Funkcja</label>
      <p class="pmg-hint" id="funkcja_h">Np. Prezes, Koordynator, Koordynatorka, Członek.</p>
      <input type="text" id="funkcja" name="funkcja" maxlength="60" value="<?= $v('funkcja') ?>" aria-describedby="funkcja_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-przydzial">
      <h2 class="pmg-form-section__title" id="sek-przydzial">Przydział</h2>
      <label for="sekcja_id">Sekcja</label>
      <select id="sekcja_id" name="sekcja_id"<?= blad_pola('sekcja_id') ?>>
        <option value=""<?= ($editOsoba['sekcja_id'] ?? '') === '' ? ' selected' : '' ?>>Zarząd</option>
        <?php foreach ($sekcjeLista as $s): ?>
          <option value="<?= (int) $s['id'] ?>"<?= (string) ($editOsoba['sekcja_id'] ?? '') === (string) $s['id'] ? ' selected' : '' ?>><?= h($s['nazwa']) ?></option>
        <?php endforeach; ?>
      </select><?= komunikat_pola('sekcja_id') ?>
      <label class="pmg-check"><input type="checkbox" name="koordynator" value="1"<?= !empty($editOsoba['koordynator']) ? ' checked' : '' ?>><span>Koordynator/-ka sekcji</span></label>
      <p class="pmg-hint">Kolejność osób zmieniasz strzałkami na liście członków. Nowa osoba trafia na koniec swojej grupy.</p>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-kontakt">
      <h2 class="pmg-form-section__title" id="sek-kontakt">Kontakt</h2>
      <label for="email">E-mail</label>
      <p class="pmg-hint" id="email_h">Adres będzie widoczny na stronie O nas.</p>
      <input type="email" id="email" name="email" maxlength="150" value="<?= $v('email') ?>" required<?= blad_pola('email', 'email_h') ?>><?= komunikat_pola('email') ?>
      <label for="linkedin">LinkedIn <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="linkedin_h">Pełny adres zaczynający się od https://. Pole opcjonalne.</p>
      <input type="text" id="linkedin" name="linkedin" maxlength="200" value="<?= $v('linkedin') ?>"<?= blad_pola('linkedin', 'linkedin_h') ?>><?= komunikat_pola('linkedin') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zdjecie">
      <h2 class="pmg-form-section__title" id="sek-zdjecie">Zdjęcie</h2>
      <label for="zdjecie">Zdjęcie 1:1 — kwadrat (JPG, PNG albo WebP)</label>
      <p class="pmg-hint" id="zdjecie_h">Maks. 10 MB. Zdjęcie zostanie przycięte do proporcji 1:1 (kwadrat). Najlepiej wgraj zdjęcie w tych proporcjach. Np. 800 × 800 px. Opcjonalne. Obecnie niewidoczne na stronie (brak miejsca w projekcie graficznym „O nas”) — zapisujemy je na zapas.</p>
      <?php if (!empty($editOsoba['zdjecie'])): ?>
        <figure class="pmg-photo pmg-photo--1x1"><img src="../<?= h($editOsoba['zdjecie']) ?>" alt=""><figcaption class="pmg-hint">Obecne zdjęcie. Wgranie nowego pliku zastąpi to zdjęcie.</figcaption></figure>
      <?php endif; ?>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp"<?= blad_pola('zdjecie', 'zdjecie_h') ?>><?= komunikat_pola('zdjecie') ?>
      <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
      <p class="pmg-hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
      <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>"<?= blad_pola('zdjecie_alt', 'zdjecie_alt_h') ?>><?= komunikat_pola('zdjecie_alt') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-widocznosc">
      <h2 class="pmg-form-section__title" id="sek-widocznosc">Publikacja</h2>
      <label class="pmg-check"><input type="checkbox" name="aktywna" value="1"<?= ($editOsoba['id'] ?? 0) === 0 || !empty($editOsoba['aktywna']) ? ' checked' : '' ?> aria-describedby="aktywna_h"><span>Opublikuj na stronie</span></label>
      <p class="pmg-hint pmg-hint--check" id="aktywna_h">Bez zaznaczenia = szkic, niewidoczny na stronie (osoba zostaje w panelu).</p>
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
    <input type="text" id="nazwa" name="nazwa" maxlength="60" value="<?= $v('nazwa') ?>" required<?= blad_pola('nazwa') ?>><?= komunikat_pola('nazwa') ?>
    <label for="kolor">Kolor</label>
    <select id="kolor" name="kolor"><?php foreach (KOLORY as $k => $n): ?><option value="<?= $k ?>"<?= ($editSekcja['kolor'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
    <label for="opis">Opis <span class="pmg-opt">(opcjonalnie)</span></label>
    <input type="text" id="opis" name="opis" maxlength="300" value="<?= $v('opis') ?>" data-pmg-licznik>
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
  <?php pmg_import_blok('czlonkowie'); ?>
  <div class="pmg-card">
    <div class="pmg-card__head"><h2 class="pmg-h2">Zarząd</h2></div>
    <div class="pmg-table-wrap pmg-table-wrap--flush">
      <table class="pmg-table pmg-table--klikalna pmg-table--osoby">
        <caption class="pmg-vh">Zarząd</caption>
        <thead><tr><th scope="col">Imię i nazwisko</th><th scope="col">Funkcja</th><th scope="col">E-mail</th><th scope="col">Kolejność</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php $zarzad = osoby_grupy(null); $bylZarzad = $zarzad !== []; ?>
        <?php foreach ($zarzad as $i => $o): $wylG = $i === 0; $wylD = $i === count($zarzad) - 1; ?>
          <tr id="wiersz-o<?= (int) $o['id'] ?>">
            <td class="pmg-td-main" data-label="Imię i nazwisko"><a class="pmg-row-link" href="?m=czlonkowie&osoba=<?= (int) $o['id'] ?>"><?= h($o['imie'] . ' ' . $o['nazwisko']) ?></a></td>
            <td data-label="Funkcja"><?= h($o['funkcja']) ?></td>
            <td data-label="E-mail"><?= h($o['email']) ?></td>
            <td class="pmg-td-actions" data-label="Kolejność">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="osoba_gora"<?= $wylG ? ' disabled' : '' ?><?= fokus_strzalki('o' . (int) $o['id'], 'gora', $wylG, $wylD) ?> aria-label="Przesuń wyżej: <?= h($o['imie'] . ' ' . $o['nazwisko']) ?>"><span aria-hidden="true">↑</span></button>
                <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="osoba_dol"<?= $wylD ? ' disabled' : '' ?><?= fokus_strzalki('o' . (int) $o['id'], 'dol', $wylG, $wylD) ?> aria-label="Przesuń niżej: <?= h($o['imie'] . ' ' . $o['nazwisko']) ?>"><span aria-hidden="true">↓</span></button>
              </form>
            </td>
            <td data-label="Status"><?php if (!$o['aktywna']): ?><span class="pmg-chip pmg-chip--neutral">Szkic</span><?php else: ?><span class="pmg-chip pmg-chip--success">Opublikowany</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bylZarzad): ?>
          <tr><td colspan="5" class="pmg-empty">Brak osób w zarządzie.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&osoba=nowa">+ Dodaj pierwszą osobę</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php foreach ($sekcjeLista as $si => $s): ?>
    <div class="pmg-card">
      <?php $wylGs = $si === 0; $wylDs = $si === count($sekcjeLista) - 1; ?>
      <div class="pmg-card__head" id="wiersz-s<?= (int) $s['id'] ?>">
        <div class="pmg-section-title">
          <h2 class="pmg-h2"><?= h($s['nazwa']) ?></h2>
          <span class="pmg-section-color"><span class="pmg-swatch pmg-swatch--<?= h($s['kolor']) ?>" aria-hidden="true"></span><?= h(KOLORY[$s['kolor']] ?? $s['kolor']) ?></span>
        </div>
        <div class="pmg-td-actions">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="sekcja_gora"<?= $wylGs ? ' disabled' : '' ?><?= fokus_strzalki('s' . (int) $s['id'], 'gora', $wylGs, $wylDs) ?> aria-label="Przesuń wyżej: sekcja <?= h($s['nazwa']) ?>"><span aria-hidden="true">↑</span></button>
            <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="sekcja_dol"<?= $wylDs ? ' disabled' : '' ?><?= fokus_strzalki('s' . (int) $s['id'], 'dol', $wylGs, $wylDs) ?> aria-label="Przesuń niżej: sekcja <?= h($s['nazwa']) ?>"><span aria-hidden="true">↓</span></button>
          </form>
          <a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&sekcja=<?= (int) $s['id'] ?>">Edytuj sekcję</a>
        </div>
      </div>
      <?php if ($s['opis'] !== ''): ?><p class="pmg-hint"><?= h($s['opis']) ?></p><?php endif; ?>
      <div class="pmg-table-wrap pmg-table-wrap--flush">
        <table class="pmg-table pmg-table--klikalna pmg-table--osoby">
          <caption class="pmg-vh">Sekcja <?= h($s['nazwa']) ?></caption>
          <thead><tr><th scope="col">Imię i nazwisko</th><th scope="col">Funkcja</th><th scope="col">E-mail</th><th scope="col">Kolejność</th><th scope="col">Status</th></tr></thead>
          <tbody>
          <?php $osoby = osoby_grupy((int) $s['id']); ?>
          <?php foreach ($osoby as $i => $o):
              // strzałka jest wyłączona na brzegu listy i na granicy koordynatorów / pozostałych (ich kolejność jest osobna)
              $wylG = $i === 0 || (int) $osoby[$i - 1]['koordynator'] !== (int) $o['koordynator'];
              $wylD = $i === count($osoby) - 1 || (int) $osoby[$i + 1]['koordynator'] !== (int) $o['koordynator']; ?>
            <tr id="wiersz-o<?= (int) $o['id'] ?>">
              <td class="pmg-td-main" data-label="Imię i nazwisko"><a class="pmg-row-link" href="?m=czlonkowie&osoba=<?= (int) $o['id'] ?>"><?= h($o['imie'] . ' ' . $o['nazwisko']) ?></a></td>
              <td data-label="Funkcja"><?= h($o['funkcja']) ?></td>
              <td data-label="E-mail"><?= h($o['email']) ?></td>
              <td class="pmg-td-actions" data-label="Kolejność">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                  <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="osoba_gora"<?= $wylG ? ' disabled' : '' ?><?= fokus_strzalki('o' . (int) $o['id'], 'gora', $wylG, $wylD) ?> aria-label="Przesuń wyżej: <?= h($o['imie'] . ' ' . $o['nazwisko']) ?>"><span aria-hidden="true">↑</span></button>
                  <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="osoba_dol"<?= $wylD ? ' disabled' : '' ?><?= fokus_strzalki('o' . (int) $o['id'], 'dol', $wylG, $wylD) ?> aria-label="Przesuń niżej: <?= h($o['imie'] . ' ' . $o['nazwisko']) ?>"><span aria-hidden="true">↓</span></button>
                </form>
              </td>
              <td data-label="Status"><?php if ($o['koordynator']): ?><span class="pmg-chip pmg-chip--purple">Koordynator/-ka</span> <?php endif; ?><?php if (!$o['aktywna']): ?><span class="pmg-chip pmg-chip--neutral">Szkic</span><?php else: ?><span class="pmg-chip pmg-chip--success">Opublikowany</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$osoby): ?>
            <tr><td colspan="5" class="pmg-empty">Brak osób w tej sekcji.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&osoba=nowa">+ Dodaj pierwszą osobę</a></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$sekcjeLista): ?>
    <div class="pmg-empty">Brak sekcji — dodaj pierwszą przyciskiem powyżej.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=czlonkowie&sekcja=nowa">+ Dodaj pierwszą sekcję</a></div>
  <?php endif; ?>

<?php endif; ?>
