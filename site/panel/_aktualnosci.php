<?php
// Moduł Aktualności: lista, dodawanie / edycja / usuwanie wpisów. Wołany wyłącznie z index.php (?m=aktualnosci).
defined('PMG_PANEL') || exit;

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
        $st = pmg_db()->prepare('SELECT zdjecie FROM pmg_aktualnosci WHERE id = ?');
        $st->execute([$id]);
        $img = $st->fetchColumn();
        pmg_db()->prepare('DELETE FROM pmg_aktualnosci WHERE id = ?')->execute([$id]);
        drop_image($img ?: null);
        loguj('aktualnosci', 'usuniecie', $id);
        $_SESSION['flash'] = 'Wpis usunięty.';
        go('?m=aktualnosci');
    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [];
        foreach (['tytul' => 200, 'zajawka' => 400, 'kategoria' => 40, 'autor' => 100, 'zdjecie_alt' => 200] as $k => $max) {
            $f[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        }
        $f['tresc'] = trim(str_replace("\r\n", "\n", (string) ($_POST['tresc'] ?? '')));
        $f['data'] = (string) ($_POST['data'] ?? '');
        $f['kolor'] = array_key_exists((string) ($_POST['kolor'] ?? ''), KOLORY) ? $_POST['kolor'] : 'pink';
        $f['opublikowany'] = empty($_POST['opublikowany']) ? 0 : 1;
        $old = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        try {
            if ($f['tytul'] === '' || $f['zajawka'] === '' || $f['tresc'] === '' || $f['kategoria'] === '') throw new RuntimeException('Uzupełnij tytuł, kategorię, zajawkę i treść.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['data'])) throw new RuntimeException('Podaj datę wpisu.');
            $stareZdjecie = $old['zdjecie'] ?? null;
            $f['zdjecie'] = zdjecie('aktualnosci', $stareZdjecie);
            if ($id && $old) {
                $st = pmg_db()->prepare('UPDATE pmg_aktualnosci SET data=?, kategoria=?, kolor=?, tytul=?, zajawka=?, tresc=?, zdjecie=?, zdjecie_alt=?, autor=?, opublikowany=? WHERE id=?');
                $st->execute([$f['data'], $f['kategoria'], $f['kolor'], $f['tytul'], $f['zajawka'], $f['tresc'], $f['zdjecie'], $f['zdjecie_alt'], $f['autor'], $f['opublikowany'], $id]);
                if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                loguj('aktualnosci', 'edycja', $id);
            } else {
                $base = $slug = slugify($f['tytul']);
                $st = pmg_db()->prepare('SELECT 1 FROM pmg_aktualnosci WHERE slug = ?');
                for ($n = 2; $st->execute([$slug]) && $st->fetchColumn(); $n++) $slug = $base . '-' . $n;
                $st = pmg_db()->prepare('INSERT INTO pmg_aktualnosci (slug, data, kategoria, kolor, tytul, zajawka, tresc, zdjecie, zdjecie_alt, autor, opublikowany) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$slug, $f['data'], $f['kategoria'], $f['kolor'], $f['tytul'], $f['zajawka'], $f['tresc'], $f['zdjecie'], $f['zdjecie_alt'], $f['autor'], $f['opublikowany']]);
                $id = (int) pmg_db()->lastInsertId();
                loguj('aktualnosci', 'dodanie', $id);
            }
            $_SESSION['flash'] = $f['opublikowany'] ? 'Zapisano i opublikowano.' : 'Zapisano jako szkic (niewidoczny na stronie).';
            go('?m=aktualnosci');
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
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

if ($edit !== null) {
    $pmgNaglowek = [
        'tytul' => $edit['id'] ? 'Edytuj wpis' : 'Nowy wpis',
        'opis' => $edit['id'] ? (string) $edit['tytul'] : 'Wpis bez zaznaczenia „Opublikuj” zostaje szkicem.',
        'wstecz' => ['href' => '?m=aktualnosci', 'etykieta' => 'Aktualności'],
    ];
} else {
    $wpisy = pmg_db()->query('SELECT id, data, tytul, opublikowany FROM pmg_aktualnosci ORDER BY data DESC, id DESC')->fetchAll();
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
      <input type="text" id="tytul" name="tytul" maxlength="200" value="<?= $v('tytul') ?>" required data-pmg-licznik>
      <label for="zajawka">Zajawka (1–2 zdania na liście wpisów)</label>
      <input type="text" id="zajawka" name="zajawka" maxlength="400" value="<?= $v('zajawka') ?>" required data-pmg-licznik>
      <label for="tresc">Treść</label>
      <p class="pmg-hint" id="tresc_h">Akapity oddzielaj pustą linią. Śródtytuł: linia zaczynająca się od <code>## </code>. Bez HTML — znaczniki pokażą się jako zwykły tekst.</p>
      <textarea id="tresc" name="tresc" aria-describedby="tresc_h" required><?= $v('tresc') ?></textarea>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-kategoria">
      <h2 class="pmg-form-section__title" id="sek-kategoria">Kategoria i data</h2>
      <label for="kategoria">Kategoria</label>
      <p class="pmg-hint" id="kategoria_h">Np. Życie koła, Wydarzenie, Rekrutacja.</p>
      <input type="text" id="kategoria" name="kategoria" maxlength="40" value="<?= $v('kategoria') ?>" required aria-describedby="kategoria_h">
      <label for="kolor">Kolor etykiety kategorii</label>
      <select id="kolor" name="kolor"><?php foreach (KOLORY as $k => $n): ?><option value="<?= $k ?>"<?= ($edit['kolor'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
      <label for="data">Data wpisu</label>
      <input type="date" id="data" name="data" value="<?= $v('data') ?>" required>
      <label for="autor">Autor <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="autor_h">Np. Sekcja Marketing.</p>
      <input type="text" id="autor" name="autor" maxlength="100" value="<?= $v('autor') ?>" aria-describedby="autor_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zdjecie">
      <h2 class="pmg-form-section__title" id="sek-zdjecie">Zdjęcie</h2>
      <label for="zdjecie">Zdjęcie 16:9 (JPG, PNG albo WebP)</label>
      <p class="pmg-hint" id="zdjecie_h">Maks. 10 MB. Proporcje 16:9, np. 1600 × 900 px.</p>
      <?php if (!empty($edit['zdjecie'])): ?>
        <figure class="pmg-photo pmg-photo--16x9"><img src="../<?= h($edit['zdjecie']) ?>" alt=""><figcaption class="pmg-hint">Obecne zdjęcie. Wgranie nowego pliku zastąpi to zdjęcie.</figcaption></figure>
      <?php endif; ?>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp" aria-describedby="zdjecie_h">
      <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
      <p class="pmg-hint" id="zdjecie_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
      <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>" aria-describedby="zdjecie_alt_h">
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
  <?php if ($edit['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Wpis i jego zdjęcie znikną ze strony i z panelu. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń ten wpis na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń wpis</button>
    </form>
  <?php endif; ?>

<?php else: ?>
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
      <?php if (!$wpisy): ?>
        <tr><td colspan="3" class="pmg-empty">Nie ma jeszcze wpisów.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=aktualnosci&nowy">+ Dodaj pierwszy wpis</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
