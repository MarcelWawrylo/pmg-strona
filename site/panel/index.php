<?php
// Panel Aktualności: logowanie hasłem, dodawanie / edycja / usuwanie wpisów (PHP 7.4 + MariaDB).
require __DIR__ . '/../api/lib.php';

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime' => 0, 'path' => dirname($_SERVER['SCRIPT_NAME']), 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_name('pmgpanel');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

const KOLORY = ['pink' => 'Różowy', 'purple' => 'Fioletowy', 'blue' => 'Niebieski', 'violet' => 'Liliowy'];
const UPLOAD_DIR = __DIR__ . '/../uploads/aktualnosci/';

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function csrf() { return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32))); }
function go($query = '') { header('Location: index.php' . $query, true, 303); exit; }

function slugify($text)
{
    $text = strtr(mb_strtolower($text, 'UTF-8'), ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z']);
    $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    return substr($text !== '' ? $text : 'wpis', 0, 60);
}

// Zapis zdjęcia 16:9. Zwraca ścieżkę względną do katalogu strony albo rzuca komunikat dla użytkownika.
function save_image($file)
{
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Nie udało się wgrać pliku (kod ' . (int) $file['error'] . ').');
    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('Zdjęcie jest większe niż 10 MB.');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Dozwolone formaty: JPG, PNG, WebP.');
    list($w, $hgt) = $info;
    if (abs($w / $hgt - 16 / 9) > 0.03) throw new RuntimeException('Zdjęcie musi mieć proporcje 16:9 (wgrane: ' . $w . '×' . $hgt . ' px).');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6));

    // Z biblioteką GD: zmniejsz do 1600 px i zapisz jako JPG (lżejsza strona). Bez GD: limit 1,5 MB.
    $open = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$types[$info[2]]];
    if (function_exists($open) && function_exists('imagejpeg')) {
        $src = $open($file['tmp_name']);
        $nw = min(1600, $w);
        $dst = imagecreatetruecolor($nw, (int) round($nw * $hgt / $w));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $hgt);
        imagejpeg($dst, UPLOAD_DIR . $name . '.jpg', 82);
        return 'uploads/aktualnosci/' . $name . '.jpg';
    }
    if ($file['size'] > 1536 * 1024) throw new RuntimeException('Serwer nie może zmniejszyć zdjęcia — wgraj plik do 1,5 MB.');
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name . '.' . $types[$info[2]]);
    return 'uploads/aktualnosci/' . $name . '.' . $types[$info[2]];
}

function drop_image($path)
{
    if ($path && preg_match('~^uploads/aktualnosci/[0-9a-f-]+\.(jpg|png|webp)$~', $path)) @unlink(__DIR__ . '/../' . $path);
}

$cfg = pmg_config();
$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ---------- POST: każda akcja wymaga tokenu CSRF ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf(), (string) ($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Sesja wygasła — odśwież stronę.'); }
    $action = $_POST['a'] ?? '';

    if ($action === 'login') {
        if (!pmg_rate_ok('login', 5, 900)) {
            $error = 'Za dużo prób logowania. Spróbuj za 15 minut.';
        } elseif ($cfg['panel_hash'] !== '' && password_verify((string) ($_POST['haslo'] ?? ''), $cfg['panel_hash'])) {
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            go();
        } else {
            $error = 'Nieprawidłowe hasło.';
        }
    } elseif (empty($_SESSION['ok'])) {
        go();
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        go();
    } elseif ($action === 'delete') {
        $st = pmg_db()->prepare('SELECT zdjecie FROM pmg_aktualnosci WHERE id = ?');
        $st->execute([(int) $_POST['id']]);
        drop_image($st->fetchColumn());
        pmg_db()->prepare('DELETE FROM pmg_aktualnosci WHERE id = ?')->execute([(int) $_POST['id']]);
        $_SESSION['flash'] = 'Wpis usunięty.';
        go();
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
        $f['zdjecie'] = $old['zdjecie'] ?? null;
        try {
            if ($f['tytul'] === '' || $f['zajawka'] === '' || $f['tresc'] === '' || $f['kategoria'] === '') throw new RuntimeException('Uzupełnij tytuł, kategorię, zajawkę i treść.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['data'])) throw new RuntimeException('Podaj datę wpisu.');
            $upload = !empty($_FILES['zdjecie']['name']);
            // opis sprawdzany przed zapisem pliku — inaczej odrzucony wpis zostawiałby osierocone zdjęcie
            if (($upload || $f['zdjecie']) && $f['zdjecie_alt'] === '') throw new RuntimeException('Dodaj opis zdjęcia (dla osób niewidomych).');
            if ($upload) {
                $f['zdjecie'] = save_image($_FILES['zdjecie']);
                if ($old) drop_image($old['zdjecie']);
            }
            if ($id && $old) {
                $st = pmg_db()->prepare('UPDATE pmg_aktualnosci SET data=?, kategoria=?, kolor=?, tytul=?, zajawka=?, tresc=?, zdjecie=?, zdjecie_alt=?, autor=?, opublikowany=? WHERE id=?');
                $st->execute([$f['data'], $f['kategoria'], $f['kolor'], $f['tytul'], $f['zajawka'], $f['tresc'], $f['zdjecie'], $f['zdjecie_alt'], $f['autor'], $f['opublikowany'], $id]);
            } else {
                $base = $slug = slugify($f['tytul']);
                $st = pmg_db()->prepare('SELECT 1 FROM pmg_aktualnosci WHERE slug = ?');
                for ($n = 2; $st->execute([$slug]) && $st->fetchColumn(); $n++) $slug = $base . '-' . $n;
                $st = pmg_db()->prepare('INSERT INTO pmg_aktualnosci (slug, data, kategoria, kolor, tytul, zajawka, tresc, zdjecie, zdjecie_alt, autor, opublikowany) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$slug, $f['data'], $f['kategoria'], $f['kolor'], $f['tytul'], $f['zajawka'], $f['tresc'], $f['zdjecie'], $f['zdjecie_alt'], $f['autor'], $f['opublikowany']]);
            }
            $_SESSION['flash'] = $f['opublikowany'] ? 'Zapisano i opublikowano.' : 'Zapisano jako szkic (niewidoczny na stronie).';
            go();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $edit = array_merge($old ?: [], $f, ['id' => $id]);
        }
    }
}

$logged = !empty($_SESSION['ok']);
if ($logged && !isset($edit)) {
    if (isset($_GET['nowy'])) {
        $edit = ['id' => 0, 'data' => date('Y-m-d'), 'kolor' => 'pink', 'opublikowany' => 0];
    } elseif (isset($_GET['id'])) {
        $st = pmg_db()->prepare('SELECT * FROM pmg_aktualnosci WHERE id = ?');
        $st->execute([(int) $_GET['id']]);
        $edit = $st->fetch() ?: null;
    }
}
$v = function ($k) use (&$edit) { return h($edit[$k] ?? ''); };
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Panel Aktualności — PMG</title>
<style>
  body { font: 16px/1.5 system-ui, sans-serif; color: #141414; background: #F7F6F3; margin: 0; padding: 24px 16px 60px; }
  main { max-width: 820px; margin: 0 auto; }
  h1 { font-size: 26px; margin: 0 0 20px; }
  a { color: #1d46e0; }
  .box { background: #fff; border: 1px solid #e3e1dc; border-radius: 14px; padding: 20px; margin-bottom: 18px; }
  label { display: block; font-weight: 600; margin: 14px 0 4px; }
  input[type=text], input[type=date], input[type=password], select, textarea { width: 100%; box-sizing: border-box; font: inherit; padding: 10px 12px; border: 1px solid #b9b6ae; border-radius: 8px; background: #fff; }
  textarea { min-height: 260px; }
  .hint { font-size: 14px; color: #555; margin: 4px 0 0; }
  button, .btn { font: inherit; font-weight: 600; padding: 10px 18px; border-radius: 999px; border: 0; background: #141414; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn--light { background: #e9e7e2; color: #141414; }
  .btn--danger { background: #b3123f; }
  .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 18px; }
  .msg { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
  .msg--err { background: #fde8ee; color: #8a0f33; }
  .msg--ok { background: #e7f5ea; color: #1b5e2b; }
  table { width: 100%; border-collapse: collapse; }
  td, th { text-align: left; padding: 10px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
  .draft { font-size: 13px; background: #eee; border-radius: 6px; padding: 2px 8px; }
  img.preview { max-width: 320px; width: 100%; border-radius: 8px; display: block; margin-top: 8px; }
  :focus-visible { outline: 3px solid #1d46e0; outline-offset: 2px; }
</style>
</head>
<body>
<main>
<h1>Panel Aktualności PMG</h1>
<?php if ($error): ?><p class="msg msg--err" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($flash): ?><p class="msg msg--ok" role="status"><?= h($flash) ?></p><?php endif; ?>

<?php if (!$logged): ?>
  <?php if ($cfg['panel_hash'] === ''): ?>
    <p class="msg msg--err">Panel nie ma jeszcze hasła. Otwórz <a href="ustaw-haslo.php">ustaw-haslo.php</a> i wklej wynik do <code>api/config.php</code>.</p>
  <?php else: ?>
    <form class="box" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="login">
      <label for="haslo">Hasło</label>
      <input type="password" id="haslo" name="haslo" autocomplete="current-password" required autofocus>
      <div class="row"><button type="submit">Zaloguj</button></div>
    </form>
  <?php endif; ?>

<?php elseif (isset($edit) && $edit !== null): ?>
  <form class="box" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <h2><?= $edit['id'] ? 'Edycja wpisu' : 'Nowy wpis' ?></h2>
    <label for="tytul">Tytuł</label>
    <input type="text" id="tytul" name="tytul" maxlength="200" value="<?= $v('tytul') ?>" required>
    <label for="data">Data wpisu</label>
    <input type="date" id="data" name="data" value="<?= $v('data') ?>" required>
    <label for="kategoria">Kategoria</label>
    <input type="text" id="kategoria" name="kategoria" maxlength="40" value="<?= $v('kategoria') ?>" placeholder="np. Życie koła, Wydarzenie, Rekrutacja" required>
    <label for="kolor">Kolor etykiety kategorii</label>
    <select id="kolor" name="kolor"><?php foreach (KOLORY as $k => $n): ?><option value="<?= $k ?>"<?= ($edit['kolor'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
    <label for="zajawka">Zajawka (1–2 zdania na liście wpisów)</label>
    <input type="text" id="zajawka" name="zajawka" maxlength="400" value="<?= $v('zajawka') ?>" required>
    <label for="tresc">Treść</label>
    <textarea id="tresc" name="tresc" required><?= $v('tresc') ?></textarea>
    <p class="hint">Akapity oddzielaj pustą linią. Śródtytuł: linia zaczynająca się od <code>## </code>. Bez HTML — znaczniki pokażą się jako zwykły tekst.</p>
    <label for="zdjecie">Zdjęcie 16:9 (JPG, PNG albo WebP)</label>
    <input type="file" id="zdjecie" name="zdjecie" accept="image/jpeg,image/png,image/webp">
    <?php if (!empty($edit['zdjecie'])): ?><img class="preview" src="../<?= h($edit['zdjecie']) ?>" alt=""><p class="hint">Wgranie nowego pliku zastąpi to zdjęcie.</p><?php endif; ?>
    <label for="zdjecie_alt">Opis zdjęcia (co na nim widać — dla osób niewidomych)</label>
    <input type="text" id="zdjecie_alt" name="zdjecie_alt" maxlength="200" value="<?= $v('zdjecie_alt') ?>">
    <label for="autor">Autor</label>
    <input type="text" id="autor" name="autor" maxlength="100" value="<?= $v('autor') ?>" placeholder="np. Sekcja Marketing">
    <label><input type="checkbox" name="opublikowany" value="1"<?= !empty($edit['opublikowany']) ? ' checked' : '' ?>> Opublikuj na stronie (bez zaznaczenia zostaje szkicem)</label>
    <div class="row"><button type="submit">Zapisz</button><a class="btn btn--light" href="index.php">Anuluj</a></div>
  </form>
  <?php if ($edit['id']): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <div class="row"><label style="margin:0;font-weight:400"><input type="checkbox" required> Tak, usuń ten wpis na stałe</label><button class="btn--danger" type="submit">Usuń wpis</button></div>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="row" style="margin: 0 0 18px"><a class="btn" href="?nowy">+ Nowy wpis</a><a class="btn btn--light" href="../aktualnosci.html" target="_blank" rel="noopener">Zobacz stronę</a>
    <form method="post" style="margin-left:auto"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="logout"><button class="btn--light" type="submit">Wyloguj</button></form></div>
  <div class="box">
    <table>
      <thead><tr><th>Data</th><th>Tytuł</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach (pmg_db()->query('SELECT id, data, tytul, opublikowany FROM pmg_aktualnosci ORDER BY data DESC, id DESC') as $r): ?>
        <tr><td><?= h($r['data']) ?></td><td><a href="?id=<?= (int) $r['id'] ?>"><?= h($r['tytul']) ?></a></td><td><?= $r['opublikowany'] ? 'Opublikowany' : '<span class="draft">Szkic</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</main>
</body>
</html>
