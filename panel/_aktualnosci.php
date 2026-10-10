<?php
// Moduł Aktualności: lista, dodawanie / edycja / usuwanie wpisów. Wołany wyłącznie z index.php (?m=aktualnosci).
defined('PMG_PANEL') || exit;

const AKT_GALERIA_MAX = 12;

function slugify($text)
{
    $text = strtr(mb_strtolower($text, 'UTF-8'), ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z']);
    $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    return substr($text !== '' ? $text : 'wpis', 0, 60);
}

$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT zdjecie, tytul FROM pmg_aktualnosci WHERE id = ?'); // tytuł do dziennika, zanim wiersz zniknie
        $st->execute([$id]);
        $wpis = $st->fetch();
        $img = $wpis ? $wpis['zdjecie'] : null;
        $st = pmg_db()->prepare('SELECT zdjecie FROM pmg_aktualnosci_galeria WHERE wpis_id = ?');
        $st->execute([$id]);
        $pliki = $st->fetchAll(PDO::FETCH_COLUMN);
        $pdo = pmg_db();
        $pdo->beginTransaction(); // wiersze galerii najpierw (klucz obcy); przy błędzie nic nie znika, pliki zostają
        try {
            $pdo->prepare('DELETE FROM pmg_aktualnosci_galeria WHERE wpis_id = ?')->execute([$id]);
            $usun = $pdo->prepare('DELETE FROM pmg_aktualnosci WHERE id = ?');
            $usun->execute([$id]);
            $usunieto = $usun->rowCount() > 0;
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('aktualnosci usuniecie: ' . $e->getMessage());
            $_SESSION['flash'] = 'Nie udało się usunąć wpisu (błąd bazy danych). Nic nie usunięto.';
            go('?m=aktualnosci&id=' . $id);
        }
        if (!$usunieto) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
            $_SESSION['flash'] = 'Nie znaleziono wpisu — nic nie usunięto.';
            go('?m=aktualnosci');
        }
        drop_image($img ?: null);
        foreach ($pliki as $p) drop_image($p);
        loguj('aktualnosci', 'usuniecie', $id, $wpis['tytul']);
        $_SESSION['flash'] = 'Wpis usunięty.';
        go('?m=aktualnosci');
    } elseif ($action === 'import') {
        pmg_import_wykonaj('aktualnosci');
    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [];
        foreach (['tytul' => 200, 'lead' => 600, 'zajawka' => 400, 'autor' => 100, 'zdjecie_alt' => 200] as $k => $max) {
            $f[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        }
        $f['tresc'] = trim(str_replace("\r\n", "\n", (string) ($_POST['tresc'] ?? '')));
        $f['data'] = (string) ($_POST['data'] ?? '');
        $f['kolor'] = array_key_exists((string) ($_POST['kolor'] ?? ''), KOLORY) ? $_POST['kolor'] : 'pink';
        $f['opublikowany'] = empty($_POST['opublikowany']) ? 0 : 1;
        $old = null;
        $noweZdjecie = null; // plik wgrany w tym żądaniu — usuwany, jeśli zapis do bazy się nie uda
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        // Każde pole sprawdzane osobno, w kolejności pól formularza — użytkownik widzi wszystkie błędy naraz.
        if ($f['tytul'] === '') $bledyPol['tytul'] = 'Uzupełnij tytuł.';
        if ($f['lead'] === '') $bledyPol['lead'] = 'Uzupełnij lead.';
        if ($f['tresc'] === '') $bledyPol['tresc'] = 'Uzupełnij treść.';
        if (!data_ok($f['data'])) $bledyPol['data'] = 'Podaj datę wpisu.';
        blad_opisu_zdjecia($old['zdjecie'] ?? null);
        try {
            if ($bledyPol) throw new BladPol();
            $stareZdjecie = $old['zdjecie'] ?? null;
            $f['zdjecie'] = zdjecie('aktualnosci', $stareZdjecie);
            if ($f['zdjecie'] !== $stareZdjecie) $noweZdjecie = $f['zdjecie'];
            if ($id && $old) {
                $st = pmg_db()->prepare('UPDATE pmg_aktualnosci SET data=?, kolor=?, tytul=?, lead=?, zajawka=?, tresc=?, zdjecie=?, zdjecie_alt=?, autor=?, opublikowany=? WHERE id=?');
                $st->execute([$f['data'], $f['kolor'], $f['tytul'], $f['lead'], $f['zajawka'], $f['tresc'], $f['zdjecie'], $f['zdjecie_alt'], $f['autor'], $f['opublikowany'], $id]);
                $noweZdjecie = null;
                if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                loguj('aktualnosci', 'edycja', $id, $f['tytul']);
            } else {
                $base = $slug = slugify($f['tytul']);
                $st = pmg_db()->prepare('SELECT 1 FROM pmg_aktualnosci WHERE slug = ?');
                for ($n = 2; $st->execute([$slug]) && $st->fetchColumn(); $n++) $slug = $base . '-' . $n;
                $st = pmg_db()->prepare('INSERT INTO pmg_aktualnosci (slug, data, kolor, tytul, lead, zajawka, tresc, zdjecie, zdjecie_alt, autor, opublikowany) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$slug, $f['data'], $f['kolor'], $f['tytul'], $f['lead'], $f['zajawka'], $f['tresc'], $f['zdjecie'], $f['zdjecie_alt'], $f['autor'], $f['opublikowany']]);
                $noweZdjecie = null;
                $id = (int) pmg_db()->lastInsertId();
                loguj('aktualnosci', 'dodanie', $id, $f['tytul']);
            }
            $_SESSION['flash'] = $f['opublikowany'] ? 'Zapisano i opublikowano. Na stronie zmiana pojawi się w ciągu 5 minut.' : 'Zapisano jako szkic (niewidoczny na stronie).';
            go('?m=aktualnosci');
        } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
            error_log('aktualnosci zapis: ' . $e->getMessage());
            drop_image($noweZdjecie);
            $error = 'Błąd zapisu — nic nie zapisano. Sprawdź długość treści i spróbuj ponownie.';
            $edit = array_merge($old ?: [], $f, ['id' => $id, 'zdjecie' => $old['zdjecie'] ?? null]);
        } catch (BladPliku $e) {
            $bledyPol['zdjecie'] = $e->getMessage();
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        } catch (BladPol $e) { // błędy pól są już w $bledyPol
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        }

    // ---------- Galeria ----------
    } elseif (strpos($action, 'gal_') === 0) {
        $wid = (int) ($_POST['wpis_id'] ?? 0);
        $gid = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci WHERE id = ?');
        $st->execute([$wid]);
        $ed = $st->fetch() ?: null;
        if (!$ed) {
            $_SESSION['flash'] = 'Nie znaleziono tego wpisu.';
            go('?m=aktualnosci');
        }
        $wroc = '?m=aktualnosci&id=' . $wid . '#galeria';
        $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci_galeria WHERE id = ? AND wpis_id = ?');
        $st->execute([$gid, $wid]);
        $g = $st->fetch() ?: null;
        $noweGal = null; // plik wgrany w tym żądaniu — usuwany, jeśli zapis do bazy się nie uda
        try {
            if ($action === 'gal_dodaj') {
                $podpis = mb_substr(trim((string) ($_POST['podpis'] ?? '')), 0, 200);
                $ile = pmg_db()->prepare('SELECT COUNT(*) FROM pmg_aktualnosci_galeria WHERE wpis_id = ?');
                $ile->execute([$wid]);
                if ((int) $ile->fetchColumn() >= AKT_GALERIA_MAX) throw new RuntimeException('Galeria może mieć najwyżej ' . AKT_GALERIA_MAX . ' zdjęć.');
                if (empty($_FILES['plik']['name'])) $bledyPol['gal-nowe-plik'] = 'Wybierz plik ze zdjęciem.';
                if ($podpis === '') $bledyPol['gal-nowe-podpis'] = 'Dodaj podpis zdjęcia (to także opis dla osób niewidomych).';
                if ($bledyPol) throw new BladPol();
                $plik = $noweGal = save_image($_FILES['plik'], 'aktualnosci');
                $kol = pmg_db()->prepare('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_aktualnosci_galeria WHERE wpis_id = ?');
                $kol->execute([$wid]);
                pmg_db()->prepare('INSERT INTO pmg_aktualnosci_galeria (wpis_id, zdjecie, podpis, kolejnosc) VALUES (?,?,?,?)')
                    ->execute([$wid, $plik, $podpis, (int) $kol->fetchColumn()]);
                $noweGal = null;
                loguj('aktualnosci', 'galeria', $wid, $ed['tytul']);
                $_SESSION['flash'] = 'Zdjęcie dodane do galerii.';
                go($wroc);
            } elseif (!$g) {
                throw new RuntimeException('Nie znaleziono zdjęcia.');
            } elseif ($action === 'gal_zapisz') {
                $podpis = mb_substr(trim((string) ($_POST['podpis'] ?? '')), 0, 200);
                if ($podpis === '') $bledyPol['gal-podpis-' . $gid] = 'Podpis zdjęcia nie może być pusty (to także opis dla osób niewidomych).';
                if ($bledyPol) throw new BladPol();
                $nowe = null;
                if (!empty($_FILES['plik']['name'])) $nowe = $noweGal = save_image($_FILES['plik'], 'aktualnosci');
                if ($nowe !== null) {
                    pmg_db()->prepare('UPDATE pmg_aktualnosci_galeria SET podpis = ?, zdjecie = ? WHERE id = ?')->execute([$podpis, $nowe, $gid]);
                    $noweGal = null;
                    drop_image($g['zdjecie']);
                } else {
                    pmg_db()->prepare('UPDATE pmg_aktualnosci_galeria SET podpis = ? WHERE id = ?')->execute([$podpis, $gid]);
                }
                loguj('aktualnosci', 'galeria', $wid, $ed['tytul']);
                $_SESSION['flash'] = 'Zapisano zdjęcie.';
                go($wroc);
            } elseif ($action === 'gal_gora' || $action === 'gal_dol') {
                if (przesun('pmg_aktualnosci_galeria', 'kolejnosc, id', $gid, $action === 'gal_gora' ? 'gora' : 'dol', 'wpis_id', $wid)) loguj('aktualnosci', 'galeria', $wid, $ed['tytul']);
                go($wroc);
            } elseif ($action === 'gal_usun') {
                $usun = pmg_db()->prepare('DELETE FROM pmg_aktualnosci_galeria WHERE id = ?');
                $usun->execute([$gid]);
                if ($usun->rowCount() === 0) throw new RuntimeException('Nie znaleziono zdjęcia — nic nie usunięto.'); // A2 6.5
                drop_image($g['zdjecie']);
                loguj('aktualnosci', 'galeria', $wid, $ed['tytul']);
                $_SESSION['flash'] = 'Zdjęcie usunięte z galerii.';
                go($wroc);
            }
        } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
            error_log('aktualnosci galeria: ' . $e->getMessage());
            drop_image($noweGal);
            $error = 'Błąd zapisu galerii.';
            $edit = $ed;
        } catch (BladPliku $e) { // plik wgrywany jest w gal_dodaj (nowy) albo gal_zapisz (podmiana)
            $bledyPol[$action === 'gal_dodaj' ? 'gal-nowe-plik' : 'gal-plik-' . $gid] = $e->getMessage();
            $edit = $ed;
        } catch (BladPol $e) { // błędy pól są już w $bledyPol
            $edit = $ed;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $edit = $ed;
        }
    }
}

if ($edit === null) {
    if (isset($_GET['nowy'])) {
        $edit = ['id' => 0, 'data' => date('Y-m-d'), 'kolor' => 'pink', 'opublikowany' => 0];
    } elseif (isset($_GET['id'])) {
        $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci WHERE id = ?');
        $st->execute([(int) $_GET['id']]);
        $edit = $st->fetch() ?: null;
    }
}
$v = function ($k) use (&$edit) { return h($edit[$k] ?? ''); };

$galeria = [];
if ($edit !== null && !empty($edit['id'])) {
    $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci_galeria WHERE wpis_id = ? ORDER BY kolejnosc, id');
    $st->execute([(int) $edit['id']]);
    $galeria = $st->fetchAll();
}

if ($edit !== null) {
    $pmgNaglowek = [
        'tytul' => $edit['id'] ? 'Edytuj wpis' : 'Nowy wpis',
        'opis' => $edit['id'] ? (string) $edit['tytul'] : 'Wpis bez zaznaczenia „Opublikuj” zostaje szkicem.',
        'wstecz' => ['href' => '?m=aktualnosci', 'etykieta' => 'Aktualności'],
    ];
    // Link do wpisu na stronie tylko dla zapisanego, opublikowanego wpisu (szkic nie jest widoczny publicznie).
    if (!empty($edit['id']) && !empty($edit['opublikowany']) && !empty($edit['slug'])) {
        $pmgNaglowek['akcje'] = [['href' => '../aktualnosci.html#wpis-' . rawurlencode((string) $edit['slug']), 'etykieta' => 'Zobacz na stronie', 'rodzaj' => 'secondary', 'nowaKarta' => true]];
    }
} else {
    // Szukanie po tytule: q przycięte do 100 znaków, a znaki specjalne LIKE (% _ \) są traktowane jak zwykły tekst.
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    if ($q !== '') {
        $st = pmg_db()->prepare('SELECT id, data, tytul, opublikowany FROM pmg_aktualnosci WHERE tytul LIKE ? ESCAPE \'\\\\\' ORDER BY data DESC, id DESC');
        $st->execute(['%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%']);
        $wpisy = $st->fetchAll();
    } else {
        $wpisy = pmg_db()->query('SELECT id, data, tytul, opublikowany FROM pmg_aktualnosci ORDER BY data DESC, id DESC')->fetchAll();
    }
    $pmgNaglowek = [
        'akcje' => [
            ['href' => '?m=aktualnosci&nowy', 'etykieta' => '+ Nowy wpis', 'rodzaj' => 'primary'],
            ['href' => '../aktualnosci.html', 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($edit !== null): ?>
  <form class="pmg-card" method="post" enctype="multipart/form-data" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">

    <section class="pmg-form-section" aria-labelledby="sek-tresc">
      <h2 class="pmg-form-section__title" id="sek-tresc">Treść</h2>
      <label for="tytul">Tytuł</label>
      <input type="text" id="tytul" name="tytul" maxlength="200" value="<?= $v('tytul') ?>" required data-pmg-licznik<?= blad_pola('tytul') ?>><?= komunikat_pola('tytul') ?>
      <label for="lead">Lead (akapit pod tytułem w artykule)</label>
      <p class="pmg-hint" id="lead_h">1–2 zdania wprowadzenia, wyróżnione nad zdjęciem. Maks. 600 znaków.</p>
      <textarea id="lead" name="lead" maxlength="600" rows="3"<?= blad_pola('lead', 'lead_h') ?> required data-pmg-licznik><?= $v('lead') ?></textarea><?= komunikat_pola('lead') ?>
      <label for="zajawka">Zajawka <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="zajawka_h">Krótki tekst na kafelku na liście wpisów i na stronie głównej. Puste pole = na kafelku pojawia się lead. Maks. 400 znaków.</p>
      <input type="text" id="zajawka" name="zajawka" maxlength="400" value="<?= $v('zajawka') ?>" aria-describedby="zajawka_h" data-pmg-licznik>
      <label for="tresc">Treść</label>
      <p class="pmg-hint" id="tresc_h">Akapity oddzielaj pustą linią. Śródtytuł: linia zaczynająca się od <code>## </code>. Bez HTML — znaczniki pokażą się jako zwykły tekst.</p>
      <textarea id="tresc" name="tresc"<?= blad_pola('tresc', 'tresc_h') ?> required><?= $v('tresc') ?></textarea><?= komunikat_pola('tresc') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-data">
      <h2 class="pmg-form-section__title" id="sek-data">Data, kolor i autor</h2>
      <label for="kolor">Kolor wpisu (akcent na kafelku)</label>
      <select id="kolor" name="kolor"><?php foreach (KOLORY as $k => $n): ?><option value="<?= $k ?>"<?= ($edit['kolor'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
      <label for="data">Data wpisu</label>
      <input type="date" id="data" name="data" value="<?= $v('data') ?>" required<?= blad_pola('data') ?>><?= komunikat_pola('data') ?>
      <label for="autor">Autor <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="autor_h">Np. Sekcja Marketing.</p>
      <input type="text" id="autor" name="autor" maxlength="100" value="<?= $v('autor') ?>" aria-describedby="autor_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zdjecie">
      <h2 class="pmg-form-section__title" id="sek-zdjecie">Zdjęcie</h2>
      <label for="zdjecie">Zdjęcie 16:9 (JPG, PNG albo WebP)</label>
      <p class="pmg-hint" id="zdjecie_h">Maks. 10 MB. Zdjęcie zostanie przycięte do proporcji 16:9 (ze środka). Najlepiej wgraj zdjęcie w tych proporcjach. Np. 1600 × 900 px.</p>
      <?php if (!empty($edit['zdjecie'])): ?>
        <figure class="pmg-photo pmg-photo--16x9"><img src="../<?= h($edit['zdjecie']) ?>" alt=""><figcaption class="pmg-hint">Obecne zdjęcie. Wgranie nowego pliku zastąpi to zdjęcie.</figcaption></figure>
      <?php endif; ?>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp"<?= blad_pola('zdjecie', 'zdjecie_h') ?>><?= komunikat_pola('zdjecie') ?>
      <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
      <p class="pmg-hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
      <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>"<?= blad_pola('zdjecie_alt', 'zdjecie_alt_h') ?>><?= komunikat_pola('zdjecie_alt') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-publikacja">
      <h2 class="pmg-form-section__title" id="sek-publikacja">Publikacja</h2>
      <label class="pmg-check"><input type="checkbox" name="opublikowany" value="1"<?= !empty($edit['opublikowany']) ? ' checked' : '' ?> aria-describedby="opublikowany_h"><span>Opublikuj na stronie</span></label>
      <p class="pmg-hint pmg-hint--check" id="opublikowany_h">Bez zaznaczenia wpis zostaje szkicem — niewidoczny na stronie.</p>
    </section>

    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=aktualnosci">Anuluj</a>
    </div>
  </form>

  <div class="pmg-card" id="galeria">
    <div class="pmg-card__head"><h2 class="pmg-h2">Galeria</h2></div>
  <?php if ($edit['id']): ?>
    <p class="pmg-hint">Zdjęcia 16:9 pod treścią artykułu, w małych kafelkach; po kliknięciu powiększają się. Kolejność zmieniasz strzałkami. Do <?= AKT_GALERIA_MAX ?> zdjęć. Bez zdjęć sekcji galerii nie ma. Każde zdjęcie zapisuje się własnym przyciskiem; zmian we wpisie powyżej te przyciski nie zapisują, więc najpierw kliknij „Zapisz” przy wpisie.</p>
    <?php foreach ($galeria as $i => $g): ?>
      <form class="pmg-gal" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="wpis_id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
        <figure class="pmg-photo pmg-photo--16x9 pmg-gal__foto"><img src="../<?= h($g['zdjecie']) ?>" alt=""></figure>
        <div class="pmg-gal__pola">
          <label for="gal-podpis-<?= (int) $g['id'] ?>">Podpis zdjęcia <?= $i + 1 ?> (także opis dla osób niewidomych)</label>
          <input type="text" id="gal-podpis-<?= (int) $g['id'] ?>" name="podpis" maxlength="200" value="<?= h($g['podpis']) ?>" required<?= blad_pola('gal-podpis-' . (int) $g['id']) ?>><?= komunikat_pola('gal-podpis-' . (int) $g['id']) ?>
          <label for="gal-plik-<?= (int) $g['id'] ?>">Podmień plik <span class="pmg-opt">(opcjonalnie)</span></label>
          <input type="file" id="gal-plik-<?= (int) $g['id'] ?>" name="plik" accept="image/jpeg,image/png,image/webp"<?= blad_pola('gal-plik-' . (int) $g['id']) ?>><?= komunikat_pola('gal-plik-' . (int) $g['id']) ?>
          <div class="pmg-gal__akcje">
            <button class="pmg-btn pmg-btn--primary pmg-btn--sm" type="submit" name="a" value="gal_zapisz">Zapisz zdjęcie</button>
            <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gal_gora" formnovalidate<?= $i === 0 ? ' disabled' : '' ?> aria-label="Przesuń wyżej: zdjęcie <?= $i + 1 ?>"><span aria-hidden="true">↑</span></button>
            <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gal_dol" formnovalidate<?= $i === count($galeria) - 1 ? ' disabled' : '' ?> aria-label="Przesuń niżej: zdjęcie <?= $i + 1 ?>"><span aria-hidden="true">↓</span></button>
            <button class="pmg-btn pmg-btn--danger pmg-btn--sm" type="submit" name="a" value="gal_usun" formnovalidate data-pmg-potwierdz="Usunąć zdjęcie <?= $i + 1 ?> z galerii? Tego nie da się cofnąć.">Usuń zdjęcie<span class="pmg-vh"> <?= $i + 1 ?></span></button>
          </div>
        </div>
      </form>
    <?php endforeach; ?>
    <?php if (count($galeria) < AKT_GALERIA_MAX): ?>
      <form class="pmg-gal pmg-gal--nowe" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="wpis_id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="a" value="gal_dodaj">
        <h3 class="pmg-gal__tytul">Dodaj zdjęcie</h3>
        <label for="gal-nowe-plik">Zdjęcie 16:9 (JPG, PNG albo WebP)</label>
        <p class="pmg-hint" id="gal-nowe-h">Maks. 10 MB. Zdjęcie zostanie przycięte do proporcji 16:9 (ze środka). Najlepiej wgraj zdjęcie w tych proporcjach. Np. 1200 × 675 px.</p>
        <input type="file" id="gal-nowe-plik" name="plik" accept="image/jpeg,image/png,image/webp" required<?= blad_pola('gal-nowe-plik', 'gal-nowe-h') ?>><?= komunikat_pola('gal-nowe-plik') ?>
        <label for="gal-nowe-podpis">Podpis zdjęcia (także opis dla osób niewidomych)</label>
        <input type="text" id="gal-nowe-podpis" name="podpis" maxlength="200" required<?= blad_pola('gal-nowe-podpis') ?>><?= komunikat_pola('gal-nowe-podpis') ?>
        <div class="pmg-form-actions"><button class="pmg-btn pmg-btn--secondary" type="submit">Dodaj do galerii</button></div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p class="pmg-hint">Galerię zdjęć dodasz po pierwszym zapisaniu wpisu: zapisz go, a potem otwórz z listy Aktualności.</p>
  <?php endif; ?>
  </div>

  <?php if ($edit['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Wpis, jego zdjęcie i galeria znikną ze strony i z panelu. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń ten wpis na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń wpis</button>
    </form>
  <?php endif; ?>

<?php else: ?>
  <?php pmg_import_blok('aktualnosci'); ?>
  <form class="pmg-card" method="get" role="search">
    <input type="hidden" name="m" value="aktualnosci">
    <label for="szukaj">Szukaj po tytule</label>
    <input type="search" id="szukaj" name="q" maxlength="100" value="<?= h($q) ?>">
    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--secondary" type="submit">Szukaj</button>
      <?php if ($q !== ''): ?><a class="pmg-btn pmg-btn--secondary" href="?m=aktualnosci">Wyczyść</a><?php endif; ?>
    </div>
  </form>
  <div class="pmg-table-wrap">
    <table class="pmg-table pmg-table--klikalna">
      <caption class="pmg-vh">Wpisy Aktualności</caption>
      <thead><tr><th scope="col">Tytuł</th><th scope="col">Data</th><th scope="col">Status</th></tr></thead>
      <tbody>
      <?php foreach ($wpisy as $r): ?>
        <tr>
          <td class="pmg-td-main" data-label="Tytuł"><a class="pmg-row-link" href="?m=aktualnosci&id=<?= (int) $r['id'] ?>"><?= h($r['tytul']) ?></a></td>
          <td class="pmg-num" data-label="Data"><time datetime="<?= h($r['data']) ?>"><?= h($r['data']) ?></time></td>
          <td data-label="Status"><?php if ($r['opublikowany']): ?><span class="pmg-chip pmg-chip--success">Opublikowany</span><?php else: ?><span class="pmg-chip pmg-chip--neutral">Szkic</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$wpisy && $q !== ''): ?>
        <tr><td colspan="3" class="pmg-empty">Nie znaleziono wpisów dla „<?= h($q) ?>”.</td></tr>
      <?php elseif (!$wpisy): ?>
        <tr><td colspan="3" class="pmg-empty">Nie ma jeszcze wpisów.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=aktualnosci&nowy">+ Dodaj pierwszy wpis</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
