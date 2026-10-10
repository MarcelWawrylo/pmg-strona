<?php
// Moduł Podcast: lista odcinków, dodawanie / edycja / usuwanie, kolejność (strzałki). Wołany wyłącznie z index.php (?m=podcast).
defined('PMG_PANEL') || exit;

const PODCAST_ORDER = 'kolejnosc, numer, id';

// ID odcinka Spotify (22 znaki) z samego ID albo z wklejonego adresu odcinka; '' = brak; null = nie rozpoznano.
// Do bazy trafia wyłącznie ID — adres odtwarzacza składa strona, więc nie da się wstawić obcego adresu do ramki.
function podcast_spotify_id($v)
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('~^(?:https://open\.spotify\.com/(?:intl-[a-z]{2}/)?episode/)?([A-Za-z0-9]{22})(?:[?/#].*)?$~', $v, $m)) return $m[1];
    return null;
}

// Podekran „Edycje podcastu” (?m=podcast&w=edycje) ma osobny plik; poniżej zostaje obsługa odcinków.
if (($_GET['w'] ?? '') === 'edycje') {
    require __DIR__ . '/_podcast_edycje.php';
    return;
}

$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'gora' || $action === 'dol') {
        $id = (int) ($_POST['id'] ?? 0);
        // Strzałki działają w obrębie edycji podcastu — tak jak lista na stronie (odcinki są tam pogrupowane w edycje).
        $st = pmg_db()->prepare('SELECT edycja_id FROM pmg_odcinki WHERE id = ?');
        $st->execute([$id]);
        $wiersz = $st->fetch();
        if ($wiersz && przesun('pmg_odcinki', PODCAST_ORDER, $id, $action, 'edycja_id', $wiersz['edycja_id'] === null ? null : (int) $wiersz['edycja_id'])) loguj('podcast', 'kolejnosc', $id, nazwa_rekordu("SELECT CONCAT('Odcinek ', numer, ': ', tytul) FROM pmg_odcinki WHERE id = ?", $id));
        go_po_przesunieciu('?m=podcast', $id, $action);

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = pmg_db()->prepare('SELECT zdjecie, numer, tytul FROM pmg_odcinki WHERE id = ?'); // tytuł do dziennika, zanim wiersz zniknie
        $st->execute([$id]);
        $odc = $st->fetch();
        $img = $odc ? $odc['zdjecie'] : null;
        $st = pmg_db()->prepare('DELETE FROM pmg_odcinki WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) { // A2 6.5: bez wpisu w dzienniku, gdy nic nie usunięto
            $_SESSION['flash'] = 'Nie znaleziono odcinka — nic nie usunięto.';
            go('?m=podcast');
        }
        drop_image($img ?: null);
        loguj('podcast', 'usuniecie', $id, 'Odcinek ' . $odc['numer'] . ': ' . $odc['tytul']);
        $_SESSION['flash'] = 'Odcinek usunięty.';
        go('?m=podcast');

    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [];
        foreach (['tytul' => 200, 'prowadzacy' => 200, 'gosc' => 200, 'gosc_bio' => 1000, 'zdjecie_alt' => 200] as $k => $max) {
            $f[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        }
        $f['opis'] = mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['opis'] ?? ''))), 0, 5000);
        $f['numer'] = (int) ($_POST['numer'] ?? 0);
        $f['data'] = (string) ($_POST['data'] ?? '');
        $czas = trim((string) ($_POST['czas_min'] ?? ''));
        $f['czas_min'] = $czas === '' ? null : (int) $czas;
        $f['spotify_id'] = (string) ($_POST['spotify_id'] ?? '');
        $f['apple_url'] = trim((string) ($_POST['apple_url'] ?? ''));
        $f['youtube_url'] = trim((string) ($_POST['youtube_url'] ?? ''));
        $f['opublikowany'] = empty($_POST['opublikowany']) ? 0 : 1;
        $f['edycja_id'] = (string) ($_POST['edycja_id'] ?? '') === '' ? null : (int) $_POST['edycja_id'];
        $old = null;
        $noweZdjecie = null; // plik wgrany w tym żądaniu — usuwany, jeśli zapis do bazy się nie uda
        if ($id) {
            $st = pmg_db()->prepare('SELECT * FROM pmg_odcinki WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch() ?: null;
        }
        // Każde pole sprawdzane osobno, w kolejności pól formularza — użytkownik widzi wszystkie błędy naraz.
        if ($f['numer'] < 1 || $f['numer'] > 9999) $bledyPol['numer'] = 'Numer odcinka: liczba od 1 do 9999.';
        if ($f['tytul'] === '') $bledyPol['tytul'] = 'Uzupełnij tytuł odcinka.';
        if (!data_ok($f['data'])) $bledyPol['data'] = 'Podaj poprawną datę odcinka.';
        if ($f['czas_min'] !== null && ($f['czas_min'] < 1 || $f['czas_min'] > 999)) $bledyPol['czas_min'] = 'Czas trwania: liczba minut od 1 do 999 albo puste pole.';
        if ($f['opis'] === '') $bledyPol['opis'] = 'Uzupełnij opis odcinka.';
        if ($f['edycja_id'] !== null) {
            $st = pmg_db()->prepare('SELECT COUNT(*) FROM pmg_podcast_edycje WHERE id = ?');
            $st->execute([$f['edycja_id']]);
            if (!(int) $st->fetchColumn()) $bledyPol['edycja_id'] = 'Wybierz edycję podcastu z listy.';
        } elseif ((int) pmg_db()->query('SELECT COUNT(*) FROM pmg_podcast_edycje')->fetchColumn() > 0) {
            $bledyPol['edycja_id'] = 'Wybierz edycję podcastu, do której należy odcinek.';
        }
        $spotify = podcast_spotify_id($f['spotify_id']);
        if ($spotify === null) $bledyPol['spotify_id'] = 'Spotify: wklej adres odcinka (https://open.spotify.com/episode/…) albo samo 22-znakowe ID odcinka.';
        else $f['spotify_id'] = $spotify;
        if ($f['apple_url'] !== '' && (!url_ok($f['apple_url']) || strpos($f['apple_url'], 'https://podcasts.apple.com/') !== 0)) $bledyPol['apple_url'] = 'Apple Podcasts: adres musi zaczynać się od https://podcasts.apple.com/ (albo zostaw puste pole).';
        elseif (mb_strlen($f['apple_url']) > 400) $bledyPol['apple_url'] = 'Adres jest za długi.';
        if ($f['youtube_url'] !== '' && (!url_ok($f['youtube_url']) || !preg_match('~^https://(www\.|music\.)?(youtube\.com|youtu\.be)/~', $f['youtube_url']))) $bledyPol['youtube_url'] = 'YouTube: adres musi zaczynać się od https://www.youtube.com/ albo https://youtu.be/ (albo zostaw puste pole).';
        elseif (mb_strlen($f['youtube_url']) > 300) $bledyPol['youtube_url'] = 'Adres jest za długi.';
        blad_opisu_zdjecia($old['zdjecie'] ?? null);
        try {
            if ($bledyPol) throw new BladPol();
            $stareZdjecie = $old['zdjecie'] ?? null;
            $f['zdjecie'] = zdjecie('podcast', $stareZdjecie);
            if ($f['zdjecie'] !== $stareZdjecie) $noweZdjecie = $f['zdjecie'];
            if ($id && $old) {
                $st = pmg_db()->prepare('UPDATE pmg_odcinki SET numer=?, tytul=?, data=?, czas_min=?, opis=?, prowadzacy=?, gosc=?, gosc_bio=?, spotify_id=?, apple_url=?, youtube_url=?, zdjecie=?, zdjecie_alt=?, opublikowany=?, edycja_id=? WHERE id=?');
                $st->execute([$f['numer'], $f['tytul'], $f['data'], $f['czas_min'], $f['opis'], $f['prowadzacy'], $f['gosc'], $f['gosc_bio'], $f['spotify_id'], $f['apple_url'], $f['youtube_url'], $f['zdjecie'], $f['zdjecie_alt'], $f['opublikowany'], $f['edycja_id'], $id]);
                $noweZdjecie = null;
                if ((string) $old['edycja_id'] !== (string) $f['edycja_id']) { // przeniesiony do innej edycji: na koniec jej listy
                    $kolejnosc = (int) pmg_db()->query('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_odcinki')->fetchColumn();
                    pmg_db()->prepare('UPDATE pmg_odcinki SET kolejnosc = ? WHERE id = ?')->execute([$kolejnosc, $id]);
                }
                if ($f['zdjecie'] !== $stareZdjecie) drop_image($stareZdjecie);
                loguj('podcast', 'edycja', $id, 'Odcinek ' . $f['numer'] . ': ' . $f['tytul']);
            } else {
                $kolejnosc = (int) pmg_db()->query('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_odcinki')->fetchColumn();
                $st = pmg_db()->prepare('INSERT INTO pmg_odcinki (numer, tytul, data, czas_min, opis, prowadzacy, gosc, gosc_bio, spotify_id, apple_url, youtube_url, zdjecie, zdjecie_alt, kolejnosc, opublikowany, edycja_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$f['numer'], $f['tytul'], $f['data'], $f['czas_min'], $f['opis'], $f['prowadzacy'], $f['gosc'], $f['gosc_bio'], $f['spotify_id'], $f['apple_url'], $f['youtube_url'], $f['zdjecie'], $f['zdjecie_alt'], $kolejnosc, $f['opublikowany'], $f['edycja_id']]);
                $noweZdjecie = null;
                $id = (int) pmg_db()->lastInsertId();
                loguj('podcast', 'dodanie', $id, 'Odcinek ' . $f['numer'] . ': ' . $f['tytul']);
            }
            $_SESSION['flash'] = $f['opublikowany'] ? 'Zapisano i opublikowano. Na stronie zmiana pojawi się w ciągu 5 minut.' : 'Zapisano jako szkic (niewidoczny na stronie).';
            go('?m=podcast');
        } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
            drop_image($noweZdjecie);
            $error = $e->getCode() === '23000' ? 'Odcinek o tym numerze już istnieje.' : 'Błąd zapisu.';
            $edit = array_merge($old ?: [], $f, ['id' => $id, 'zdjecie' => $old['zdjecie'] ?? null]);
        } catch (BladPliku $e) {
            $bledyPol['zdjecie'] = $e->getMessage();
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        } catch (BladPol $e) { // błędy pól są już w $bledyPol
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        }
    }
}

if ($edit === null) {
    if (isset($_GET['nowy'])) {
        $nastepny = (int) pmg_db()->query('SELECT COALESCE(MAX(numer), 0) + 1 FROM pmg_odcinki')->fetchColumn();
        // Domyślnie najnowsza edycja (pierwsza w kolejności z panelu).
        $pierwsza = pmg_db()->query('SELECT id FROM pmg_podcast_edycje ORDER BY kolejnosc, id LIMIT 1')->fetchColumn();
        $edit = ['id' => 0, 'numer' => $nastepny, 'data' => date('Y-m-d'), 'opublikowany' => 0, 'edycja_id' => $pierwsza === false ? null : (int) $pierwsza];
    } elseif (isset($_GET['id'])) {
        $st = pmg_db()->prepare('SELECT * FROM pmg_odcinki WHERE id = ?');
        $st->execute([(int) $_GET['id']]);
        $edit = $st->fetch() ?: null;
    }
}
$v = function ($k) use (&$edit) { return h($edit[$k] ?? ''); };
$edycjePodcastu = $edit !== null
    ? pmg_db()->query('SELECT id, numer, lata FROM pmg_podcast_edycje ORDER BY kolejnosc, id')->fetchAll()
    : [];

if ($edit !== null) {
    $pmgNaglowek = [
        'tytul' => $edit['id'] ? 'Edytuj odcinek' : 'Nowy odcinek',
        'opis' => $edit['id'] ? (string) $edit['tytul'] : 'Odcinek bez zaznaczenia „Opublikuj na stronie” zostaje szkicem.',
        'wstecz' => ['href' => '?m=podcast', 'etykieta' => 'Odcinki podcastu'],
    ];
    // Strona podcastu nie ma kotwic do odcinków (okno odcinka otwiera się kliknięciem), więc link prowadzi do całej listy.
    if (!empty($edit['id']) && !empty($edit['opublikowany'])) {
        $pmgNaglowek['akcje'] = [['href' => '../podcast.html', 'etykieta' => 'Zobacz na stronie', 'rodzaj' => 'secondary', 'nowaKarta' => true]];
    }
} else {
    // Kolejność listy = kolejność na stronie: edycje w kolejności z panelu, w każdej odcinki wg strzałek (numer rozstrzyga tylko remisy).
    $odcinki = pmg_db()->query('SELECT o.id, o.numer, o.tytul, o.data, o.opublikowany, o.edycja_id, e.numer AS edycja_numer FROM pmg_odcinki o LEFT JOIN pmg_podcast_edycje e ON e.id = o.edycja_id ORDER BY e.kolejnosc IS NULL, e.kolejnosc, e.id, o.kolejnosc, o.numer, o.id')->fetchAll();
    $pmgNaglowek = [
        'akcje' => [
            ['href' => '?m=podcast&nowy', 'etykieta' => '+ Nowy odcinek', 'rodzaj' => 'primary'],
            ['href' => '../podcast.html', 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($edit !== null): ?>
  <form class="pmg-card" method="post" enctype="multipart/form-data" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">

    <section class="pmg-form-section" aria-labelledby="sek-odcinek">
      <h2 class="pmg-form-section__title" id="sek-odcinek">Odcinek</h2>
      <label for="numer">Numer odcinka</label>
      <p class="pmg-hint" id="numer_h">Oznaczenie odcinka na stronie (np. „Odcinek 12”). Nie wpływa na kolejność — tę zmieniasz strzałkami na liście odcinków.</p>
      <input type="number" id="numer" name="numer" min="1" max="9999" value="<?= $v('numer') ?>" required<?= blad_pola('numer', 'numer_h') ?>><?= komunikat_pola('numer') ?>
      <label for="tytul">Tytuł</label>
      <input type="text" id="tytul" name="tytul" maxlength="200" value="<?= $v('tytul') ?>" required data-pmg-licznik<?= blad_pola('tytul') ?>><?= komunikat_pola('tytul') ?>
      <label for="data">Data publikacji odcinka</label>
      <input type="date" id="data" name="data" value="<?= $v('data') ?>" required<?= blad_pola('data') ?>><?= komunikat_pola('data') ?>
      <label for="czas_min">Czas trwania w minutach <span class="pmg-opt">(opcjonalnie)</span></label>
      <input type="number" id="czas_min" name="czas_min" min="1" max="999" value="<?= $v('czas_min') ?>"<?= blad_pola('czas_min') ?>><?= komunikat_pola('czas_min') ?>
      <label for="opis">Opis odcinka</label>
      <p class="pmg-hint" id="opis_h">Akapity oddzielaj pustą linią. Bez HTML — znaczniki pokażą się jako zwykły tekst.</p>
      <textarea id="opis" name="opis" maxlength="5000"<?= blad_pola('opis', 'opis_h') ?> required><?= $v('opis') ?></textarea><?= komunikat_pola('opis') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-edycja">
      <h2 class="pmg-form-section__title" id="sek-edycja">Edycja podcastu</h2>
      <label for="edycja_id">Do której edycji należy odcinek</label>
      <p class="pmg-hint" id="edycja_h">Na stronie odcinki są pogrupowane według edycji. Edycje dodasz w <a href="?m=podcast&amp;w=edycje">Edycje podcastu</a>.</p>
      <select id="edycja_id" name="edycja_id"<?= blad_pola('edycja_id', 'edycja_h') ?><?= $edycjePodcastu ? ' required' : '' ?>>
        <option value=""<?= $edycjePodcastu ? ' disabled' : '' ?><?= ($edit['edycja_id'] ?? null) === null ? ' selected' : '' ?>><?= $edycjePodcastu ? 'Wybierz edycję' : 'Brak edycji — najpierw dodaj edycję podcastu' ?></option>
        <?php foreach ($edycjePodcastu as $ep): ?>
          <option value="<?= (int) $ep['id'] ?>"<?= (int) ($edit['edycja_id'] ?? 0) === (int) $ep['id'] ? ' selected' : '' ?>>Edycja <?= (int) $ep['numer'] ?><?= $ep['lata'] !== '' ? ' (' . h($ep['lata']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select><?= komunikat_pola('edycja_id') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-goscie">
      <h2 class="pmg-form-section__title" id="sek-goscie">Rozmówcy</h2>
      <label for="prowadzacy">Prowadzący <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="prowadzacy_h">Np. Michał Miszczuk, Szymon Adamczyk.</p>
      <input type="text" id="prowadzacy" name="prowadzacy" maxlength="200" value="<?= $v('prowadzacy') ?>" aria-describedby="prowadzacy_h">
      <label for="gosc">Gość <span class="pmg-opt">(opcjonalnie)</span></label>
      <input type="text" id="gosc" name="gosc" maxlength="200" value="<?= $v('gosc') ?>">
      <label for="gosc_bio">O gościu <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="gosc_bio_h">Krótki biogram, maksymalnie 1000 znaków.</p>
      <textarea id="gosc_bio" name="gosc_bio" maxlength="1000" aria-describedby="gosc_bio_h" data-pmg-licznik><?= $v('gosc_bio') ?></textarea>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-linki">
      <h2 class="pmg-form-section__title" id="sek-linki">Odtwarzacze i linki</h2>
      <label for="spotify_id">Spotify <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="spotify_h">Wklej adres odcinka ze Spotify (Udostępnij → Kopiuj link do odcinka) albo samo ID. Z niego strona zbuduje odtwarzacz i przycisk.</p>
      <input type="text" id="spotify_id" name="spotify_id" maxlength="200" value="<?= $v('spotify_id') ?>"<?= blad_pola('spotify_id', 'spotify_h') ?>><?= komunikat_pola('spotify_id') ?>
      <label for="apple_url">Apple Podcasts <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="apple_h">Adres zaczynający się od https://podcasts.apple.com/</p>
      <input type="text" id="apple_url" name="apple_url" maxlength="400" value="<?= $v('apple_url') ?>"<?= blad_pola('apple_url', 'apple_h') ?>><?= komunikat_pola('apple_url') ?>
      <label for="youtube_url">YouTube <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="youtube_h">Adres zaczynający się od https://www.youtube.com/ albo https://youtu.be/</p>
      <input type="text" id="youtube_url" name="youtube_url" maxlength="300" value="<?= $v('youtube_url') ?>"<?= blad_pola('youtube_url', 'youtube_h') ?>><?= komunikat_pola('youtube_url') ?>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-zdjecie">
      <h2 class="pmg-form-section__title" id="sek-zdjecie">Zdjęcie</h2>
      <label for="zdjecie">Zdjęcie 16:9 (JPG, PNG albo WebP) <span class="pmg-opt">(opcjonalnie)</span></label>
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
      <p class="pmg-hint pmg-hint--check" id="opublikowany_h">Bez zaznaczenia = szkic, niewidoczny na stronie. Kolejność zmieniasz strzałkami na liście odcinków.</p>
    </section>

    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=podcast">Anuluj</a>
    </div>
  </form>
  <?php if ($edit['id']): ?>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Odcinek i jego zdjęcie znikną ze strony i z panelu. Tego nie da się cofnąć.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń ten odcinek na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń odcinek</button>
    </form>
  <?php endif; ?>

<?php else: ?>
  <p class="pmg-hint">Odcinki pojawiają się na stronie w edycjach, w kolejności z tej listy (strzałki przesuwają odcinek w obrębie jego edycji). Numer odcinka jest tylko jego oznaczeniem i nie wpływa na kolejność. Gdy w panelu jest choć jeden odcinek (także szkic), lista na stronie pochodzi z panelu, a nie z kodu strony.</p>
  <div class="pmg-table-wrap">
    <table class="pmg-table pmg-table--klikalna">
      <caption class="pmg-vh">Odcinki podcastu</caption>
      <thead><tr><th scope="col" class="pmg-num">Numer</th><th scope="col">Tytuł</th><th scope="col">Edycja</th><th scope="col">Data</th><th scope="col">Status</th><th scope="col">Kolejność</th></tr></thead>
      <tbody>
      <?php foreach ($odcinki as $i => $r):
          // strzałka jest wyłączona na brzegu edycji (odcinek sąsiedniej edycji nie jest jego sąsiadem na stronie)
          $wylG = $i === 0 || (string) $odcinki[$i - 1]['edycja_id'] !== (string) $r['edycja_id'];
          $wylD = $i === count($odcinki) - 1 || (string) $odcinki[$i + 1]['edycja_id'] !== (string) $r['edycja_id']; ?>
        <tr id="wiersz-<?= (int) $r['id'] ?>">
          <td class="pmg-num" data-label="Numer"><?= (int) $r['numer'] ?></td>
          <td class="pmg-td-main" data-label="Tytuł"><a class="pmg-row-link" href="?m=podcast&id=<?= (int) $r['id'] ?>"><?= h($r['tytul']) ?></a></td>
          <td class="pmg-num" data-label="Edycja"><?= $r['edycja_numer'] === null ? '—' : (int) $r['edycja_numer'] ?></td>
          <td class="pmg-num" data-label="Data"><time datetime="<?= h($r['data']) ?>"><?= h($r['data']) ?></time></td>
          <td data-label="Status"><?php if ($r['opublikowany']): ?><span class="pmg-chip pmg-chip--success">Opublikowany</span><?php else: ?><span class="pmg-chip pmg-chip--neutral">Szkic</span><?php endif; ?></td>
          <td class="pmg-td-actions" data-label="Kolejność">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gora"<?= $wylG ? ' disabled' : '' ?><?= fokus_strzalki((int) $r['id'], 'gora', $wylG, $wylD) ?> aria-label="Przesuń wyżej: <?= h($r['tytul']) ?>"><span aria-hidden="true">↑</span></button>
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="dol"<?= $wylD ? ' disabled' : '' ?><?= fokus_strzalki((int) $r['id'], 'dol', $wylG, $wylD) ?> aria-label="Przesuń niżej: <?= h($r['tytul']) ?>"><span aria-hidden="true">↓</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$odcinki): ?>
        <tr><td colspan="6" class="pmg-empty">Nie ma jeszcze odcinków.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=podcast&nowy">+ Dodaj pierwszy odcinek</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
