<?php
// Moduł „Treści stron”: teksty opisowe poza Aktualnościami, Członkami, PM Session, Podcastem i Case Koła (nagłówki, leady,
// opisy sekcji, napisy na przyciskach, stopka). Zakładki = podstrony (+ „Stopka i wspólne”), pola z api/tresci-pola.php.
// Zapisane w pmg_tresci (klucz = data-tresc z HTML) zastępuje tekst z HTML; puste pole / „Przywróć” = tekst z HTML.
// Wartość to zwykły tekst (bez HTML): nowa linia = <br>, pusta linia = nowy akapit (w <p>) — patrz initTresci() w js/main.js.
defined('PMG_PANEL') || exit;

// Tekst z formularza do postaci zapisywanej: końce linii \n, bez znaków sterujących, jedno-liniowe pola bez nowych linii,
// bez spacji na końcach linii i bez więcej niż jednej pustej linii z rzędu.
if (!function_exists('tresci_norm')) {
    function tresci_norm($v, $typ)
    {
        $v = str_replace(["\r\n", "\r"], "\n", (string) $v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $v);
        if ($typ === 'krotki') return trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', $v));
        $v = preg_replace('/[ \t]+\n/', "\n", $v);
        $v = preg_replace('/\n{3,}/', "\n\n", $v);
        return trim($v);
    }
}

$lista = require __DIR__ . '/../api/tresci-pola.php';
$zakladki = $lista['zakladki'];
$z = (string) ($_POST['z'] ?? $_GET['z'] ?? '');
if (!isset($zakladki[$z])) $z = (string) key($zakladki);
$pola = $zakladki[$z]['pola'];

$wartosciForm = null; // wartości wpisane w formularzu, gdy zapis się nie udał (żeby ich nie zgubić)
$bladTresci = []; // klucz pola => komunikat (identyfikator pola w formularzu powstaje dopiero przy wypisywaniu, więc tam trafia do $bledyPol)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'import') {
        pmg_import_wykonaj('tresci');

    } elseif ($action === 'zapisz') {
        $wejscie = is_array($_POST['t'] ?? null) ? $_POST['t'] : [];
        $przywroc = (string) ($_POST['przywroc'] ?? '');
        $nowe = [];
        foreach ($pola as $k => $p) {
            if (!isset($wejscie[$k]) || !is_string($wejscie[$k])) continue; // pole nie przyszło w żądaniu = bez zmian
            $nowe[$k] = tresci_norm($wejscie[$k], $p['typ']);
            if ($k !== $przywroc && mb_strlen($nowe[$k]) > $p['max']) {
                $bladTresci[$k] = 'Pole „' . $p['etykieta'] . '” ma ' . mb_strlen($nowe[$k]) . ' znaków, a limit to ' . $p['max'] . '. Nic nie zapisano.';
            }
        }
        if ($bladTresci) $wartosciForm = $nowe; // wpisane wartości zostają w polach, żeby nic nie zginęło
        if (!$bladTresci) {
            $pdo = pmg_db();
            $zmienione = 0;
            $przywrocone = 0;
            try {
                $pdo->beginTransaction();
                $aktualne = [];
                foreach ($pdo->query('SELECT klucz, wartosc FROM pmg_tresci')->fetchAll() as $r) $aktualne[$r['klucz']] = $r['wartosc'];
                $zapisz = $pdo->prepare('REPLACE INTO pmg_tresci (klucz, wartosc, data_zmiany, kto) VALUES (?,?,NOW(),?)');
                $usun = $pdo->prepare('DELETE FROM pmg_tresci WHERE klucz = ?');
                foreach ($pola as $k => $p) {
                    $maWartosc = array_key_exists($k, $aktualne);
                    if ($k === $przywroc) { // przycisk „Przywróć tekst ze strony”: usuń wartość z bazy, niezależnie od tego, co wpisano
                        if ($maWartosc) { $usun->execute([$k]); $przywrocone++; }
                        continue;
                    }
                    if (!isset($nowe[$k])) continue;
                    if ($nowe[$k] === '') { // puste pole = tekst ze strony
                        if ($maWartosc) { $usun->execute([$k]); $przywrocone++; }
                    } elseif (!$maWartosc || $aktualne[$k] !== $nowe[$k]) {
                        $zapisz->execute([$k, $nowe[$k], $me['imie_nazwisko']]);
                        $zmienione++;
                    }
                }
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('tresci zapis: ' . $e->getMessage());
                $error = 'Błąd zapisu — nic nie zapisano. Spróbuj ponownie.';
                $wartosciForm = $nowe;
            }
            if ($error === '') {
                if ($zmienione) loguj('tresci', 'edycja', null, $zakladki[$z]['etykieta'] . ': ' . pmg_odmiana($zmienione, 'tekst', 'teksty', 'tekstów'));
                if ($przywrocone) loguj('tresci', 'przywrocenie', null, $zakladki[$z]['etykieta'] . ': ' . pmg_odmiana($przywrocone, 'tekst', 'teksty', 'tekstów'));
                $_SESSION['flash'] = ($zmienione || $przywrocone)
                    ? 'Zapisano' . ($zmienione ? ': zmienione teksty — ' . $zmienione : '') . ($przywrocone ? ($zmienione ? ', ' : ': ') . 'przywrócone teksty ze strony — ' . $przywrocone : '') . '. Zmiany widać na stronie w ciągu 5 minut.'
                    : 'Nic nie zmieniono.';
                go('?m=tresci&z=' . rawurlencode($z));
            }
        }
    }
}

// Zapisane wartości (wszystkie zakładki: liczniki przy zakładkach; bieżąca: pola).
$baza = [];
foreach (pmg_db()->query('SELECT klucz, wartosc, data_zmiany, kto FROM pmg_tresci')->fetchAll() as $r) $baza[$r['klucz']] = $r;
$ileZmian = [];
foreach ($zakladki as $zk => $zz) {
    $ileZmian[$zk] = 0;
    foreach ($zz['pola'] as $k => $p) if (isset($baza[$k]) && trim($baza[$k]['wartosc']) !== '') $ileZmian[$zk]++;
}

$pmgNaglowek = [
    'tytul' => 'Treści stron',
    'opis' => 'Nagłówki, opisy i napisy na przyciskach strony. Puste pole = strona pokazuje tekst, który ma teraz. Zmiany widać w ciągu 5 minut.',
];
if ($zakladki[$z]['plik'] !== '') {
    $pmgNaglowek['akcje'] = [['href' => '../' . $zakladki[$z]['plik'], 'etykieta' => 'Zobacz stronę', 'rodzaj' => 'text', 'nowaKarta' => true]];
}

// Pola zgrupowane wg sekcji strony (część etykiety przed „: ”).
$sekcje = [];
foreach ($pola as $k => $p) {
    $cz = explode(': ', $p['etykieta'], 2);
    $sekcje[count($cz) === 2 ? $cz[0] : 'Pozostałe'][$k] = count($cz) === 2 ? $cz[1] : $p['etykieta'];
}
$nr = 0;
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>
<?php pmg_import_blok('tresci'); ?>

<nav class="pmg-tabs" aria-label="Strony serwisu">
  <ul class="pmg-tabs__list">
  <?php foreach ($zakladki as $zk => $zz): ?>
    <li><a class="pmg-tabs__tab" href="?m=tresci&amp;z=<?= h(rawurlencode($zk)) ?>"<?= $zk === $z ? ' aria-current="page"' : '' ?>><?= h($zz['etykieta']) ?><?php if ($ileZmian[$zk]): ?> <span class="pmg-tabs__n"><span class="pmg-vh">, zmienione teksty: </span><?= (int) $ileZmian[$zk] ?></span><?php endif; ?></a></li>
  <?php endforeach; ?>
  </ul>
</nav>

<form class="pmg-card" method="post" data-pmg-niezapisane>
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="zapisz"><input type="hidden" name="z" value="<?= h($z) ?>">
  <button class="pmg-vh" type="submit" tabindex="-1" aria-hidden="true">Zapisz</button><?php // domyślny przycisk dla Entera w polu — inaczej wysłałby go pierwszy „Przywróć” ?>
  <h2 class="pmg-h2"><?= h($zakladki[$z]['etykieta']) ?></h2>

  <?php foreach ($sekcje as $nazwaSekcji => $pp): $sid = 'sek-t' . (++$nr); ?>
    <section class="pmg-form-section" aria-labelledby="<?= $sid ?>">
      <h3 class="pmg-form-section__title" id="<?= $sid ?>"><?= h($nazwaSekcji) ?></h3>
      <?php foreach ($pp as $k => $nazwa): $p = $pola[$k]; $id = 't' . (++$nr);
            if (isset($bladTresci[$k])) $bledyPol[$id] = $bladTresci[$k];
            $ma = isset($baza[$k]) && trim($baza[$k]['wartosc']) !== '';
            $wartosc = $wartosciForm !== null && isset($wartosciForm[$k]) ? $wartosciForm[$k] : ($ma ? $baza[$k]['wartosc'] : '');
            $wiersze = min(8, max(2, (int) ceil(mb_strlen($p['domyslny']) / 70) + substr_count($p['domyslny'], "\n"))); ?>
        <div class="pmg-tresc">
          <label for="<?= $id ?>"><?= h($nazwa) ?></label>
          <p class="pmg-hint pmg-tresc__dom" id="<?= $id ?>-h">Na stronie jest: <span id="<?= $id ?>-dom"><?= h($p['domyslny']) ?></span><?php if (!empty($p['gdzie'])): ?> (<?= h($p['gdzie']) ?>)<?php endif; ?></p>
          <?php if ($p['typ'] === 'tekst'): ?>
            <textarea id="<?= $id ?>" name="t[<?= h($k) ?>]" rows="<?= $wiersze ?>" maxlength="<?= (int) $p['max'] ?>"<?= blad_pola($id, $id . '-h') ?> data-pmg-licznik><?= h($wartosc) ?></textarea><?= komunikat_pola($id) ?>
          <?php else: ?>
            <input type="text" id="<?= $id ?>" name="t[<?= h($k) ?>]" maxlength="<?= (int) $p['max'] ?>" value="<?= h($wartosc) ?>"<?= blad_pola($id, $id . '-h') ?> data-pmg-licznik><?= komunikat_pola($id) ?>
          <?php endif; ?>
          <div class="pmg-tresc__meta">
            <?php if ($ma): ?>
              <span class="pmg-chip pmg-chip--accent">Zmieniony</span>
              <span class="pmg-hint">Ostatnia zmiana: <time datetime="<?= h($baza[$k]['data_zmiany']) ?>"><?= h($baza[$k]['data_zmiany']) ?></time><?= $baza[$k]['kto'] !== '' ? ', ' . h($baza[$k]['kto']) : '' ?></span>
              <button class="pmg-btn pmg-btn--text pmg-btn--sm" type="submit" name="przywroc" value="<?= h($k) ?>">Przywróć tekst ze strony<span class="pmg-vh">: <?= h($nazwa) ?></span></button>
            <?php else: ?>
              <span class="pmg-chip pmg-chip--neutral">Tekst ze strony</span>
            <?php endif; ?>
            <button class="pmg-btn pmg-btn--text pmg-btn--sm" type="button" data-pmg-wstaw="<?= $id ?>" data-pmg-wstaw-z="<?= $id ?>-dom" hidden>Wstaw tekst ze strony<span class="pmg-vh">: <?= h($nazwa) ?></span></button>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <div class="pmg-form-actions">
    <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
  </div>
</form>
