<?php
// Podekran modułu Podcast: edycje podcastu (zespół edycji + kolejność grup na stronie). Wołany z _podcast.php (?m=podcast&w=edycje).
defined('PMG_PANEL') || exit;

const PODCAST_EDYCJE_ORDER = 'kolejnosc, id';

$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'gora' || $action === 'dol') {
        $id = (int) ($_POST['id'] ?? 0);
        if (przesun('pmg_podcast_edycje', PODCAST_EDYCJE_ORDER, $id, $action)) loguj('podcast', 'kolejnosc', $id);
        go('?m=podcast&w=edycje');

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // Jedno zapytanie: edycja z przypisanymi odcinkami nie zostanie usunięta (także gdy odcinek dodano w tej chwili).
        $st = pmg_db()->prepare('DELETE FROM pmg_podcast_edycje WHERE id = ? AND NOT EXISTS (SELECT 1 FROM pmg_odcinki WHERE edycja_id = ?)');
        $st->execute([$id, $id]);
        if ($st->rowCount() > 0) {
            loguj('podcast', 'usuniecie', $id);
            $_SESSION['flash'] = 'Edycja usunięta.';
            go('?m=podcast&w=edycje');
        }
        $st = pmg_db()->prepare('SELECT COUNT(*) FROM pmg_odcinki WHERE edycja_id = ?');
        $st->execute([$id]);
        $ile = (int) $st->fetchColumn();
        $error = $ile > 0
            ? 'Nie można usunąć edycji, bo należy do niej ' . pmg_odmiana($ile, 'odcinek', 'odcinki', 'odcinków') . '. Przenieś odcinki do innej edycji (w edycji odcinka) albo usuń je, a potem spróbuj ponownie.'
            : 'Nie znaleziono tej edycji.';
        $st = pmg_db()->prepare('SELECT * FROM pmg_podcast_edycje WHERE id = ?');
        $st->execute([$id]);
        $edit = $st->fetch() ?: null;

    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [];
        foreach (['lata' => 20, 'koordynator' => 200, 'mentorzy' => 400, 'zespol' => 800] as $k => $max) {
            $f[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        }
        $f['opis'] = mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['opis'] ?? ''))), 0, 600);
        $f['numer'] = (int) ($_POST['numer'] ?? 0);
        $f['widoczna'] = empty($_POST['widoczna']) ? 0 : 1;
        $old = null;
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_podcast_edycje WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        try {
            if ($f['numer'] < 1 || $f['numer'] > 999) throw new RuntimeException('Numer edycji: liczba od 1 do 999.');
            if ($f['lata'] === '') throw new RuntimeException('Podaj lata edycji, np. 2025/2026.');
            if ($id && $old) {
                $st = pmg_db()->prepare('UPDATE pmg_podcast_edycje SET numer=?, lata=?, koordynator=?, mentorzy=?, zespol=?, opis=?, widoczna=? WHERE id=?');
                $st->execute([$f['numer'], $f['lata'], $f['koordynator'], $f['mentorzy'], $f['zespol'], $f['opis'], $f['widoczna'], $id]);
                loguj('podcast', 'edycja', $id);
            } else {
                // Nowa edycja ląduje na górze listy (na stronie najnowsza edycja jest pierwsza).
                $kolejnosc = (int) pmg_db()->query('SELECT COALESCE(MIN(kolejnosc), 1) - 1 FROM pmg_podcast_edycje')->fetchColumn();
                $st = pmg_db()->prepare('INSERT INTO pmg_podcast_edycje (numer, lata, koordynator, mentorzy, zespol, opis, kolejnosc, widoczna) VALUES (?,?,?,?,?,?,?,?)');
                $st->execute([$f['numer'], $f['lata'], $f['koordynator'], $f['mentorzy'], $f['zespol'], $f['opis'], $kolejnosc, $f['widoczna']]);
                $id = (int) pmg_db()->lastInsertId();
                loguj('podcast', 'dodanie', $id);
            }
            $_SESSION['flash'] = $f['widoczna'] ? 'Zapisano. Edycja jest widoczna na stronie.' : 'Zapisano. Edycja jest ukryta na stronie (razem z jej odcinkami).';
            go('?m=podcast&w=edycje');
        } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
            $error = $e->getCode() === '23000' ? 'Edycja o tym numerze już istnieje.' : 'Błąd zapisu.';
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        }
    }
}

if ($edit === null) {
    if (isset($_GET['nowa'])) {
        $nastepny = (int) pmg_db()->query('SELECT COALESCE(MAX(numer), 0) + 1 FROM pmg_podcast_edycje')->fetchColumn();
        $edit = ['id' => 0, 'numer' => $nastepny, 'widoczna' => 1];
    } elseif (isset($_GET['id'])) {
        $st = pmg_db()->prepare('SELECT * FROM pmg_podcast_edycje WHERE id = ?');
        $st->execute([(int) $_GET['id']]);
        $edit = $st->fetch() ?: null;
    }
}
$v = function ($k) use (&$edit) { return h($edit[$k] ?? ''); };

if ($edit !== null) {
    $pmgNaglowek = [
        'tytul' => $edit['id'] ? 'Edytuj edycję ' . $edit['numer'] : 'Nowa edycja podcastu',
        'opis' => 'Zespół edycji pojawia się na stronie nad jej odcinkami.',
        'wstecz' => ['href' => '?m=podcast&w=edycje', 'etykieta' => 'Edycje podcastu'],
    ];
} else {
    $edycje = pmg_db()->query('SELECT e.id, e.numer, e.lata, e.widoczna, (SELECT COUNT(*) FROM pmg_odcinki o WHERE o.edycja_id = e.id) AS odcinkow FROM pmg_podcast_edycje e ORDER BY ' . PODCAST_EDYCJE_ORDER)->fetchAll();
    $pmgNaglowek = [
        'tytul' => 'Edycje podcastu',
        'opis' => 'Odcinki na stronie są pogrupowane według edycji, w kolejności z tej listy.',
        'wstecz' => ['href' => '?m=podcast', 'etykieta' => 'Podcast'],
        'akcje' => [
            ['href' => '?m=podcast&w=edycje&nowa', 'etykieta' => '+ Nowa edycja', 'rodzaj' => 'primary'],
            ['href' => '../podcast.html', 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($edit !== null): ?>
  <form class="pmg-card" method="post" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">

    <section class="pmg-form-section" aria-labelledby="sek-edycja">
      <h2 class="pmg-form-section__title" id="sek-edycja">Edycja</h2>
      <label for="numer">Numer edycji</label>
      <input type="number" id="numer" name="numer" min="1" max="999" value="<?= $v('numer') ?>" required>
      <label for="lata">Lata</label>
      <p class="pmg-hint" id="lata_h">Np. 2025/2026. Na stronie pojawią się w nawiasie obok numeru.</p>
      <input type="text" id="lata" name="lata" maxlength="20" value="<?= $v('lata') ?>" aria-describedby="lata_h" required>
      <label for="opis">Opis edycji <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="opis_h">Krótki tekst pod nagłówkiem edycji, maksymalnie 600 znaków.</p>
      <textarea id="opis" name="opis" maxlength="600" aria-describedby="opis_h" data-pmg-licznik><?= $v('opis') ?></textarea>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zespol">
      <h2 class="pmg-form-section__title" id="sek-zespol">Zespół edycji</h2>
      <label for="koordynator">Koordynator <span class="pmg-opt">(opcjonalnie)</span></label>
      <input type="text" id="koordynator" name="koordynator" maxlength="200" value="<?= $v('koordynator') ?>">
      <label for="mentorzy">Mentorzy <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="mentorzy_h">Imiona i nazwiska oddzielone przecinkami.</p>
      <input type="text" id="mentorzy" name="mentorzy" maxlength="400" value="<?= $v('mentorzy') ?>" aria-describedby="mentorzy_h">
      <label for="zespol">Zespół <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="zespol_h">Imiona i nazwiska oddzielone przecinkami. Puste pole jest pomijane na stronie.</p>
      <textarea id="zespol" name="zespol" maxlength="800" aria-describedby="zespol_h"><?= $v('zespol') ?></textarea>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-widocznosc">
      <h2 class="pmg-form-section__title" id="sek-widocznosc">Widoczność</h2>
      <label class="pmg-check"><input type="checkbox" name="widoczna" value="1"<?= !empty($edit['widoczna']) ? ' checked' : '' ?> aria-describedby="widoczna_h"><span>Pokaż edycję na stronie</span></label>
      <p class="pmg-hint pmg-hint--check" id="widoczna_h">Bez zaznaczenia edycja jest ukryta razem ze wszystkimi swoimi odcinkami. Kolejność edycji zmieniasz strzałkami na liście.</p>
    </section>

    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=podcast&amp;w=edycje">Anuluj</a>
    </div>
  </form>
  <?php if ($edit['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Można usunąć tylko edycję bez odcinków. Jeśli są do niej przypisane odcinki, najpierw przenieś je do innej edycji. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tę edycję na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń edycję</button>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="pmg-table-wrap">
    <table class="pmg-table pmg-table--klikalna">
      <caption class="pmg-vh">Edycje podcastu</caption>
      <thead><tr><th scope="col" class="pmg-num">Nr</th><th scope="col">Lata</th><th scope="col">Odcinki</th><th scope="col">Widoczność</th><th scope="col">Kolejność</th></tr></thead>
      <tbody>
      <?php foreach ($edycje as $i => $r): ?>
        <tr>
          <td class="pmg-num" data-label="Nr"><?= (int) $r['numer'] ?></td>
          <td class="pmg-td-main" data-label="Lata"><a class="pmg-row-link" href="?m=podcast&amp;w=edycje&amp;id=<?= (int) $r['id'] ?>">Edycja <?= (int) $r['numer'] ?><?= $r['lata'] !== '' ? ' (' . h($r['lata']) . ')' : '' ?></a></td>
          <td class="pmg-num" data-label="Odcinki"><?= (int) $r['odcinkow'] ?></td>
          <td data-label="Widoczność"><?php if ($r['widoczna']): ?><span class="pmg-chip pmg-chip--success">Widoczna</span><?php else: ?><span class="pmg-chip pmg-chip--neutral">Ukryta</span><?php endif; ?></td>
          <td class="pmg-td-actions" data-label="Kolejność">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gora"<?= $i === 0 ? ' disabled' : '' ?> aria-label="Przesuń wyżej: edycja <?= (int) $r['numer'] ?>"><span aria-hidden="true">↑</span></button>
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="dol"<?= $i === count($edycje) - 1 ? ' disabled' : '' ?> aria-label="Przesuń niżej: edycja <?= (int) $r['numer'] ?>"><span aria-hidden="true">↓</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$edycje): ?>
        <tr><td colspan="5" class="pmg-empty">Nie ma jeszcze edycji podcastu.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=podcast&amp;w=edycje&amp;nowa">+ Dodaj pierwszą edycję</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
