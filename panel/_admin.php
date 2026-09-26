<?php
// Moduł administracyjny (tylko rola 'admin'): konta, dziennik zmian, kopia bazy, ustawienia (2b).
// Wołany wyłącznie z index.php (?m=konta|dziennik|kopia|ustawienia).
defined('PMG_PANEL') || exit;

$sub = $_GET['m'] ?? '';

// ---------- Kopia bazy: strumień SQL, bez szablonu ----------
if ($sub === 'kopia') {
    ob_end_clean();
    $pdo = pmg_db();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="pmg-kopia-' . date('Y-m-d') . '.sql"');
    header('Cache-Control: no-store');
    echo "-- Kopia bazy PMG — " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tabele = $pdo->query("SHOW TABLES LIKE 'pmg\\_%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tabele as $t) {
        echo "DROP TABLE IF EXISTS `$t`;\n";
        $create = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch();
        echo $create['Create Table'] . ";\n\n";
        $stmt = $pdo->query('SELECT * FROM `' . $t . '`');
        foreach ($stmt as $r) {
            $vals = array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote($v); }, $r);
            echo 'INSERT INTO `' . $t . '` (`' . implode('`,`', array_keys($r)) . '`) VALUES (' . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "-- KONIEC KOPII
"; // brak tej linii = kopia przerwana w trakcie
    loguj('kopia', 'pobranie');
    exit;
}

// ---------- Ustawienia: etap 2b ----------
// Pola tego formularza. Liczby "PM Session w liczbach" (pms_*) są też w białej liście USTAWIENIA
// (lib.php), ale edytuje je moduł pmsession w etapie 2d — nie ten formularz.
if ($sub === 'ustawienia') {
    $pola = ['instagram', 'facebook', 'linkedin', 'tiktok', 'email', 'rekrutacja_otwarta', 'rekrutacja_link', 'rekrutacja_tekst'];

    $st = pmg_db()->prepare('SELECT klucz, wartosc FROM pmg_ustawienia WHERE klucz IN (' . implode(',', array_fill(0, count($pola), '?')) . ')');
    $st->execute($pola);
    $wartosci = array_fill_keys($pola, '');
    foreach ($st->fetchAll() as $r) $wartosci[$r['klucz']] = $r['wartosc'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $wejscie = [
            'instagram' => trim((string) ($_POST['instagram'] ?? '')),
            'facebook' => trim((string) ($_POST['facebook'] ?? '')),
            'linkedin' => trim((string) ($_POST['linkedin'] ?? '')),
            'tiktok' => trim((string) ($_POST['tiktok'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'rekrutacja_otwarta' => ($_POST['rekrutacja_otwarta'] ?? '') === '0' ? '0' : '1',
            'rekrutacja_link' => trim((string) ($_POST['rekrutacja_link'] ?? '')),
            'rekrutacja_tekst' => trim((string) ($_POST['rekrutacja_tekst'] ?? '')),
        ];
        $wartosci = $wejscie; // formularz zachowuje wpisane wartości, jeśli coś jest nie tak

        if (!url_ok($wejscie['instagram'])) $error = 'Instagram: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        elseif (!url_ok($wejscie['facebook'])) $error = 'Facebook: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        elseif (!url_ok($wejscie['linkedin'])) $error = 'LinkedIn: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        elseif (!url_ok($wejscie['tiktok'])) $error = 'TikTok: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        elseif ($wejscie['email'] !== '' && !filter_var($wejscie['email'], FILTER_VALIDATE_EMAIL)) $error = 'Podaj poprawny adres e-mail albo zostaw puste pole.';
        elseif (!url_ok($wejscie['rekrutacja_link'])) $error = 'Link do formularza rekrutacyjnego: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        elseif (mb_strlen($wejscie['rekrutacja_tekst']) > 300) $error = 'Tekst o rekrutacji może mieć maksymalnie 300 znaków.';

        if ($error === '') {
            $pdo = pmg_db();
            $pdo->beginTransaction();
            $upd = $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?,?)');
            foreach ($pola as $k) $upd->execute([$k, $wejscie[$k]]);
            $pdo->commit();
            loguj('ustawienia', 'edycja');
            $_SESSION['flash'] = 'Zapisano. Zmiany widać na stronie w ciągu 5 minut.';
            go('?m=ustawienia');
        }
    }
    ?>
    <?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>
    <form class="pmg-card" method="post" data-pmg-niezapisane>
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

      <section class="pmg-form-section" aria-labelledby="sek-social">
        <h2 class="pmg-form-section__title" id="sek-social">Media społecznościowe</h2>
        <label for="instagram">Instagram</label>
        <p class="pmg-hint" id="instagram_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="instagram" name="instagram" value="<?= h($wartosci['instagram']) ?>" aria-describedby="instagram_h">

        <label for="facebook">Facebook</label>
        <p class="pmg-hint" id="facebook_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="facebook" name="facebook" value="<?= h($wartosci['facebook']) ?>" aria-describedby="facebook_h">

        <label for="linkedin">LinkedIn</label>
        <p class="pmg-hint" id="linkedin_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="linkedin" name="linkedin" value="<?= h($wartosci['linkedin']) ?>" aria-describedby="linkedin_h">

        <label for="tiktok">TikTok</label>
        <p class="pmg-hint" id="tiktok_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="tiktok" name="tiktok" value="<?= h($wartosci['tiktok']) ?>" aria-describedby="tiktok_h">
      </section>

      <section class="pmg-form-section" aria-labelledby="sek-kontakt">
        <h2 class="pmg-form-section__title" id="sek-kontakt">Kontakt</h2>
        <label for="email">E-mail kontaktowy</label>
        <p class="pmg-hint" id="email_h">Adres pokazywany na stronie (stopka, Kontakt). Puste pole = strona pokazuje obecny adres.</p>
        <input type="email" id="email" name="email" value="<?= h($wartosci['email']) ?>" aria-describedby="email_h">
      </section>

      <section class="pmg-form-section" aria-labelledby="sek-rekrutacja">
        <h2 class="pmg-form-section__title" id="sek-rekrutacja">Rekrutacja</h2>
        <fieldset class="pmg-fieldset">
          <legend class="pmg-legend">Rekrutacja</legend>
          <p class="pmg-hint" id="rekrutacja_otwarta_h">Przy „Zamknięta” strona Dołącz ukrywa przycisk do formularza i podpowiedź pod nim.</p>
          <div class="pmg-options">
            <label class="pmg-option"><input type="radio" name="rekrutacja_otwarta" value="1" aria-describedby="rekrutacja_otwarta_h"<?= $wartosci['rekrutacja_otwarta'] !== '0' ? ' checked' : '' ?>><span>Otwarta</span></label>
            <label class="pmg-option"><input type="radio" name="rekrutacja_otwarta" value="0" aria-describedby="rekrutacja_otwarta_h"<?= $wartosci['rekrutacja_otwarta'] === '0' ? ' checked' : '' ?>><span>Zamknięta</span></label>
          </div>
        </fieldset>

        <label for="rekrutacja_link">Link do formularza rekrutacyjnego</label>
        <p class="pmg-hint" id="rekrutacja_link_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="rekrutacja_link" name="rekrutacja_link" value="<?= h($wartosci['rekrutacja_link']) ?>" aria-describedby="rekrutacja_link_h">

        <label for="rekrutacja_tekst">Krótki tekst o rekrutacji</label>
        <p class="pmg-hint" id="rekrutacja_tekst_h">Maksymalnie 300 znaków. Puste pole = strona pokazuje obecny tekst.</p>
        <textarea id="rekrutacja_tekst" name="rekrutacja_tekst" maxlength="300" aria-describedby="rekrutacja_tekst_h" data-pmg-licznik><?= h($wartosci['rekrutacja_tekst']) ?></textarea>
      </section>

      <div class="pmg-form-actions">
        <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      </div>
    </form>
    <?php
    return;
}

// ---------- Dziennik zmian ----------
if ($sub === 'dziennik') {
    $wpisy = pmg_db()->query(
        'SELECT d.kiedy, d.modul, d.akcja, d.rekord_id, u.imie_nazwisko
         FROM pmg_dziennik d JOIN pmg_uzytkownicy u ON u.id = d.uzytkownik_id
         ORDER BY d.id DESC LIMIT 200'
    )->fetchAll();
    // Etykiety PL akcji dziennika — tylko widok; nieznany klucz pokazuje surową wartość.
    $pmgAkcjeDziennika = [
        'dodanie' => 'Dodanie', 'edycja' => 'Edycja', 'usuniecie' => 'Usunięcie',
        'zaproszenie' => 'Zaproszenie', 'blokada' => 'Blokada', 'odblokowanie' => 'Odblokowanie',
        'reset' => 'Reset hasła', 'biezaca' => 'Ustawienie bieżącej edycji', 'pobranie' => 'Pobranie kopii',
    ];
    ?>
    <div class="pmg-table-wrap">
      <table class="pmg-table">
        <caption class="pmg-vh">Dziennik zmian</caption>
        <thead><tr>
          <th scope="col">Kiedy</th><th scope="col">Kto</th><th scope="col">Moduł</th><th scope="col">Akcja</th><th scope="col" class="pmg-num">Rekord</th>
        </tr></thead>
        <tbody>
        <?php foreach ($wpisy as $w): ?>
          <tr>
            <td class="pmg-td-main" data-label="Kiedy"><time datetime="<?= h($w['kiedy']) ?>"><?= h($w['kiedy']) ?></time></td>
            <td data-label="Kto"><?= h($w['imie_nazwisko']) ?></td>
            <td data-label="Moduł"><?= h($etykietyModulow[$w['modul']] ?? $w['modul']) ?></td>
            <td data-label="Akcja"><?= h($pmgAkcjeDziennika[$w['akcja']] ?? $w['akcja']) ?></td>
            <td class="pmg-num" data-label="Rekord"><?= $w['rekord_id'] !== null ? (int) $w['rekord_id'] : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$wpisy): ?><tr><td colspan="5" class="pmg-empty">Brak wpisów.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    return;
}

// ---------- Konta ----------
if ($sub !== 'konta') { echo '<div class="pmg-alert pmg-alert--error" role="alert">' . pmg_ikona('blad') . '<p>Nieznany widok.</p></div>'; return; }

$error = '';
const MODULY_REDAKTORA = ['aktualnosci' => 'Aktualności', 'czlonkowie' => 'Członkowie', 'pmsession' => 'PM Session'];

// Ilu jest innych aktywnych administratorów z ustawionym hasłem (poza kontem $id) — chroni ostatniego admina.
function inni_aktywni_admini($id)
{
    $st = pmg_db()->prepare("SELECT COUNT(*) FROM pmg_uzytkownicy WHERE rola='admin' AND aktywny=1 AND haslo IS NOT NULL AND id <> ?");
    $st->execute([$id]);
    return (int) $st->fetchColumn();
}

function link_zaproszenia($token)
{
    return ($GLOBALS['https'] ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/?t=' . $token;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'zapros' || $action === 'edytuj') {
        $imie = mb_substr(trim((string) ($_POST['imie_nazwisko'] ?? '')), 0, 100);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $rola = ($_POST['rola'] ?? '') === 'admin' ? 'admin' : 'redaktor';
        $moduly = implode(',', array_intersect((array) ($_POST['moduly'] ?? []), array_keys(MODULY_REDAKTORA)));

        if ($imie === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Podaj imię i nazwisko oraz poprawny e-mail.';
        } elseif ($action === 'zapros') {
            try {
                $token = bin2hex(random_bytes(32));
                $st = pmg_db()->prepare('INSERT INTO pmg_uzytkownicy (imie_nazwisko, email, rola, moduly, aktywny, token_hash, token_do) VALUES (?,?,?,?,1,?,DATE_ADD(NOW(), INTERVAL 72 HOUR))');
                $st->execute([$imie, $email, $rola, $moduly, hash('sha256', $token)]);
                $nowyId = (int) pmg_db()->lastInsertId();
                loguj('konta', 'zaproszenie', $nowyId);
                $_SESSION['flash'] = 'Konto utworzone.';
                $_SESSION['flash_link'] = link_zaproszenia($token);
                go('?m=konta');
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Konto z tym e-mailem już istnieje.' : 'Błąd zapisu.';
            }
        } else { // edytuj
            $st = pmg_db()->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ?');
            $st->execute([$id]);
            $target = $st->fetch();
            if (!$target) {
                $error = 'Nie znaleziono konta.';
            } else {
                $byl_aktywnym_adminem = $target['rola'] === 'admin' && (int) $target['aktywny'] === 1 && $target['haslo'] !== null;
                if ($byl_aktywnym_adminem && $rola !== 'admin' && inni_aktywni_admini($id) === 0) {
                    $error = 'Nie można zdegradować ostatniego aktywnego administratora.';
                } else {
                    try {
                        $st2 = pmg_db()->prepare('UPDATE pmg_uzytkownicy SET imie_nazwisko=?, email=?, rola=?, moduly=? WHERE id=?');
                        $st2->execute([$imie, $email, $rola, $moduly, $id]);
                        loguj('konta', 'edycja', $id);
                        $_SESSION['flash'] = 'Zapisano.';
                        go('?m=konta');
                    } catch (PDOException $e) {
                        $error = $e->getCode() === '23000' ? 'Konto z tym e-mailem już istnieje.' : 'Błąd zapisu.';
                    }
                }
            }
        }
        if ($error !== '') $edit = ['id' => $id, 'imie_nazwisko' => $imie, 'email' => $email, 'rola' => $rola, 'moduly' => $moduly];

    } elseif ($action === 'blokuj' || $action === 'odblokuj') {
        $st = pmg_db()->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ?');
        $st->execute([$id]);
        $target = $st->fetch();
        if (!$target) {
            $error = 'Nie znaleziono konta.';
        } elseif ($action === 'blokuj') {
            $jest_aktywnym_adminem = $target['rola'] === 'admin' && (int) $target['aktywny'] === 1 && $target['haslo'] !== null;
            if ($jest_aktywnym_adminem && inni_aktywni_admini($id) === 0) {
                $error = 'Nie można zablokować ostatniego aktywnego administratora.';
            } else {
                pmg_db()->prepare('UPDATE pmg_uzytkownicy SET aktywny=0, token_hash=NULL, token_do=NULL WHERE id=?')->execute([$id]);
                loguj('konta', 'blokada', $id);
                $_SESSION['flash'] = 'Konto zablokowane.';
                go('?m=konta');
            }
        } else {
            pmg_db()->prepare('UPDATE pmg_uzytkownicy SET aktywny=1 WHERE id=?')->execute([$id]);
            loguj('konta', 'odblokowanie', $id);
            $_SESSION['flash'] = 'Konto odblokowane.';
            go('?m=konta');
        }

    } elseif ($action === 'reset') {
        $st = pmg_db()->prepare("SELECT COUNT(*) FROM pmg_uzytkownicy WHERE id = ? AND rola='admin' AND aktywny=1 AND haslo IS NOT NULL");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() && inni_aktywni_admini($id) === 0) {
            $error = 'Nie można zresetować hasła ostatniego aktywnego administratora — najpierw dodaj drugiego.';
        } else {
            $token = bin2hex(random_bytes(32));
            pmg_db()->prepare('UPDATE pmg_uzytkownicy SET haslo=NULL, token_hash=?, token_do=DATE_ADD(NOW(), INTERVAL 72 HOUR) WHERE id=?')
                ->execute([hash('sha256', $token), $id]);
            loguj('konta', 'reset', $id);
            $_SESSION['flash'] = 'Nowy link gotowy do przekazania.';
            $_SESSION['flash_link'] = link_zaproszenia($token);
            go('?m=konta');
        }
    }
}

$pokazLink = $_SESSION['flash_link'] ?? '';
unset($_SESSION['flash_link']);

if (!isset($edit)) {
    $edit = null;
    if (isset($_GET['nowy'])) {
        $edit = ['id' => 0, 'imie_nazwisko' => '', 'email' => '', 'rola' => 'redaktor', 'moduly' => ''];
    } elseif (isset($_GET['id'])) {
        $st = pmg_db()->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ?');
        $st->execute([(int) $_GET['id']]);
        $edit = $st->fetch() ?: null;
    }
}

if ($edit !== null) {
    $pmgNaglowek = [
        'tytul' => $edit['id'] ? 'Edytuj konto' : 'Nowe konto',
        'opis' => $edit['id'] ? (string) $edit['email'] : 'Po zapisaniu zobaczysz tu link do przekazania nowej osobie (ważny 72 h).',
        'wstecz' => ['href' => '?m=konta', 'etykieta' => 'Konta'],
    ];
} else {
    $pmgNaglowek = [
        'akcje' => [
            ['href' => '?m=konta&nowy', 'etykieta' => '+ Nowe konto', 'rodzaj' => 'primary'],
        ],
    ];
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>

<?php if ($edit !== null): ?>
  <form class="pmg-card" method="post" data-pmg-niezapisane>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="a" value="<?= $edit['id'] ? 'edytuj' : 'zapros' ?>">
    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <label for="imie_nazwisko">Imię i nazwisko</label>
    <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" value="<?= h($edit['imie_nazwisko']) ?>" required>
    <label for="email">E-mail (login)</label>
    <input type="email" id="email" name="email" maxlength="150" value="<?= h($edit['email']) ?>" required>
    <fieldset class="pmg-fieldset">
      <legend class="pmg-legend">Rola</legend>
      <p class="pmg-hint" id="rola_h">Administrator ma dostęp do wszystkich modułów oraz do kont, dziennika i kopii bazy.</p>
      <div class="pmg-options">
        <label class="pmg-option"><input type="radio" name="rola" value="redaktor" aria-describedby="rola_h"<?= $edit['rola'] === 'redaktor' ? ' checked' : '' ?>><span>Redaktor</span></label>
        <label class="pmg-option"><input type="radio" name="rola" value="admin" aria-describedby="rola_h"<?= $edit['rola'] === 'admin' ? ' checked' : '' ?>><span>Administrator</span></label>
      </div>
    </fieldset>
    <fieldset class="pmg-fieldset">
      <legend class="pmg-legend">Moduły (dla redaktora)</legend>
      <p class="pmg-hint" id="moduly_h">Dotyczy tylko roli Redaktor.</p>
      <div class="pmg-options">
        <?php $wybrane = explode(',', $edit['moduly']); foreach (MODULY_REDAKTORA as $mk => $ml): ?>
          <label class="pmg-option"><input type="checkbox" name="moduly[]" value="<?= $mk ?>" aria-describedby="moduly_h"<?= in_array($mk, $wybrane, true) ? ' checked' : '' ?>><span><?= h($ml) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="pmg-form-actions">
      <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
      <a class="pmg-btn pmg-btn--secondary" href="?m=konta">Anuluj</a>
    </div>
  </form>

<?php else: ?>
  <?php if ($pokazLink): ?>
    <div class="pmg-card">
      <h2 class="pmg-h2">Link zaproszenia</h2>
      <label for="link-zaproszenia">Link do przekazania tej osobie — ważny 72 h</label>
      <p class="pmg-hint" id="link-zaproszenia_h">Wyślij go tej osobie — po otwarciu ustawi swoje hasło.</p>
      <div class="pmg-copy">
        <input type="text" id="link-zaproszenia" readonly value="<?= h($pokazLink) ?>" aria-describedby="link-zaproszenia_h">
        <button type="button" class="pmg-btn pmg-btn--secondary" data-pmg-kopiuj="link-zaproszenia" hidden><?= pmg_ikona('kopiuj') ?>Kopiuj link</button>
      </div>
      <p class="pmg-hint" role="status" data-pmg-kopiuj-status></p>
    </div>
  <?php endif; ?>
  <div class="pmg-table-wrap">
    <table class="pmg-table pmg-table--klikalna">
      <caption class="pmg-vh">Konta</caption>
      <thead><tr>
        <th scope="col">Imię i nazwisko</th><th scope="col">Rola</th><th scope="col">Moduły</th><th scope="col">Status</th><th scope="col">Akcje</th>
      </tr></thead>
      <tbody>
      <?php foreach (pmg_db()->query('SELECT * FROM pmg_uzytkownicy ORDER BY imie_nazwisko') as $k): ?>
        <?php
          if ($k['rola'] === 'admin') {
              $modulyTekst = 'Wszystkie';
          } else {
              $wybraneL = array_filter(explode(',', $k['moduly']), function ($mk) { return $mk !== ''; });
              $etykietyL = array_map(function ($mk) { return MODULY_REDAKTORA[$mk] ?? $mk; }, $wybraneL);
              $modulyTekst = $etykietyL ? implode(', ', $etykietyL) : '—';
          }
          if (!$k['aktywny']) { $statusKlasa = 'pmg-chip--danger'; $statusTekst = 'Zablokowane'; }
          elseif ($k['haslo'] === null) { $statusKlasa = 'pmg-chip--warning'; $statusTekst = 'Czeka na hasło'; }
          else { $statusKlasa = 'pmg-chip--success'; $statusTekst = 'Aktywne'; }
        ?>
        <tr>
          <td class="pmg-td-main" data-label="Imię i nazwisko">
            <a class="pmg-row-link" href="?m=konta&id=<?= (int) $k['id'] ?>"><?= h($k['imie_nazwisko']) ?></a>
            <span class="pmg-row-sub"><?= h($k['email']) ?></span>
          </td>
          <td data-label="Rola"><?= $k['rola'] === 'admin' ? 'Administrator' : 'Redaktor' ?></td>
          <td data-label="Moduły"><?= h($modulyTekst) ?></td>
          <td data-label="Status"><span class="pmg-chip <?= $statusKlasa ?>"><?= $statusTekst ?></span></td>
          <td class="pmg-td-actions" data-label="Akcje">
            <a class="pmg-btn pmg-btn--text pmg-btn--sm" href="?m=konta&id=<?= (int) $k['id'] ?>">Edytuj<span class="pmg-vh"> <?= h($k['imie_nazwisko']) ?></span></a>
            <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="reset"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit"><?= $k['haslo'] === null ? 'Link zaproszenia' : 'Resetuj hasło' ?></button></form>
            <?php if ($k['aktywny']): ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="blokuj"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="pmg-btn pmg-btn--danger-outline pmg-btn--sm" type="submit">Zablokuj</button></form>
            <?php else: ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="odblokuj"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit">Odblokuj</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
