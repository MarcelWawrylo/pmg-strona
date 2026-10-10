<?php
// Moduł administracyjny (tylko rola 'admin'): konta, dziennik zmian, kopia bazy, ustawienia (2b).
// Wołany wyłącznie z index.php (?m=konta|dziennik|kopia|ustawienia).
defined('PMG_PANEL') || exit;

$sub = $_GET['m'] ?? '';

// ---------- Kopia bazy ----------
// GET pokazuje tylko ekran z przyciskiem; sam plik (hasze haseł, tokeny, e-maile) wydaje wyłącznie POST z tokenem
// CSRF — przeglądarka ani rozszerzenie nie pobierze go przy samym wejściu w link.
if ($sub === 'kopia' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <form class="pmg-card" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="kopia">
      <p>Plik <code>.sql</code> zawiera całą bazę, także e-maile i skróty haseł kont panelu — przechowuj go bezpiecznie i nie wysyłaj dalej. Nie zawiera zdjęć z katalogu <code>uploads/</code>.</p>
      <div class="pmg-form-actions"><button class="pmg-btn pmg-btn--primary" type="submit">Pobierz kopię bazy</button></div>
    </form>
    <?php
    return;
}
// Strumień SQL, bez szablonu.
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
// (lib.php), ale edytuje je moduł pmsession w etapie 2d — nie ten formularz. Rekrutację (rekrutacja_*) edytuje osobny
// moduł _rekrutacja.php; zapis poniżej zmienia tylko klucze z $pola, więc kluczy rekrutacji nie rusza.
if ($sub === 'ustawienia') {
    $pola = ['instagram', 'facebook', 'linkedin', 'tiktok', 'email'];

    $st = pmg_db()->prepare('SELECT klucz, wartosc FROM pmg_ustawienia WHERE klucz IN (' . implode(',', array_fill(0, count($pola), '?')) . ')');
    $st->execute($pola);
    $wartosci = array_fill_keys($pola, '');
    foreach ($st->fetchAll() as $r) $wartosci[$r['klucz']] = $r['wartosc'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['a'] ?? '') === 'import') {
        pmg_import_wykonaj('ustawienia');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $wejscie = [
            'instagram' => trim((string) ($_POST['instagram'] ?? '')),
            'facebook' => trim((string) ($_POST['facebook'] ?? '')),
            'linkedin' => trim((string) ($_POST['linkedin'] ?? '')),
            'tiktok' => trim((string) ($_POST['tiktok'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
        ];
        $wartosci = $wejscie; // formularz zachowuje wpisane wartości, jeśli coś jest nie tak

        if (!url_ok($wejscie['instagram'])) $bledyPol['instagram'] = 'Instagram: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        if (!url_ok($wejscie['facebook'])) $bledyPol['facebook'] = 'Facebook: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        if (!url_ok($wejscie['linkedin'])) $bledyPol['linkedin'] = 'LinkedIn: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        if (!url_ok($wejscie['tiktok'])) $bledyPol['tiktok'] = 'TikTok: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
        if ($wejscie['email'] !== '' && !filter_var($wejscie['email'], FILTER_VALIDATE_EMAIL)) $bledyPol['email'] = 'Podaj poprawny adres e-mail albo zostaw puste pole.';

        if (!$bledyPol) {
            $pdo = pmg_db();
            $pdo->beginTransaction();
            $upd = $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?,?)');
            foreach ($pola as $k) $upd->execute([$k, $wejscie[$k]]);
            $pdo->commit();
            loguj('ustawienia', 'edycja', null, 'Media społecznościowe i e-mail');
            $_SESSION['flash'] = 'Zapisano. Zmiany widać na stronie w ciągu 5 minut.';
            go('?m=ustawienia');
        }
    }
    ?>
    <?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>
    <?php pmg_import_blok('ustawienia'); ?>
    <form class="pmg-card" method="post" data-pmg-niezapisane>
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

      <section class="pmg-form-section" aria-labelledby="sek-social">
        <h2 class="pmg-form-section__title" id="sek-social">Media społecznościowe</h2>
        <label for="instagram">Instagram</label>
        <p class="pmg-hint" id="instagram_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="instagram" name="instagram" value="<?= h($wartosci['instagram']) ?>"<?= blad_pola('instagram', 'instagram_h') ?>><?= komunikat_pola('instagram') ?>

        <label for="facebook">Facebook</label>
        <p class="pmg-hint" id="facebook_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="facebook" name="facebook" value="<?= h($wartosci['facebook']) ?>"<?= blad_pola('facebook', 'facebook_h') ?>><?= komunikat_pola('facebook') ?>

        <label for="linkedin">LinkedIn</label>
        <p class="pmg-hint" id="linkedin_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="linkedin" name="linkedin" value="<?= h($wartosci['linkedin']) ?>"<?= blad_pola('linkedin', 'linkedin_h') ?>><?= komunikat_pola('linkedin') ?>

        <label for="tiktok">TikTok</label>
        <p class="pmg-hint" id="tiktok_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
        <input type="text" id="tiktok" name="tiktok" value="<?= h($wartosci['tiktok']) ?>"<?= blad_pola('tiktok', 'tiktok_h') ?>><?= komunikat_pola('tiktok') ?>
      </section>

      <section class="pmg-form-section" aria-labelledby="sek-kontakt">
        <h2 class="pmg-form-section__title" id="sek-kontakt">Kontakt</h2>
        <label for="email">E-mail kontaktowy</label>
        <p class="pmg-hint" id="email_h">Adres pokazywany na stronie (stopka, Kontakt). Puste pole = strona pokazuje obecny adres.</p>
        <input type="email" id="email" name="email" value="<?= h($wartosci['email']) ?>"<?= blad_pola('email', 'email_h') ?>><?= komunikat_pola('email') ?>
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
    // Retencja (RODO: imię i nazwisko + akcje): wpisy starsze niż 12 miesięcy znikają przy wejściu do dziennika.
    pmg_db()->exec('DELETE FROM pmg_dziennik WHERE kiedy < NOW() - INTERVAL 12 MONTH');
    // LEFT JOIN: wpis bez konta (nieudane logowanie na nieznany adres) też jest widoczny — jako „Nieznana osoba”.
    $wpisy = pmg_db()->query(
        'SELECT d.kiedy, d.modul, d.akcja, d.rekord_id, d.opis, u.imie_nazwisko
         FROM pmg_dziennik d LEFT JOIN pmg_uzytkownicy u ON u.id = d.uzytkownik_id
         ORDER BY d.id DESC LIMIT 200'
    )->fetchAll();
    // Etykiety PL akcji dziennika — tylko widok; nieznany klucz pokazuje surową wartość.
    $pmgAkcjeDziennika = [
        'dodanie' => 'Dodanie', 'edycja' => 'Edycja', 'usuniecie' => 'Usunięcie',
        'zaproszenie' => 'Zaproszenie', 'blokada' => 'Blokada', 'odblokowanie' => 'Odblokowanie',
        'reset' => 'Reset hasła', 'haslo' => 'Zmiana własnego hasła', 'biezaca' => 'Ustawienie bieżącej edycji', 'pobranie' => 'Pobranie kopii', 'kolejnosc' => 'Zmiana kolejności', 'import' => 'Wczytanie treści ze strony', 'przywrocenie' => 'Przywrócenie tekstu ze strony',
        'galeria' => 'Zmiana galerii', 'logowanie' => 'Logowanie', 'nieudane_logowanie' => 'Nieudane logowanie', 'haslo_z_linku' => 'Ustawienie hasła z linku',
    ];
    $modulyDziennika = $etykietyModulow + ['konto' => 'Konto']; // 'konto' = logowania i hasło z linku
    ?>
    <div class="pmg-table-wrap">
      <table class="pmg-table">
        <caption class="pmg-vh">Dziennik zmian, od najnowszych</caption>
        <thead><tr><th scope="col">Zdarzenie</th></tr></thead>
        <tbody>
        <?php foreach ($wpisy as $w): ?>
          <?php
            // Jedno zdanie: „Kto — Czynność: „co” · Moduł · kiedy”. Starsze wpisy (sprzed schematu 10) nie mają opisu — wtedy #id.
            $co = $w['opis'] !== '' ? '„' . $w['opis'] . '”' : ($w['rekord_id'] !== null ? '#' . (int) $w['rekord_id'] : '');
            $kiedy = strtotime((string) $w['kiedy']);
          ?>
          <tr>
            <td data-label="Zdarzenie">
              <strong><?= h($w['imie_nazwisko'] ?? 'Nieznana osoba') ?></strong> — <?= h($pmgAkcjeDziennika[$w['akcja']] ?? $w['akcja']) ?><?= $co !== '' ? ': ' . h($co) : '' ?>
              · <?= h($modulyDziennika[$w['modul']] ?? $w['modul']) ?>
              · <time datetime="<?= h(date('Y-m-d\TH:i:s', $kiedy)) ?>"><?= h(date('d.m.Y H:i', $kiedy)) ?></time>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$wpisy): ?><tr><td class="pmg-empty">Brak wpisów.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    return;
}

// ---------- Konta ----------
if ($sub !== 'konta') { echo '<div class="pmg-alert pmg-alert--error" role="alert">' . pmg_ikona('blad') . '<p>Nieznany widok.</p></div>'; return; }

$error = '';
const MODULY_REDAKTORA = ['aktualnosci' => 'Aktualności', 'czlonkowie' => 'Członkowie', 'pmsession' => 'PM Session', 'podcast' => 'Podcast', 'case' => 'Case Koła', 'rekrutacja' => 'Rekrutacja'];
const KONTO_WLASNE = 'Nie możesz zmienić roli, zablokować ani zresetować własnego konta. Hasło zmienisz w „Moje konto”.';

// Ilu jest innych aktywnych administratorów z ustawionym hasłem (poza kontem $id) — chroni ostatniego admina.
// FOR UPDATE: w transakcji (A2 1.5) czyta najnowszy zatwierdzony stan, a nie stary obraz bazy.
function inni_aktywni_admini($id)
{
    $st = pmg_db()->prepare("SELECT COUNT(*) FROM pmg_uzytkownicy WHERE rola='admin' AND aktywny=1 AND haslo IS NOT NULL AND id <> ? FOR UPDATE");
    $st->execute([$id]);
    return (int) $st->fetchColumn();
}

// A2 1.5: na początku transakcji blokuje wiersze administratorów i konta $id jednym zapytaniem (zawsze w kolejności id), więc
// dwa równoczesne żądania (np. dwóch adminów degraduje się nawzajem) czekają na siebie, zamiast oba przejść sprawdzenie
// „ostatniego administratora”. Blokada trwa do commit/rollBack.
function zablokuj_adminow($id)
{
    $st = pmg_db()->prepare("SELECT id FROM pmg_uzytkownicy WHERE rola = 'admin' OR id = ? ORDER BY id FOR UPDATE");
    $st->execute([$id]);
    $st->fetchAll();
}

// A2 3.18: adres panelu z konfiguracji ('adres_panelu'), a nie z nagłówka Host żądania. Gdy klucza nie ma (albo nie zaczyna się
// od http:// lub https://), link składamy jak dawniej: protokół i Host bieżącego żądania + katalog panelu.
function link_zaproszenia($token)
{
    $adres = trim((string) (pmg_config()['adres_panelu'] ?? ''));
    if (preg_match('~^https?://~', $adres)) return rtrim($adres, '/') . '/?t=' . $token;
    return ($GLOBALS['https'] ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/?t=' . $token;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    // Własnego konta nie można zablokować, zresetować ani zmienić mu roli (admin zablokowałby sam siebie).
    $ja = $id === (int) $me['id'];

    if ($ja && in_array($action, ['blokuj', 'odblokuj', 'reset'], true)) {
        $error = KONTO_WLASNE;
    } elseif ($action === 'zapros' || $action === 'edytuj') {
        $imie = mb_substr(trim((string) ($_POST['imie_nazwisko'] ?? '')), 0, 100);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $rola = ($_POST['rola'] ?? '') === 'admin' ? 'admin' : 'redaktor';
        $moduly = implode(',', array_intersect((array) ($_POST['moduly'] ?? []), array_keys(MODULY_REDAKTORA)));
        // Przy własnym koncie pola roli i modułów są wyłączone (nie przychodzą w POST); rola i moduły zostają bez zmian.
        $zmianaWlasnejRoli = $ja && isset($_POST['rola']) && $rola !== $me['rola'];
        if ($ja) { $rola = $me['rola']; $moduly = $me['moduly']; }

        if ($imie === '') $bledyPol['imie_nazwisko'] = 'Podaj imię i nazwisko.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $bledyPol['email'] = 'Podaj poprawny e-mail.';
        if ($bledyPol) {
            // nic nie zapisujemy — formularz wróci z komunikatami przy polach (niżej)
        } elseif ($zmianaWlasnejRoli) {
            $error = KONTO_WLASNE;
        } elseif ($action === 'zapros') {
            try {
                $token = bin2hex(random_bytes(32));
                $st = pmg_db()->prepare('INSERT INTO pmg_uzytkownicy (imie_nazwisko, email, rola, moduly, aktywny, token_hash, token_do) VALUES (?,?,?,?,1,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))'); // A2 3.17: link ważny 24 h
                $st->execute([$imie, $email, $rola, $moduly, hash('sha256', $token)]);
                $nowyId = (int) pmg_db()->lastInsertId();
                loguj('konta', 'zaproszenie', $nowyId, $imie);
                $_SESSION['flash'] = 'Konto utworzone.';
                $_SESSION['flash_link'] = link_zaproszenia($token);
                go('?m=konta');
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Konto z tym e-mailem już istnieje.' : 'Błąd zapisu.';
            }
        } else { // edytuj
            $pdo = pmg_db();
            $pdo->beginTransaction(); // A2 1.5: sprawdzenie ostatniego admina i zmiana w jednej transakcji z blokadą wierszy
            try {
                zablokuj_adminow($id);
                $st = $pdo->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $target = $st->fetch();
                if (!$target) {
                    $error = 'Nie znaleziono konta.';
                } else {
                    $byl_aktywnym_adminem = $target['rola'] === 'admin' && (int) $target['aktywny'] === 1 && $target['haslo'] !== null;
                    if ($byl_aktywnym_adminem && $rola !== 'admin' && inni_aktywni_admini($id) === 0) {
                        $error = 'Nie można zdegradować ostatniego aktywnego administratora.';
                    } else {
                        $st2 = $pdo->prepare('UPDATE pmg_uzytkownicy SET imie_nazwisko=?, email=?, rola=?, moduly=? WHERE id=?');
                        $st2->execute([$imie, $email, $rola, $moduly, $id]);
                    }
                }
                if ($error === '') $pdo->commit(); else $pdo->rollBack();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getCode() === '23000' ? 'Konto z tym e-mailem już istnieje.' : 'Błąd zapisu.';
            }
            if ($error === '') {
                loguj('konta', 'edycja', $id, $imie);
                $_SESSION['flash'] = 'Zapisano.';
                go('?m=konta');
            }
        }
        if ($error !== '' || $bledyPol) $edit = ['id' => $id, 'imie_nazwisko' => $imie, 'email' => $email, 'rola' => $rola, 'moduly' => $moduly];

    } elseif ($action === 'blokuj' || $action === 'odblokuj') {
        $pdo = pmg_db();
        $pdo->beginTransaction(); // A2 1.5: jak przy edycji — sprawdzenie i blokada konta w jednej transakcji
        try {
            zablokuj_adminow($id);
            $st = $pdo->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ? FOR UPDATE');
            $st->execute([$id]);
            $target = $st->fetch();
            if (!$target) {
                $error = 'Nie znaleziono konta.';
            } elseif ($action === 'blokuj') {
                $jest_aktywnym_adminem = $target['rola'] === 'admin' && (int) $target['aktywny'] === 1 && $target['haslo'] !== null;
                if ($jest_aktywnym_adminem && inni_aktywni_admini($id) === 0) {
                    $error = 'Nie można zablokować ostatniego aktywnego administratora.';
                } else {
                    $pdo->prepare('UPDATE pmg_uzytkownicy SET aktywny=0, token_hash=NULL, token_do=NULL WHERE id=?')->execute([$id]);
                }
            } else {
                $pdo->prepare('UPDATE pmg_uzytkownicy SET aktywny=1 WHERE id=?')->execute([$id]);
            }
            if ($error === '') $pdo->commit(); else $pdo->rollBack();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        if ($error === '') {
            loguj('konta', $action === 'blokuj' ? 'blokada' : 'odblokowanie', $id, $target['imie_nazwisko']);
            $_SESSION['flash'] = $action === 'blokuj' ? 'Konto zablokowane.' : 'Konto odblokowane.';
            go('?m=konta');
        }

    } elseif ($action === 'reset') {
        $pdo = pmg_db();
        $pdo->beginTransaction(); // A2 1.5: jak przy edycji — sprawdzenie i reset w jednej transakcji
        try {
            zablokuj_adminow($id);
            $st = $pdo->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ? FOR UPDATE');
            $st->execute([$id]);
            $target = $st->fetch();
            if (!$target) {
                $error = 'Nie znaleziono konta.';
            } elseif ($target['rola'] === 'admin' && (int) $target['aktywny'] === 1 && $target['haslo'] !== null && inni_aktywni_admini($id) === 0) {
                $error = 'Nie można zresetować hasła ostatniego aktywnego administratora — najpierw dodaj drugiego.';
            } else {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('UPDATE pmg_uzytkownicy SET haslo=NULL, token_hash=?, token_do=DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id=?') // A2 3.17: 24 h
                    ->execute([hash('sha256', $token), $id]);
            }
            if ($error === '') $pdo->commit(); else $pdo->rollBack();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        if ($error === '') {
            loguj('konta', 'reset', $id, $target['imie_nazwisko']);
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
        'opis' => $edit['id'] ? (string) $edit['email'] : 'Po zapisaniu zobaczysz tu link do przekazania nowej osobie (ważny 24 h).',
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
    <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" value="<?= h($edit['imie_nazwisko']) ?>" required<?= blad_pola('imie_nazwisko') ?>><?= komunikat_pola('imie_nazwisko') ?>
    <label for="email">E-mail (login)</label>
    <input type="email" id="email" name="email" maxlength="150" value="<?= h($edit['email']) ?>" required<?= blad_pola('email') ?>><?= komunikat_pola('email') ?>
    <?php $wlasne = (int) $edit['id'] === (int) $me['id']; ?>
    <fieldset class="pmg-fieldset"<?= $wlasne ? ' disabled' : '' ?>>
      <legend class="pmg-legend">Rola</legend>
      <p class="pmg-hint" id="rola_h"><?= $wlasne ? 'To Twoje konto: roli i modułów nie zmienisz sam(a). Może to zrobić inny administrator.' : 'Administrator ma dostęp do wszystkich modułów oraz do kont, dziennika i kopii bazy.' ?></p>
      <div class="pmg-options">
        <label class="pmg-option"><input type="radio" name="rola" value="redaktor" aria-describedby="rola_h"<?= $edit['rola'] === 'redaktor' ? ' checked' : '' ?>><span>Redaktor</span></label>
        <label class="pmg-option"><input type="radio" name="rola" value="admin" aria-describedby="rola_h"<?= $edit['rola'] === 'admin' ? ' checked' : '' ?>><span>Administrator</span></label>
      </div>
    </fieldset>
    <fieldset class="pmg-fieldset"<?= $wlasne ? ' disabled' : '' ?>>
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
      <label for="link-zaproszenia">Link do przekazania tej osobie — ważny 24 h</label>
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
            <?php if ((int) $k['id'] === (int) $me['id']): ?><span class="pmg-chip pmg-chip--accent">To Ty</span><?php endif; ?>
          </td>
          <td data-label="Rola"><?= $k['rola'] === 'admin' ? 'Administrator' : 'Redaktor' ?></td>
          <td data-label="Moduły"><?= h($modulyTekst) ?></td>
          <td data-label="Status"><span class="pmg-chip <?= $statusKlasa ?>"><?= $statusTekst ?></span></td>
          <td class="pmg-td-actions" data-label="Akcje">
            <a class="pmg-btn pmg-btn--text pmg-btn--sm" href="?m=konta&id=<?= (int) $k['id'] ?>">Edytuj<span class="pmg-vh"> <?= h($k['imie_nazwisko']) ?></span></a>
            <?php if ((int) $k['id'] === (int) $me['id']): ?>
              <a class="pmg-btn pmg-btn--secondary pmg-btn--sm" href="?m=konto">Moje konto</a>
            <?php else: ?>
            <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="reset"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit"<?= $k['haslo'] === null ? '' : ' data-pmg-potwierdz="' . h('Zresetować hasło: ' . $k['imie_nazwisko'] . '? Obecne hasło przestanie działać od razu, a Ty dostaniesz nowy link do przekazania.') . '"' ?>><?= $k['haslo'] === null ? 'Link zaproszenia' : 'Resetuj hasło' ?></button></form>
            <?php if ($k['aktywny']): ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="blokuj"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="pmg-btn pmg-btn--danger-outline pmg-btn--sm" type="submit" data-pmg-potwierdz="<?= h('Zablokować konto: ' . $k['imie_nazwisko'] . '? Ta osoba od razu straci dostęp do panelu.') ?>">Zablokuj</button></form>
            <?php else: ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="odblokuj"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button class="pmg-btn pmg-btn--secondary pmg-btn--sm" type="submit">Odblokuj</button></form>
            <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
