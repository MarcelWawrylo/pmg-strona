<?php
// Panel PMG — jedyny punkt wejścia. Sesja, CSRF, logowanie/konta, router modułów (?m=), szablon.
require __DIR__ . '/../api/lib.php';
define('PMG_PANEL', 1);

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(['lifetime' => 0, 'path' => dirname($_SERVER['SCRIPT_NAME']), 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_name('pmgpanel');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

pmg_migrate();

// Hasło stałej długości do porównań przy logowaniu na nieistniejące konto (stały czas odpowiedzi).
const DUMMY_HASH = '$2y$10$orSlF6sUaQo89zN3NskTuOtXFvhAKDwJFtiZnQzJjJnZMJhWciWfy';
const KOLORY = ['pink' => 'Różowy', 'purple' => 'Fioletowy', 'blue' => 'Niebieski', 'violet' => 'Liliowy'];
const ZDJECIA = ['aktualnosci' => [16 / 9, 1600], 'czlonkowie' => [1, 800], 'pmsession' => [1, 800]];
const MODULY = [
    'aktualnosci' => 'aktualnosci', 'czlonkowie' => 'czlonkowie', 'pmsession' => 'pmsession',
    'konta' => 'admin', 'dziennik' => 'admin', 'ustawienia' => 'admin', 'kopia' => 'admin',
];

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function csrf() { return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32))); }
function go($query = '') { header('Location: index.php' . $query, true, 303); exit; }

// Czy zalogowany $me ma dostęp do modułu $modul ('admin' = tylko administrator).
function wolno($modul)
{
    global $me;
    return $me && ($me['rola'] === 'admin' || in_array($modul, explode(',', $me['moduly']), true));
}

// Wpis do dziennika zmian po każdym udanym zapisie.
function loguj($modul, $akcja, $id = null)
{
    global $me;
    pmg_db()->prepare('INSERT INTO pmg_dziennik (uzytkownik_id, modul, akcja, rekord_id) VALUES (?,?,?,?)')
        ->execute([$me['id'], $modul, $akcja, $id]);
}

// Czy ekran "pierwsze konto" może w ogóle przyjąć zgłoszenie: albo instalacja ma z przeszłości
// wspólne hasło panelu (panel_hash), albo w config.php jest ustawione jednorazowe setup_haslo (min. 12 znaków).
function pierwsze_ok($cfg)
{
    return ($cfg['panel_hash'] ?? '') !== '' || mb_strlen((string) ($cfg['setup_haslo'] ?? '')) >= 12;
}

// Puste pole albo adres zaczynający się od https:// i poprawny wg FILTER_VALIDATE_URL.
function url_ok($v)
{
    return $v === '' || (strpos($v, 'https://') === 0 && filter_var($v, FILTER_VALIDATE_URL) !== false);
}

// Zapis zdjęcia dla modułu $modul (proporcje i szerokość z ZDJECIA). Zwraca ścieżkę względną albo rzuca komunikat.
function save_image($file, $modul)
{
    list($proporcja, $maxSzer) = ZDJECIA[$modul];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Nie udało się wgrać pliku (kod ' . (int) $file['error'] . ').');
    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('Zdjęcie jest większe niż 10 MB.');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Dozwolone formaty: JPG, PNG, WebP.');
    list($w, $hgt) = $info;
    if ($w * $hgt > 40000000) throw new RuntimeException('Zdjęcie ma za dużą rozdzielczość (maks. ok. 40 megapikseli).');
    if (abs($w / $hgt - $proporcja) > 0.03) {
        $opis = abs($proporcja - 1) < 0.001 ? '1:1 (kwadrat)' : '16:9';
        throw new RuntimeException('Zdjęcie musi mieć proporcje ' . $opis . ' (wgrane: ' . $w . '×' . $hgt . ' px).');
    }
    $dir = __DIR__ . '/../uploads/' . $modul . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6));

    // Z biblioteką GD: zmniejsz do maxSzer px i zapisz jako JPG (lżejsza strona). Bez GD: limit 1,5 MB.
    $open = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$types[$info[2]]];
    if (function_exists($open) && function_exists('imagejpeg')) {
        $src = $open($file['tmp_name']);
        $nw = min($maxSzer, $w);
        $dst = imagecreatetruecolor($nw, (int) round($nw * $hgt / $w));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $hgt);
        imagejpeg($dst, $dir . $name . '.jpg', 82);
        return 'uploads/' . $modul . '/' . $name . '.jpg';
    }
    if ($file['size'] > 1536 * 1024) throw new RuntimeException('Serwer nie może zmniejszyć zdjęcia — wgraj plik do 1,5 MB.');
    move_uploaded_file($file['tmp_name'], $dir . $name . '.' . $types[$info[2]]);
    return 'uploads/' . $modul . '/' . $name . '.' . $types[$info[2]];
}

function drop_image($path)
{
    if ($path && preg_match('~^uploads/(aktualnosci|czlonkowie|pmsession)/[0-9a-f-]+\.(jpg|png|webp)$~', $path)) @unlink(__DIR__ . '/../' . $path);
}

// Sprawdza opis zdjęcia (wymagany przy nowym pliku i przy zachowaniu istniejącego) i wgrywa nowy plik, jeśli podano.
// Zwraca ścieżkę do zapisania w bazie (nową albo — bez wgrania — dotychczasową $old).
function zdjecie($modul, $old)
{
    $alt = trim((string) ($_POST['zdjecie_alt'] ?? ''));
    $upload = !empty($_FILES['zdjecie']['name']);
    if (($upload || $old) && $alt === '') throw new RuntimeException('Dodaj opis zdjęcia (dla osób niewidomych).');
    return $upload ? save_image($_FILES['zdjecie'], $modul) : $old;
}

// ---------- $me: ładowany z bazy przy każdym żądaniu (blokada/zmiana roli działa od razu) ----------
$me = null;
$wygasla = false;
if (!empty($_SESSION['uid'])) {
    if (time() - ($_SESSION['t'] ?? 0) > 7200) {
        $wygasla = true;
        session_regenerate_id(true);
        $_SESSION = [];
    } else {
        $_SESSION['t'] = time();
        $st = pmg_db()->prepare('SELECT * FROM pmg_uzytkownicy WHERE id = ? AND aktywny = 1');
        $st->execute([(int) $_SESSION['uid']]);
        $me = $st->fetch() ?: null;
        // Reset/zmiana hasła (haslo=NULL albo nowy hash) musi kończyć starą sesję od razu, nie dopiero po jej wygaśnięciu.
        if ($me && !hash_equals($_SESSION['ph'] ?? '', hash('sha256', (string) $me['haslo']))) $me = null;
        if (!$me) { session_regenerate_id(true); $_SESSION = []; }
    }
}

// ---------- CSRF: jedno miejsce dla każdego POST (formularze przed i po zalogowaniu) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Sesja wygasła — odśwież stronę i spróbuj ponownie.');
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ---------- Akcje poza modułami: pierwsze konto / logowanie / ustawienie hasła / wylogowanie ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        go();
    }

    if (!$me && $action === 'pierwsze') {
        $cfg = pmg_config();
        if (!pierwsze_ok($cfg)) {
            http_response_code(403);
            exit("Aby założyć pierwsze konto, wpisz w api/config.php hasło instalacyjne 'setup_haslo' (min. 12 znaków) — patrz README.");
        }
        $panelHash = (string) ($cfg['panel_hash'] ?? '');
        if (!pmg_rate_ok('login', 20, 900)) {
            $error = 'Za dużo prób. Spróbuj za 15 minut.';
        } elseif ($panelHash !== '' && !password_verify((string) ($_POST['stare_haslo'] ?? ''), $panelHash)) {
            $error = 'Nieprawidłowe dotychczasowe hasło panelu.';
        } elseif ($panelHash === '' && !hash_equals((string) $cfg['setup_haslo'], (string) ($_POST['setup_haslo'] ?? ''))) {
            $error = 'Nieprawidłowe hasło instalacyjne.';
        } else {
            $imie = mb_substr(trim((string) ($_POST['imie_nazwisko'] ?? '')), 0, 100);
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $haslo = (string) ($_POST['haslo'] ?? '');
            if ($imie === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Podaj imię i nazwisko oraz poprawny e-mail.';
            } elseif (mb_strlen($haslo) < 12) {
                $error = 'Hasło musi mieć co najmniej 12 znaków.';
            } else {
                // Wyścig dwóch równoczesnych POST-ów "pierwsze konto": INSERT wykonuje się tylko, gdy tabela
                // wciąż jest pusta (jedno zapytanie, bez osobnego SELECT COUNT przed nim).
                $hash = password_hash($haslo, PASSWORD_DEFAULT);
                $st = pmg_db()->prepare(
                    'INSERT INTO pmg_uzytkownicy (imie_nazwisko, email, haslo, rola, moduly, aktywny)
                     SELECT ?,?,?,?,?,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM pmg_uzytkownicy)'
                );
                $st->execute([$imie, $email, $hash, 'admin', 'aktualnosci,czlonkowie,pmsession']);
                if ($st->rowCount() === 0) {
                    http_response_code(403);
                    exit('To konto już istnieje — zaloguj się.');
                }
                session_regenerate_id(true);
                $_SESSION['uid'] = (int) pmg_db()->lastInsertId();
                $_SESSION['t'] = time();
                $_SESSION['ph'] = hash('sha256', $hash);
                go();
            }
        }
    }

    if (!$me && $action === 'login') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $haslo = (string) ($_POST['haslo'] ?? '');
        if (!pmg_rate_ok('login', 20, 900)) {
            $error = 'Za dużo prób logowania. Spróbuj za 15 minut.';
        } else {
            // Limit na konto: najpierw SELECT, żeby kluczem był id konta (nie surowy e-mail z żądania) —
            // MariaDB (utf8mb4_unicode_ci) dopasowuje konto również po wariantach z akcentami, więc klucz
            // po samym stringu dałby się obejść. Konto nieistniejące -> klucz to znormalizowany e-mail.
            $st = pmg_db()->prepare('SELECT * FROM pmg_uzytkownicy WHERE email = ? AND aktywny = 1');
            $st->execute([$email]);
            $u = $st->fetch();
            $klucz = $u ? 'u' . $u['id'] : $email;
            if (!pmg_rate_ok('login_konto', 5, 900, $klucz, false)) {
                $error = 'Za dużo prób logowania. Spróbuj za 15 minut.';
            } else {
                $ma_haslo = $u && $u['haslo'] !== null;
                $ok = password_verify($haslo, $ma_haslo ? $u['haslo'] : DUMMY_HASH) && $ma_haslo;
                if ($ok) {
                    session_regenerate_id(true);
                    $_SESSION['uid'] = (int) $u['id'];
                    $_SESSION['t'] = time();
                    $_SESSION['ph'] = hash('sha256', (string) $u['haslo']);
                    pmg_db()->prepare('UPDATE pmg_uzytkownicy SET ostatnie_logowanie = NOW() WHERE id = ?')->execute([$u['id']]);
                    go();
                } else {
                    pmg_rate_ok('login_konto', 5, 900, $klucz); // liczy się tylko nieudana próba
                    $error = 'Nieprawidłowy e-mail lub hasło.';
                }
            }
        }
    }

    if (!$me && $action === 'haslo') {
        $t = (string) ($_POST['t'] ?? '');
        $haslo = (string) ($_POST['haslo'] ?? '');
        $st = pmg_db()->prepare('SELECT id FROM pmg_uzytkownicy WHERE token_hash = ? AND token_do > NOW() AND aktywny = 1');
        $st->execute([hash('sha256', $t)]);
        $u = $st->fetch();
        if (!$u) {
            $error = 'Link jest nieważny lub wygasł — poproś administratora o nowy.';
        } elseif (mb_strlen($haslo) < 12) {
            $error = 'Hasło musi mieć co najmniej 12 znaków.';
        } else {
            pmg_db()->prepare('UPDATE pmg_uzytkownicy SET haslo = ?, token_hash = NULL, token_do = NULL WHERE id = ?')
                ->execute([password_hash($haslo, PASSWORD_DEFAULT), $u['id']]);
            $_SESSION['flash'] = 'Hasło ustawione — możesz się zalogować.';
            go();
        }
    }
}

// ---------- Widok bez zalogowania ----------
$widok = '';
if (!$me) {
    $liczbaKont = (int) pmg_db()->query('SELECT COUNT(*) FROM pmg_uzytkownicy')->fetchColumn();
    if ($liczbaKont === 0) $widok = 'pierwsze';
    elseif (isset($_GET['t'])) $widok = 'haslo';
    else $widok = 'logowanie';
}

// ---------- Router modułów ----------
$m = $_GET['m'] ?? '';
$tresc = null;
if ($me && $m !== '') {
    if (!isset(MODULY[$m])) {
        http_response_code(404);
        $tresc = '<p class="msg msg--err" role="alert">Nieznany moduł.</p>';
    } elseif (!wolno(MODULY[$m])) {
        http_response_code(403);
        $tresc = '<p class="msg msg--err" role="alert">Nie masz dostępu do tego modułu. Jeśli to pomyłka, poproś administratora.</p>';
    } else {
        $plik = __DIR__ . '/_' . (MODULY[$m] === 'admin' ? 'admin' : $m) . '.php';
        if (!is_file($plik)) {
            $tresc = '<p class="msg msg--info">Moduł w przygotowaniu.</p>';
        } else {
            ob_start();
            require $plik;
            $tresc = ob_get_clean();
        }
    }
}

$etykietyModulow = [
    'aktualnosci' => 'Aktualności', 'czlonkowie' => 'Członkowie', 'pmsession' => 'PM Session',
    'konta' => 'Konta', 'dziennik' => 'Dziennik zmian', 'ustawienia' => 'Ustawienia strony', 'kopia' => 'Kopia bazy danych',
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Panel PMG</title>
<style>
  * { box-sizing: border-box; }
  body { font: 16px/1.5 system-ui, sans-serif; color: #141414; background: #F7F6F3; margin: 0; padding: 0 16px 60px; }
  main { max-width: 900px; margin: 0 auto; }
  h1 { font-size: 26px; margin: 24px 0 16px; }
  h2 { font-size: 20px; }
  a { color: #1d46e0; }
  .box { background: #fff; border: 1px solid #e3e1dc; border-radius: 14px; padding: 20px; margin-bottom: 18px; }
  label { display: block; font-weight: 600; margin: 14px 0 4px; }
  fieldset { border: 1px solid #e3e1dc; border-radius: 10px; margin: 14px 0; padding: 10px 14px; }
  fieldset label { display: inline-block; font-weight: 400; margin-right: 16px; }
  legend { font-weight: 600; padding: 0 6px; }
  input[type=text], input[type=email], input[type=date], input[type=time], input[type=password], select, textarea { width: 100%; font: inherit; padding: 10px 12px; border: 1px solid #b9b6ae; border-radius: 8px; background: #fff; }
  textarea { min-height: 200px; }
  .hint { font-size: 14px; color: #555; margin: 4px 0 0; }
  button, .btn { font: inherit; font-weight: 600; padding: 10px 18px; border-radius: 999px; border: 0; background: #141414; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn--light { background: #e9e7e2; color: #141414; }
  .btn--danger { background: #b3123f; }
  .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 18px; }
  .msg { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
  .msg--err { background: #fde8ee; color: #8a0f33; }
  .msg--ok { background: #e7f5ea; color: #1b5e2b; }
  .msg--info { background: #eef1fd; color: #1d2c8b; }
  .tabela { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  td, th { text-align: left; padding: 10px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
  .draft { font-size: 13px; background: #eee; border-radius: 6px; padding: 2px 8px; }
  img.preview { max-width: 320px; width: 100%; border-radius: 8px; display: block; margin-top: 8px; }
  .topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid #e3e1dc; margin-bottom: 20px; }
  .topbar a { text-decoration: none; }
  .topbar__nav { display: flex; flex-wrap: wrap; gap: 12px; margin-left: auto; }
  .topbar form { margin: 0; }
  .kafelki { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
  .kafelek { display: block; background: #fff; border: 1px solid #e3e1dc; border-radius: 14px; padding: 22px 18px; text-decoration: none; color: #141414; font-weight: 600; }
  .kafelek:hover, .kafelek:focus-visible { border-color: #1d46e0; }
  :focus-visible { outline: 3px solid #1d46e0; outline-offset: 2px; }
  @media (max-width: 480px) { body { padding: 0 12px 40px; } .topbar__nav { gap: 8px 14px; } }
</style>
</head>
<body>
<main>
<h1>Panel PMG</h1>

<?php if ($me): ?>
  <div class="topbar">
    <span>Zalogowano: <strong><?= h($me['imie_nazwisko']) ?></strong></span>
    <nav class="topbar__nav">
      <?php foreach ($etykietyModulow as $mk => $ml): if (wolno(MODULY[$mk])): ?>
        <a href="?m=<?= $mk ?>"<?= $mk === $m ? ' aria-current="page"' : '' ?>><?= h($ml) ?></a>
      <?php endif; endforeach; ?>
    </nav>
    <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="logout"><button class="btn--light" type="submit">Wyloguj</button></form>
  </div>
<?php endif; ?>

<?php if ($error): ?><p class="msg msg--err" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($flash): ?><p class="msg msg--ok" role="status"><?= h($flash) ?></p><?php endif; ?>
<?php if ($wygasla): ?><p class="msg msg--err" role="alert">Sesja wygasła z powodu bezczynności — zaloguj się ponownie.</p><?php endif; ?>

<?php if (!$me): ?>

  <?php if ($widok === 'pierwsze'): $cfg = pmg_config(); ?>
    <div class="box">
      <h2>Pierwsze konto administratora</h2>
      <?php if (!pierwsze_ok($cfg)): ?>
        <p class="hint">Aby założyć pierwsze konto, wpisz w <code>api/config.php</code> hasło instalacyjne <code>'setup_haslo'</code> (min. 12 znaków) — patrz README.</p>
      <?php else: ?>
        <p class="hint">Tabela kont jest pusta — to jednorazowy ekran. Załóż konto administratora, żeby dalej zarządzać panelem.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="pierwsze">
          <?php if (($cfg['panel_hash'] ?? '') !== ''): ?>
            <label for="stare_haslo">Dotychczasowe hasło panelu Aktualności</label>
            <p class="hint" id="stare_haslo_h">Ta instalacja miała wcześniej wspólne hasło do panelu — potwierdź je, żeby przejąć dostęp.</p>
            <input type="password" id="stare_haslo" name="stare_haslo" autocomplete="current-password" required aria-describedby="stare_haslo_h">
          <?php else: ?>
            <label for="setup_haslo">Hasło instalacyjne (z pliku api/config.php)</label>
            <p class="hint" id="setup_haslo_h">Jednorazowe hasło ustawione w <code>api/config.php</code> jako <code>setup_haslo</code>.</p>
            <input type="password" id="setup_haslo" name="setup_haslo" autocomplete="off" required aria-describedby="setup_haslo_h">
          <?php endif; ?>
          <label for="imie_nazwisko">Imię i nazwisko</label>
          <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" required autofocus>
          <label for="email">E-mail (login)</label>
          <input type="email" id="email" name="email" maxlength="150" required>
          <label for="haslo">Hasło (min. 12 znaków)</label>
          <p class="hint" id="haslo_h">Użyj hasła, którego nie używasz nigdzie indziej.</p>
          <input type="password" id="haslo" name="haslo" minlength="12" required autocomplete="new-password" aria-describedby="haslo_h">
          <div class="row"><button type="submit">Załóż konto</button></div>
        </form>
      <?php endif; ?>
    </div>

  <?php elseif ($widok === 'haslo'): ?>
    <div class="box">
      <h2>Ustaw hasło</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="haslo">
        <input type="hidden" name="t" value="<?= h($_GET['t'] ?? '') ?>">
        <label for="haslo">Nowe hasło (min. 12 znaków)</label>
        <input type="password" id="haslo" name="haslo" minlength="12" required autocomplete="new-password" autofocus>
        <div class="row"><button type="submit">Ustaw hasło</button></div>
      </form>
    </div>

  <?php else: ?>
    <form class="box" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="login">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" autocomplete="username" required autofocus>
      <label for="haslo">Hasło</label>
      <input type="password" id="haslo" name="haslo" autocomplete="current-password" required>
      <div class="row"><button type="submit">Zaloguj</button></div>
    </form>
  <?php endif; ?>

<?php elseif ($m === ''): ?>
  <div class="kafelki">
    <?php foreach ($etykietyModulow as $mk => $ml): if (wolno(MODULY[$mk])): ?>
      <a class="kafelek" href="?m=<?= $mk ?>"><?= h($ml) ?></a>
    <?php endif; endforeach; ?>
  </div>

<?php else: ?>
  <?= $tresc ?>
<?php endif; ?>

</main>
</body>
</html>
