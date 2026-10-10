<?php
// Moduł Case Koła: edycje (karta w hubie + treść podstrony), galeria zdjęć, kolejność (strzałki), widoczność.
// Wołany wyłącznie z index.php (?m=case).
defined('PMG_PANEL') || exit;

const CASE_ORDER = 'kolejnosc, id';
const CASE_GALERIA_MAX = 12;
const CASE_STYLE = [
    'ciemne' => 'Jasne (białe) logo na ciemnej karcie',
    'jasne' => 'Kolorowe logo na białej płytce',
    'jasne-wysokie' => 'Kolorowe logo na białej płytce — wysokie albo kwadratowe',
];
// Pola tekstowe sekcji podstrony: klucz => [etykieta, podpowiedź]. Puste pole = sekcja jest pomijana na stronie.
const CASE_TEKSTY = [
    'o_partnerze' => ['O partnerze', 'Kim jest koło partnerskie.'],
    'wyzwanie' => ['Wyzwanie', 'Z czym partner przyszedł do PMG.'],
    'co_zrobilismy' => ['Co zrobiliśmy', 'Zakres pracy PMG.'],
    'rezultat' => ['Rezultat', 'Co partner otrzymał na koniec współpracy.'],
];

function case_edycja($id)
{
    $st = pmg_db()->prepare('SELECT * FROM pmg_case_edycje WHERE id = ?');
    $st->execute([(int) $id]);
    return $st->fetch() ?: null;
}

// Zapis pliku z pola formularza: nowy plik > zaznaczone „usuń” > dotychczasowy. Stary plik z uploads/ usuwa wywołujący po udanym zapisie.
function case_plik($pole, $modul, $stary, $usun)
{
    if (!empty($_FILES[$pole]['name'])) return save_image($_FILES[$pole], $modul);
    return $usun ? null : $stary;
}

$edit = null;
$galeriaEdycja = null; // edycja, której galeria jest na ekranie (przy błędzie galerii)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'import') {
        pmg_import_wykonaj('case');

    } elseif ($action === 'gora' || $action === 'dol') {
        $id = (int) ($_POST['id'] ?? 0);
        if (przesun('pmg_case_edycje', CASE_ORDER, $id, $action)) { $ed = case_edycja($id); loguj('case', 'kolejnosc', $id, $ed ? 'Edycja ' . $ed['numer'] . ': ' . $ed['nazwa'] : ''); }
        go('?m=case');

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = case_edycja($id);
        if ($old) {
            $pdo = pmg_db();
            $st = $pdo->prepare('SELECT zdjecie, pelne FROM pmg_case_galeria WHERE edycja_id = ?');
            $st->execute([$id]);
            $pliki = [$old['hero'], $old['logo']];
            foreach ($st->fetchAll() as $g) { $pliki[] = $g['zdjecie']; $pliki[] = $g['pelne']; }
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM pmg_case_galeria WHERE edycja_id = ?')->execute([$id]);
                $usun = $pdo->prepare('DELETE FROM pmg_case_edycje WHERE id = ?');
                $usun->execute([$id]);
                $usunieto = $usun->rowCount() > 0; // A2 6.5: równoległe usunięcie mogło nas wyprzedzić
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('case delete: ' . $e->getMessage());
                $error = 'Nie udało się usunąć edycji (błąd bazy danych).';
                $edit = $old;
            }
            if ($error === '' && !$usunieto) {
                $_SESSION['flash'] = 'Nie znaleziono tej edycji — nic nie usunięto.';
                go('?m=case');
            }
            if ($error === '') {
                foreach ($pliki as $p) drop_image($p);
                loguj('case', 'usuniecie', $id, 'Edycja ' . $old['numer'] . ': ' . $old['nazwa']);
                $_SESSION['flash'] = 'Edycja usunięta.';
                go('?m=case');
            }
        } else {
            $_SESSION['flash'] = 'Nie znaleziono tej edycji.';
            go('?m=case');
        }

    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $f = [];
        foreach (['nazwa' => 80, 'tytul_karty' => 80, 'naglowek' => 120, 'adres_strony' => 100, 'opis_meta' => 300, 'w_toku' => 300, 'hero_alt' => 200] as $k => $max) {
            $f[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        }
        foreach (array_keys(CASE_TEKSTY) as $k) {
            $f[$k] = mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST[$k] ?? ''))), 0, 5000);
        }
        $f['numer'] = (int) ($_POST['numer'] ?? 0);
        $f['logo_styl'] = (string) ($_POST['logo_styl'] ?? 'ciemne');
        $f['widoczna'] = empty($_POST['widoczna']) ? 0 : 1;
        $old = $id ? case_edycja($id) : null;
        $nowe = []; // pliki wgrane w tym żądaniu — usuwane, jeśli zapis się nie uda
        try {
            if ($f['numer'] < 1 || $f['numer'] > 999) throw new RuntimeException('Numer edycji: liczba od 1 do 999.');
            if ($f['nazwa'] === '') throw new RuntimeException('Podaj nazwę partnera (np. Solvro).');
            if (!isset(CASE_STYLE[$f['logo_styl']])) throw new RuntimeException('Wybierz sposób wyświetlania logo.');
            if ($f['adres_strony'] !== '') {
                if (!preg_match('~^case-kola-[a-z0-9-]+\.html$~', $f['adres_strony']) || $f['adres_strony'] === 'case-kola-edycja.html') {
                    throw new RuntimeException('Własna podstrona: nazwa pliku w postaci case-kola-nazwa.html (albo zostaw puste pole, żeby użyć wspólnej podstrony).');
                }
                if (!is_file(__DIR__ . '/../' . $f['adres_strony'])) throw new RuntimeException('Na serwerze nie ma pliku ' . $f['adres_strony'] . '. Zostaw puste pole, żeby użyć wspólnej podstrony.');
            }
            $stareHero = $old['hero'] ?? null;
            $stareLogo = $old['logo'] ?? null;
            // opis zdjęcia sprawdzamy przed wgraniem plików, żeby błąd nie zostawiał wgranych plików bez rekordu
            $zostajeHero = !empty($_FILES['hero']['name']) || (!empty($stareHero) && empty($_POST['hero_usun']));
            if ($zostajeHero && $f['hero_alt'] === '') throw new RuntimeException('Dodaj opis zdjęcia głównego (dla osób niewidomych).');
            $f['hero'] = case_plik('hero', 'case', $stareHero, !empty($_POST['hero_usun']));
            if ($f['hero'] !== $stareHero && $f['hero'] !== null) $nowe[] = $f['hero'];
            $f['logo'] = case_plik('logo', 'case-logo', $stareLogo, !empty($_POST['logo_usun']));
            if ($f['logo'] !== $stareLogo && $f['logo'] !== null) $nowe[] = $f['logo'];
            if ($f['hero'] === null) $f['hero_alt'] = '';
            $kolumny = ['numer', 'nazwa', 'tytul_karty', 'naglowek', 'adres_strony', 'opis_meta', 'logo', 'logo_styl', 'hero', 'hero_alt', 'o_partnerze', 'wyzwanie', 'co_zrobilismy', 'rezultat', 'w_toku', 'widoczna'];
            $wartosci = [];
            foreach ($kolumny as $k) $wartosci[] = $f[$k];
            if ($id && $old) {
                $st = pmg_db()->prepare('UPDATE pmg_case_edycje SET ' . implode('=?, ', $kolumny) . '=? WHERE id=?');
                $st->execute(array_merge($wartosci, [$id]));
                $nowe = [];
                if ($f['hero'] !== $stareHero) drop_image($stareHero);
                if ($f['logo'] !== $stareLogo) drop_image($stareLogo);
                loguj('case', 'edycja', $id, 'Edycja ' . $f['numer'] . ': ' . $f['nazwa']);
            } else {
                // Nowa edycja ląduje na początku listy (w hubie najnowsza edycja jest pierwsza).
                $kolejnosc = (int) pmg_db()->query('SELECT COALESCE(MIN(kolejnosc), 1) - 1 FROM pmg_case_edycje')->fetchColumn();
                $st = pmg_db()->prepare('INSERT INTO pmg_case_edycje (' . implode(', ', $kolumny) . ', kolejnosc) VALUES (' . implode(',', array_fill(0, count($kolumny) + 1, '?')) . ')');
                $st->execute(array_merge($wartosci, [$kolejnosc]));
                $nowe = [];
                $id = (int) pmg_db()->lastInsertId();
                loguj('case', 'dodanie', $id, 'Edycja ' . $f['numer'] . ': ' . $f['nazwa']);
            }
            $_SESSION['flash'] = $f['widoczna'] ? 'Zapisano. Edycja jest widoczna na stronie. Na stronie zmiana pojawi się w ciągu 5 minut.' : 'Zapisano. Edycja jest ukryta na stronie.';
            go('?m=case&id=' . $id);
        } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy
            foreach ($nowe as $p) drop_image($p);
            $error = $e->getCode() === '23000' ? 'Edycja o tym numerze już istnieje.' : 'Błąd zapisu.';
            if ($e->getCode() !== '23000') error_log('case save: ' . $e->getMessage());
            $edit = array_merge($old ?: [], $f, ['id' => $id, 'hero' => $old['hero'] ?? null, 'logo' => $old['logo'] ?? null]);
        } catch (RuntimeException $e) {
            foreach ($nowe as $p) drop_image($p);
            $error = $e->getMessage();
            $edit = array_merge($old ?: [], $f, ['id' => $id, 'hero' => $old['hero'] ?? null, 'logo' => $old['logo'] ?? null]);
        }

    // ---------- Galeria ----------
    } elseif (strpos($action, 'gal_') === 0) {
        $eid = (int) ($_POST['edycja_id'] ?? 0);
        $gid = (int) ($_POST['id'] ?? 0);
        $ed = case_edycja($eid);
        if (!$ed) {
            $_SESSION['flash'] = 'Nie znaleziono tej edycji.';
            go('?m=case');
        }
        $wroc = '?m=case&id=' . $eid . '#galeria';
        $st = pmg_db()->prepare('SELECT * FROM pmg_case_galeria WHERE id = ? AND edycja_id = ?');
        $st->execute([$gid, $eid]);
        $g = $st->fetch() ?: null;
        $noweGal = null; // plik wgrany w tym żądaniu — usuwany, jeśli zapis do bazy się nie uda
        try {
            if ($action === 'gal_dodaj') {
                $podpis = mb_substr(trim((string) ($_POST['podpis'] ?? '')), 0, 200);
                $ile = pmg_db()->prepare('SELECT COUNT(*) FROM pmg_case_galeria WHERE edycja_id = ?');
                $ile->execute([$eid]);
                if ((int) $ile->fetchColumn() >= CASE_GALERIA_MAX) throw new RuntimeException('Galeria może mieć najwyżej ' . CASE_GALERIA_MAX . ' zdjęć.');
                if (empty($_FILES['plik']['name'])) throw new RuntimeException('Wybierz plik ze zdjęciem.');
                if ($podpis === '') throw new RuntimeException('Dodaj podpis zdjęcia (to także opis dla osób niewidomych).');
                $plik = $noweGal = save_image($_FILES['plik'], 'case');
                $kol = pmg_db()->prepare('SELECT COALESCE(MAX(kolejnosc), 0) + 1 FROM pmg_case_galeria WHERE edycja_id = ?');
                $kol->execute([$eid]);
                pmg_db()->prepare('INSERT INTO pmg_case_galeria (edycja_id, zdjecie, pelne, podpis, kolejnosc) VALUES (?,?,NULL,?,?)')
                    ->execute([$eid, $plik, $podpis, (int) $kol->fetchColumn()]);
                $noweGal = null;
                loguj('case', 'galeria', $eid, 'Edycja ' . $ed['numer'] . ': ' . $ed['nazwa']);
                $_SESSION['flash'] = 'Zdjęcie dodane do galerii.';
                go($wroc);
            } elseif (!$g) {
                throw new RuntimeException('Nie znaleziono zdjęcia.');
            } elseif ($action === 'gal_zapisz') {
                $podpis = mb_substr(trim((string) ($_POST['podpis'] ?? '')), 0, 200);
                if ($podpis === '') throw new RuntimeException('Podpis zdjęcia nie może być pusty (to także opis dla osób niewidomych).');
                $nowe = null;
                if (!empty($_FILES['plik']['name'])) $nowe = $noweGal = save_image($_FILES['plik'], 'case');
                if ($nowe !== null) {
                    // nowy plik zastępuje zdjęcie i powiększenie (osobny plik „pełne” dotyczył starego zdjęcia)
                    pmg_db()->prepare('UPDATE pmg_case_galeria SET podpis = ?, zdjecie = ?, pelne = NULL WHERE id = ?')->execute([$podpis, $nowe, $gid]);
                    $noweGal = null;
                    drop_image($g['zdjecie']);
                    drop_image($g['pelne']);
                } else {
                    pmg_db()->prepare('UPDATE pmg_case_galeria SET podpis = ? WHERE id = ?')->execute([$podpis, $gid]);
                }
                loguj('case', 'galeria', $eid, 'Edycja ' . $ed['numer'] . ': ' . $ed['nazwa']);
                $_SESSION['flash'] = 'Zapisano zdjęcie.';
                go($wroc);
            } elseif ($action === 'gal_gora' || $action === 'gal_dol') {
                if (przesun('pmg_case_galeria', 'kolejnosc, id', $gid, $action === 'gal_gora' ? 'gora' : 'dol', 'edycja_id', $eid)) loguj('case', 'galeria', $eid, 'Edycja ' . $ed['numer'] . ': ' . $ed['nazwa']);
                go($wroc);
            } elseif ($action === 'gal_usun') {
                $usun = pmg_db()->prepare('DELETE FROM pmg_case_galeria WHERE id = ?');
                $usun->execute([$gid]);
                if ($usun->rowCount() === 0) throw new RuntimeException('Nie znaleziono zdjęcia — nic nie usunięto.'); // A2 6.5
                drop_image($g['zdjecie']);
                drop_image($g['pelne']);
                loguj('case', 'galeria', $eid, 'Edycja ' . $ed['numer'] . ': ' . $ed['nazwa']);
                $_SESSION['flash'] = 'Zdjęcie usunięte z galerii.';
                go($wroc);
            }
        } catch (PDOException $e) { // przed RuntimeException: PDOException po nim dziedziczy, więc inaczej do formularza trafiłby surowy komunikat bazy
            error_log('case galeria: ' . $e->getMessage());
            drop_image($noweGal);
            $error = 'Błąd zapisu galerii.';
            $edit = $ed;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $edit = $ed;
        }
    }
}

if ($edit === null) {
    if (isset($_GET['nowa'])) {
        $nastepny = (int) pmg_db()->query('SELECT COALESCE(MAX(numer), 0) + 1 FROM pmg_case_edycje')->fetchColumn();
        $edit = ['id' => 0, 'numer' => $nastepny, 'logo_styl' => 'ciemne', 'widoczna' => 1];
    } elseif (isset($_GET['id'])) {
        $edit = case_edycja($_GET['id']);
    }
}
$v = function ($k) use (&$edit) { return h($edit[$k] ?? ''); };

$galeria = [];
if ($edit !== null && !empty($edit['id'])) {
    $st = pmg_db()->prepare('SELECT * FROM pmg_case_galeria WHERE edycja_id = ? ORDER BY kolejnosc, id');
    $st->execute([(int) $edit['id']]);
    $galeria = $st->fetchAll();
}

if ($edit !== null) {
    $pmgNaglowek = [
        'tytul' => $edit['id'] ? 'Edytuj edycję ' . $edit['numer'] : 'Nowa edycja Case Koła',
        'opis' => $edit['id'] ? (string) $edit['nazwa'] : 'Karta w hubie i podstrona powstaną po zapisaniu. Puste pola tekstowe są pomijane na stronie.',
        'wstecz' => ['href' => '?m=case', 'etykieta' => 'Case Koła'],
    ];
    if ($edit['id'] && !empty($edit['widoczna'])) { // ukrytej edycji nie ma na stronie, więc bez linku
        $url = $edit['adres_strony'] !== '' ? $edit['adres_strony'] : 'case-kola-edycja.html?nr=' . (int) $edit['numer'];
        $pmgNaglowek['akcje'] = [['href' => '../' . $url, 'etykieta' => 'Zobacz na stronie', 'rodzaj' => 'secondary', 'nowaKarta' => true]];
    }
} else {
    $lista = pmg_db()->query('SELECT e.id, e.numer, e.nazwa, e.widoczna, (SELECT COUNT(*) FROM pmg_case_galeria g WHERE g.edycja_id = e.id) AS zdjec FROM pmg_case_edycje e ORDER BY e.kolejnosc, e.id')->fetchAll();
    $pmgNaglowek = [
        'akcje' => [
            ['href' => '?m=case&nowa', 'etykieta' => '+ Nowa edycja', 'rodzaj' => 'primary'],
            ['href' => '../case-kola.html', 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($edit !== null): ?>
  <form class="pmg-card" method="post" enctype="multipart/form-data" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">

    <section class="pmg-form-section" aria-labelledby="sek-karta">
      <h2 class="pmg-form-section__title" id="sek-karta">Edycja i karta w hubie</h2>
      <label for="numer">Numer edycji</label>
      <p class="pmg-hint" id="numer_h">Pokazuje się na karcie („Edycja 4”) i w adresie podstrony (case-kola-edycja.html?nr=…). Każda edycja ma inny numer.</p>
      <input type="number" id="numer" name="numer" min="1" max="999" value="<?= $v('numer') ?>" aria-describedby="numer_h" required>
      <label for="nazwa">Nazwa partnera</label>
      <p class="pmg-hint" id="nazwa_h">Np. Solvro. Pojawia się w menu, w ścieżce nawigacji i (jeśli nie wpiszesz innego tytułu) na karcie.</p>
      <input type="text" id="nazwa" name="nazwa" maxlength="80" value="<?= $v('nazwa') ?>" aria-describedby="nazwa_h" required>
      <label for="tytul_karty">Tytuł na karcie <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="tytul_karty_h">Gdy chcesz, żeby na karcie nazwa wyglądała inaczej niż w menu, np. QUBIT.</p>
      <input type="text" id="tytul_karty" name="tytul_karty" maxlength="80" value="<?= $v('tytul_karty') ?>" aria-describedby="tytul_karty_h">

      <label for="logo">Logo partnera (JPG, PNG albo WebP) <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="logo_h">Maks. 10 MB, dowolne proporcje. PNG z przezroczystym tłem zachowa przezroczystość. Karta bez logo pokazuje tylko tytuł.</p>
      <?php if (!empty($edit['logo'])): ?>
        <figure class="pmg-photo pmg-photo--16x9"><img src="../<?= h($edit['logo']) ?>" alt="" style="object-fit:contain;padding:12px;background:#141414"><figcaption class="pmg-hint">Obecne logo (na ciemnym tle podglądu). Wgranie nowego pliku zastąpi je.</figcaption></figure>
        <label class="pmg-check"><input type="checkbox" name="logo_usun" value="1"><span>Usuń obecne logo</span></label>
      <?php endif; ?>
      <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" aria-describedby="logo_h">
      <label for="logo_styl">Jak pokazać logo na karcie</label>
      <select id="logo_styl" name="logo_styl">
        <?php foreach (CASE_STYLE as $k => $etykieta): ?>
          <option value="<?= h($k) ?>"<?= ($edit['logo_styl'] ?? 'ciemne') === $k ? ' selected' : '' ?>><?= h($etykieta) ?></option>
        <?php endforeach; ?>
      </select>
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-strona">
      <h2 class="pmg-form-section__title" id="sek-strona">Nagłówek podstrony</h2>
      <label for="naglowek">Nagłówek <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="naglowek_h">Duży tytuł na górze podstrony, np. „KN Solvro”. Puste pole = nazwa partnera.</p>
      <input type="text" id="naglowek" name="naglowek" maxlength="120" value="<?= $v('naglowek') ?>" aria-describedby="naglowek_h">
      <label for="opis_meta">Krótki opis dla wyszukiwarek <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="opis_meta_h">Jedno zdanie, np. okres i temat współpracy. Nie widać go na stronie, tylko w wynikach wyszukiwania. Maks. 300 znaków.</p>
      <textarea id="opis_meta" name="opis_meta" maxlength="300" aria-describedby="opis_meta_h" data-pmg-licznik><?= $v('opis_meta') ?></textarea>

      <label for="hero">Zdjęcie główne 16:9 (JPG, PNG albo WebP) <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="hero_h">Maks. 10 MB. Zdjęcie zostanie przycięte do proporcji 16:9 (ze środka). Najlepiej wgraj zdjęcie w tych proporcjach. Np. 1920 × 1080 px. Bez zdjęcia, ale z jasnym logo (patrz wyżej) na górze pojawi się logo na ciemnym tle; w pozostałych przypadkach sekcji nie ma.</p>
      <?php if (!empty($edit['hero'])): ?>
        <figure class="pmg-photo pmg-photo--16x9"><img src="../<?= h($edit['hero']) ?>" alt=""><figcaption class="pmg-hint">Obecne zdjęcie. Wgranie nowego pliku zastąpi je.</figcaption></figure>
        <label class="pmg-check"><input type="checkbox" name="hero_usun" value="1"><span>Usuń obecne zdjęcie główne</span></label>
      <?php endif; ?>
      <input type="file" id="hero" name="hero" accept="image/jpeg,image/png,image/webp" aria-describedby="hero_h">
      <label for="hero_alt">Opis zdjęcia głównego (co na nim widać — dla osób niewidomych)</label>
      <p class="pmg-hint" id="hero_alt_h">Wymagany, jeśli dodajesz lub masz już zapisane zdjęcie.</p>
      <input type="text" id="hero_alt" name="hero_alt" maxlength="200" value="<?= $v('hero_alt') ?>" aria-describedby="hero_alt_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-tresc">
      <h2 class="pmg-form-section__title" id="sek-tresc">Treść podstrony</h2>
      <p class="pmg-hint">Akapity oddzielaj pustą linią. Bez HTML — znaczniki pokażą się jako zwykły tekst. Puste pole = sekcji nie ma na stronie.</p>
      <?php foreach (CASE_TEKSTY as $k => $def): ?>
        <label for="<?= $k ?>"><?= h($def[0]) ?> <span class="pmg-opt">(opcjonalnie)</span></label>
        <p class="pmg-hint" id="<?= $k ?>_h"><?= h($def[1]) ?></p>
        <textarea id="<?= $k ?>" name="<?= $k ?>" maxlength="5000" rows="7" aria-describedby="<?= $k ?>_h"><?= $v($k) ?></textarea>
      <?php endforeach; ?>
      <label for="w_toku">Komunikat „w toku” <span class="pmg-opt">(opcjonalnie)</span></label>
      <p class="pmg-hint" id="w_toku_h">Krótki tekst na dole strony, gdy współpraca trwa, np. „Rozwiązanie powstanie w kolejnym etapie współpracy.” Maks. 300 znaków.</p>
      <input type="text" id="w_toku" name="w_toku" maxlength="300" value="<?= $v('w_toku') ?>" aria-describedby="w_toku_h">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-adres">
      <h2 class="pmg-form-section__title" id="sek-adres">Własna podstrona</h2>
      <label for="adres_strony">Plik własnej podstrony <span class="pmg-opt">(zwykle puste)</span></label>
      <p class="pmg-hint" id="adres_h">Domyślnie karta prowadzi do wspólnej podstrony, którą wypełnia ta treść. Wpisz nazwę pliku (np. case-kola-solvro.html) tylko wtedy, gdy edycja ma osobny, ręcznie przygotowany plik na serwerze — wtedy zmiany tekstu stąd nie będą na nim widoczne.</p>
      <input type="text" id="adres_strony" name="adres_strony" maxlength="100" value="<?= $v('adres_strony') ?>" aria-describedby="adres_h" autocapitalize="none" spellcheck="false">
    </section>

    <section class="pmg-form-section" aria-labelledby="sek-widocznosc">
      <h2 class="pmg-form-section__title" id="sek-widocznosc">Widoczność</h2>
      <label class="pmg-check"><input type="checkbox" name="widoczna" value="1"<?= !empty($edit['widoczna']) ? ' checked' : '' ?> aria-describedby="widoczna_h"><span>Pokaż edycję na stronie</span></label>
      <p class="pmg-hint pmg-hint--check" id="widoczna_h">Bez zaznaczenia edycja jest ukryta: nie ma jej w hubie, w menu ani pod swoim adresem. Kolejność kart zmieniasz strzałkami na liście.</p>
    </section>

    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=case">Anuluj</a>
    </div>
  </form>

  <?php if ($edit['id']): ?>
    <div class="pmg-card" id="galeria">
      <div class="pmg-card__head"><h2 class="pmg-h2">Galeria</h2></div>
      <p class="pmg-hint">Zdjęcia 16:9 pod treścią podstrony; po kliknięciu powiększają się. Kolejność zmieniasz strzałkami. Do <?= CASE_GALERIA_MAX ?> zdjęć. Bez zdjęć sekcji galerii nie ma.</p>
      <?php foreach ($galeria as $i => $g): ?>
        <form class="pmg-gal" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="edycja_id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <figure class="pmg-photo pmg-photo--16x9 pmg-gal__foto"><img src="../<?= h($g['zdjecie']) ?>" alt=""></figure>
          <div class="pmg-gal__pola">
            <label for="gal-podpis-<?= (int) $g['id'] ?>">Podpis zdjęcia <?= $i + 1 ?> (także opis dla osób niewidomych)</label>
            <input type="text" id="gal-podpis-<?= (int) $g['id'] ?>" name="podpis" maxlength="200" value="<?= h($g['podpis']) ?>" required>
            <label for="gal-plik-<?= (int) $g['id'] ?>">Podmień plik <span class="pmg-opt">(opcjonalnie)</span></label>
            <input type="file" id="gal-plik-<?= (int) $g['id'] ?>" name="plik" accept="image/jpeg,image/png,image/webp">
            <div class="pmg-gal__akcje">
              <button class="pmg-btn pmg-btn--primary pmg-btn--sm" type="submit" name="a" value="gal_zapisz">Zapisz zdjęcie</button>
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gal_gora" formnovalidate<?= $i === 0 ? ' disabled' : '' ?> aria-label="Przesuń wyżej: zdjęcie <?= $i + 1 ?>"><span aria-hidden="true">↑</span></button>
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gal_dol" formnovalidate<?= $i === count($galeria) - 1 ? ' disabled' : '' ?> aria-label="Przesuń niżej: zdjęcie <?= $i + 1 ?>"><span aria-hidden="true">↓</span></button>
              <button class="pmg-btn pmg-btn--danger pmg-btn--sm" type="submit" name="a" value="gal_usun" formnovalidate data-pmg-potwierdz="Usunąć zdjęcie <?= $i + 1 ?> z galerii? Tego nie da się cofnąć.">Usuń zdjęcie<span class="pmg-vh"> <?= $i + 1 ?></span></button>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
      <?php if (count($galeria) < CASE_GALERIA_MAX): ?>
        <form class="pmg-gal pmg-gal--nowe" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="edycja_id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="a" value="gal_dodaj">
          <h3 class="pmg-gal__tytul">Dodaj zdjęcie</h3>
          <label for="gal-nowe-plik">Zdjęcie 16:9 (JPG, PNG albo WebP)</label>
          <p class="pmg-hint" id="gal-nowe-h">Maks. 10 MB. Zdjęcie zostanie przycięte do proporcji 16:9 (ze środka). Najlepiej wgraj zdjęcie w tych proporcjach. Np. 1200 × 675 px.</p>
          <input type="file" id="gal-nowe-plik" name="plik" accept="image/jpeg,image/png,image/webp" aria-describedby="gal-nowe-h" required>
          <label for="gal-nowe-podpis">Podpis zdjęcia (także opis dla osób niewidomych)</label>
          <input type="text" id="gal-nowe-podpis" name="podpis" maxlength="200" required>
          <div class="pmg-form-actions"><button class="pmg-btn pmg-btn--secondary" type="submit">Dodaj do galerii</button></div>
        </form>
      <?php endif; ?>
    </div>

    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Edycja, jej zdjęcia i galeria znikną ze strony i z panelu. Tego nie da się cofnąć. Żeby tylko schować edycję, odznacz „Pokaż edycję na stronie”.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tę edycję na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń edycję</button>
    </form>
  <?php endif; ?>

<?php else: ?>
  <?php pmg_import_blok('case'); ?>
  <p class="pmg-hint">Karty w hubie Case Koła pojawiają się w kolejności z tej listy. Gdy w panelu jest choć jedna edycja (także ukryta), hub i menu pochodzą z panelu, a nie z kodu strony.</p>
  <div class="pmg-table-wrap">
    <table class="pmg-table pmg-table--klikalna">
      <caption class="pmg-vh">Edycje Case Koła</caption>
      <thead><tr><th scope="col" class="pmg-num">Nr</th><th scope="col">Partner</th><th scope="col" class="pmg-num">Zdjęcia</th><th scope="col">Widoczność</th><th scope="col">Kolejność</th></tr></thead>
      <tbody>
      <?php foreach ($lista as $i => $r): ?>
        <tr>
          <td class="pmg-num" data-label="Nr"><?= (int) $r['numer'] ?></td>
          <td class="pmg-td-main" data-label="Partner"><a class="pmg-row-link" href="?m=case&amp;id=<?= (int) $r['id'] ?>"><?= h($r['nazwa']) ?></a></td>
          <td class="pmg-num" data-label="Zdjęcia"><?= (int) $r['zdjec'] ?></td>
          <td data-label="Widoczność"><?php if ($r['widoczna']): ?><span class="pmg-chip pmg-chip--success">Widoczna</span><?php else: ?><span class="pmg-chip pmg-chip--neutral">Ukryta</span><?php endif; ?></td>
          <td class="pmg-td-actions" data-label="Kolejność">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="gora"<?= $i === 0 ? ' disabled' : '' ?> aria-label="Przesuń wyżej: <?= h($r['nazwa']) ?>"><span aria-hidden="true">↑</span></button>
              <button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit" name="a" value="dol"<?= $i === count($lista) - 1 ? ' disabled' : '' ?> aria-label="Przesuń niżej: <?= h($r['nazwa']) ?>"><span aria-hidden="true">↓</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?>
        <tr><td colspan="5" class="pmg-empty">Nie ma jeszcze edycji Case Koła.<br><a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=case&amp;nowa">+ Dodaj pierwszą edycję</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
