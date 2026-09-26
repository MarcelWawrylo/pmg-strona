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
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self'; form-action 'self'; frame-ancestors 'none'");
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

// ---------- Funkcje widoku (nie zmieniają logiki/danych, tylko wygląd) ----------

// Inicjały (do awatara) z pierwszych liter dwóch pierwszych słów imienia i nazwiska.
function pmg_inicjaly($imie_nazwisko)
{
    $slowa = array_filter(preg_split('/\s+/', trim((string) $imie_nazwisko)), function ($s) { return $s !== ''; });
    $ini = '';
    foreach (array_slice($slowa, 0, 2) as $slowo) $ini .= mb_substr($slowo, 0, 1);
    return mb_strtoupper($ini);
}

// Odmiana rzeczownika po liczbie: 1 wpis / 2-4 wpisy / 5+ wpisów (i nastolatki: 12-14 wpisów).
function pmg_odmiana($n, $jeden, $kilka, $wiele)
{
    $n = (int) $n; $r10 = $n % 10; $r100 = $n % 100;
    if ($n === 1) return '1 ' . $jeden;
    return $n . ' ' . (($r10 >= 2 && $r10 <= 4 && ($r100 < 12 || $r100 > 14)) ? $kilka : $wiele);
}

// Ikony inline SVG (viewBox 20x20, stroke 1,5) — bez bibliotek, bez CDN.
function pmg_ikona($nazwa)
{
    static $d = [
        'start' => 'M3.5 9 10 3.5 16.5 9v7a1 1 0 0 1-1 1h-3.25v-5h-4.5v5H4.5a1 1 0 0 1-1-1Z',
        'aktualnosci' => 'M3.5 4.5h10v11a1.5 1.5 0 0 0 1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5Z M13.5 8h3v7.5A1.5 1.5 0 0 1 15 17 M6.5 7.5h4 M6.5 10.5h4 M6.5 13.5h2.5',
        'czlonkowie' => 'M7.5 9.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M2.5 16.5a5 5 0 0 1 10 0 M13 3.75a2.75 2.75 0 0 1 0 5.5 M14.75 11.75a5 5 0 0 1 2.75 4.75',
        'pmsession' => 'M3.5 5.5A1.5 1.5 0 0 1 5 4h10a1.5 1.5 0 0 1 1.5 1.5v10A1.5 1.5 0 0 1 15 17H5a1.5 1.5 0 0 1-1.5-1.5Z M3.5 8.5h13 M7 2.5v3 M13 2.5v3 M7 12h2.5',
        'konta' => 'M2.75 5.25a1.5 1.5 0 0 1 1.5-1.5h11.5a1.5 1.5 0 0 1 1.5 1.5v9.5a1.5 1.5 0 0 1-1.5 1.5H4.25a1.5 1.5 0 0 1-1.5-1.5Z M7.5 10a1.75 1.75 0 1 0 0-3.5 1.75 1.75 0 0 0 0 3.5Z M4.75 13.5a2.9 2.9 0 0 1 5.5 0 M12.25 8h3 M12.25 11h3',
        'dziennik' => 'M3.5 10A6.5 6.5 0 1 0 5.4 5.4 M5.4 2.75V5.4H2.75 M10 6.5V10l2.5 1.75',
        'ustawienia' => 'M3.5 6.5h6 M13.5 6.5h3 M3.5 13.5h2 M9.5 13.5h7 M13.5 6.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z M9.5 13.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
        'kopia' => 'M16.5 5c0 1.1-2.9 2-6.5 2S3.5 6.1 3.5 5s2.9-2 6.5-2 6.5.9 6.5 2Z M3.5 5v10c0 1.1 2.9 2 6.5 2 M16.5 5v4.5 M3.5 10c0 1.1 2.9 2 6.5 2 M14.5 12v5 M12.25 14.75 14.5 17l2.25-2.25',
        'wyloguj' => 'M8 3.5H5A1.5 1.5 0 0 0 3.5 5v10A1.5 1.5 0 0 0 5 16.5h3 M12.5 13.5 16 10l-3.5-3.5 M16 10H8',
        'menu' => 'M3.5 6h13 M3.5 10h13 M3.5 14h13',
        'zamknij' => 'M5 5l10 10 M15 5 5 15',
        'wstecz' => 'M12 5l-5 5 5 5',
        'chevron' => 'M6 8l4 4 4-4',
        'zewnetrzny' => 'M8.5 4.5H5A1.5 1.5 0 0 0 3.5 6v9A1.5 1.5 0 0 0 5 16.5h9a1.5 1.5 0 0 0 1.5-1.5v-3.5 M11.5 3.5h5v5 M16.5 3.5l-7 7',
        'blad' => 'M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M10 6.5v4.25 M10 13.5v.01',
        'sukces' => 'M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M7 10.25l2 2 4-4.25',
        'info' => 'M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M10 9.25v4.25 M10 6.5v.01',
        'kopiuj' => 'M7.5 7.5h8a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-8a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z M13.5 4.5v-.5a1 1 0 0 0-1-1h-8a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h.5',
        'pobierz' => 'M10 3.5v9 M6.25 9 10 12.75 13.75 9 M4 16.5h12',
    ];
    return '<svg class="pmg-icon" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><path d="' . $d[$nazwa] . '"/></svg>';
}

// Nawigacja modułów (echo; wywoływana dwa razy — sidebar i menu mobilne — jedna kopia jest zawsze ukryta CSS-em).
function pmg_nawigacja($m, $etykiety)
{
    $grupy = ['Treści' => ['aktualnosci', 'czlonkowie', 'pmsession'], 'Administracja' => ['konta', 'dziennik', 'ustawienia', 'kopia']];
    echo '<ul class="pmg-nav__list"><li><a class="pmg-nav__item" href="index.php"' . ($m === '' ? ' aria-current="page"' : '') . '>' . pmg_ikona('start') . '<span>Start</span></a></li></ul>';
    foreach ($grupy as $nazwa => $klucze) {
        $widoczne = array_filter($klucze, function ($mk) { return wolno(MODULY[$mk]); });
        if (!$widoczne) continue;
        echo '<p class="pmg-nav__group">' . h($nazwa) . '</p><ul class="pmg-nav__list">';
        foreach ($widoczne as $mk) {
            if ($mk === 'kopia') {
                echo '<li><a class="pmg-nav__item pmg-nav__item--download" href="?m=kopia">' . pmg_ikona('kopia') . '<span>Kopia bazy danych<span class="pmg-nav__sub"><span class="pmg-vh"> — </span>pobiera plik .sql</span></span></a></li>';
            } else {
                echo '<li><a class="pmg-nav__item" href="?m=' . $mk . '"' . ($mk === $m ? ' aria-current="page"' : '') . '>' . pmg_ikona($mk) . '<span>' . h($etykiety[$mk]) . '</span></a></li>';
            }
        }
        echo '</ul>';
    }
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
    if ($w * $hgt > 25000000) throw new RuntimeException('Zdjęcie ma za dużą rozdzielczość (maks. ok. 25 megapikseli) — zmniejsz je przed wgraniem.');
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

// ---------- Etykiety i opisy modułów (nad routerem — moduły ich potrzebują) ----------
$etykietyModulow = [
    'aktualnosci' => 'Aktualności', 'czlonkowie' => 'Członkowie', 'pmsession' => 'PM Session',
    'konta' => 'Konta', 'dziennik' => 'Dziennik zmian', 'ustawienia' => 'Ustawienia strony', 'kopia' => 'Kopia bazy danych',
];
$opisyModulow = [
    'aktualnosci' => 'Wpisy na stronie Aktualności i w kafelkach na stronie głównej.',
    'czlonkowie' => 'Zarząd i sekcje koła pokazywane na stronie O nas.',
    'pmsession' => 'Edycje konferencji, prelegenci, harmonogram i liczby na stronie PM Session.',
    'konta' => 'Kto ma dostęp do panelu i do których modułów.',
    'dziennik' => 'Ostatnie 200 zapisanych zmian.',
    'ustawienia' => 'Linki do mediów społecznościowych, e-mail kontaktowy i rekrutacja na stronie. Zmiany widać w ciągu 5 minut.',
    'kopia' => 'Pobiera plik .sql z pełną kopią bazy danych.',
];
$pmgNaglowek = []; // moduły mogą nadpisać: tytul, opis, wstecz[href,etykieta], akcje[[href,etykieta,rodzaj,plus?,nowaKarta?]], chip[tekst,wariant]

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
            if (!pmg_rate_ok('login_konto', 5, 900, $klucz)) { // liczone od razu — równoległe żądania nie obejdą limitu
                $error = 'Za dużo prób logowania. Spróbuj za 15 minut.';
            } else {
                $ma_haslo = $u && $u['haslo'] !== null;
                $ok = password_verify($haslo, $ma_haslo ? $u['haslo'] : DUMMY_HASH) && $ma_haslo;
                if ($ok) {
                    session_regenerate_id(true);
                    $_SESSION['uid'] = (int) $u['id'];
                    $_SESSION['t'] = time();
                    $_SESSION['ph'] = hash('sha256', (string) $u['haslo']);
                    pmg_rate_clear('login_konto', $klucz);
                    pmg_db()->prepare('UPDATE pmg_uzytkownicy SET ostatnie_logowanie = NOW() WHERE id = ?')->execute([$u['id']]);
                    go();
                } else {
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
        $pmgNaglowek = ['tytul' => 'Nie ma takiego modułu'];
        $tresc = '<div class="pmg-alert pmg-alert--error" role="alert">' . pmg_ikona('blad') . '<p>Nieznany moduł.</p></div>'
            . '<p class="pmg-after-alert"><a class="pmg-btn pmg-btn--secondary" href="index.php">Wróć do strony startowej</a></p>';
    } elseif (!wolno(MODULY[$m])) {
        http_response_code(403);
        $pmgNaglowek = ['tytul' => 'Brak dostępu'];
        $tresc = '<div class="pmg-alert pmg-alert--error" role="alert">' . pmg_ikona('blad') . '<p>Nie masz dostępu do tego modułu. Jeśli to pomyłka, poproś administratora.</p></div>'
            . '<p class="pmg-after-alert"><a class="pmg-btn pmg-btn--secondary" href="index.php">Wróć do strony startowej</a></p>';
    } else {
        $plik = __DIR__ . '/_' . (MODULY[$m] === 'admin' ? 'admin' : $m) . '.php';
        if (!is_file($plik)) {
            $tresc = '<div class="pmg-alert pmg-alert--info">' . pmg_ikona('info') . '<p>Moduł w przygotowaniu.</p></div>';
        } else {
            ob_start();
            require $plik;
            $tresc = ob_get_clean();
        }
    }
}

// ---------- Dane do szablonu (nagłówek strony, tytuł, alerty, liczby na start) ----------
$etykieta = $me ? ($etykietyModulow[$m] ?? '') : '';
$ng = $pmgNaglowek + ['tytul' => $etykieta !== '' ? $etykieta : 'Start', 'opis' => $opisyModulow[$m] ?? '', 'akcje' => [], 'wstecz' => null, 'chip' => null];
if (!$me) $ng = ['tytul' => ['pierwsze' => 'Pierwsze konto administratora', 'haslo' => 'Ustaw hasło'][$widok] ?? 'Zaloguj się', 'opis' => '', 'akcje' => [], 'wstecz' => null, 'chip' => null];
if ($me && $m === '') $ng['opis'] = 'Wybierz moduł, żeby dodać lub zmienić treść na stronie.';
$tytulBledu = ['pierwsze' => 'Nie udało się założyć konta', 'haslo' => 'Nie udało się ustawić hasła', 'logowanie' => 'Nie udało się zalogować'][$widok] ?? 'Nie udało się zapisać';

// Alerty (§5.10). Uwaga: rola "alert" jest na samym tekście komunikatu (nie na tytule), żeby czytnik
// ekranu i automatyczne testy dostawały dokładnie treść $error — tytuł ($tytulBledu) zostaje w nagłówku
// tej samej karty, czytelny przy przejściu fokusu (tabindex/data-pmg-fokus na zewnętrznym kontenerze).
ob_start();
if ($error) {
    echo '<div class="pmg-alert pmg-alert--error" tabindex="-1" data-pmg-fokus>' . pmg_ikona('blad')
        . '<div><h2 class="pmg-alert__title">' . h($tytulBledu) . '</h2><p role="alert">' . h($error) . '</p></div></div>';
}
if ($flash) {
    echo '<div class="pmg-alert pmg-alert--success" role="status">' . pmg_ikona('sukces') . '<p>' . h($flash) . '</p></div>';
}
if ($wygasla) {
    echo '<div class="pmg-alert pmg-alert--error" role="alert">' . pmg_ikona('blad') . '<p>Sesja wygasła z powodu bezczynności — zaloguj się ponownie.</p></div>';
}
$pmgAlerty = ob_get_clean();

// Liczby na kafelkach strony startowej (tylko odczyt; tylko moduły, do których $me ma dostęp).
$pmgLiczby = [];
if ($me && $m === '') { $db = pmg_db(); foreach (array_keys($etykietyModulow) as $mk) { if (!wolno(MODULY[$mk])) continue;
  if ($mk === 'aktualnosci') { $a = $db->query('SELECT COUNT(*) FROM pmg_aktualnosci')->fetchColumn(); $b = (int) $db->query('SELECT COUNT(*) FROM pmg_aktualnosci WHERE opublikowany = 0')->fetchColumn();
      $pmgLiczby[$mk] = pmg_odmiana($a, 'wpis', 'wpisy', 'wpisów') . ($b ? ' · ' . pmg_odmiana($b, 'szkic', 'szkice', 'szkiców') : ''); }
  if ($mk === 'czlonkowie') { $pmgLiczby[$mk] = pmg_odmiana($db->query('SELECT COUNT(*) FROM pmg_osoby')->fetchColumn(), 'osoba', 'osoby', 'osób') . ' · ' . pmg_odmiana($db->query('SELECT COUNT(*) FROM pmg_sekcje')->fetchColumn(), 'sekcja', 'sekcje', 'sekcji'); }
  if ($mk === 'pmsession') { $a = $db->query('SELECT COUNT(*) FROM pmg_edycje')->fetchColumn(); $b = (int) $db->query("SELECT COUNT(*) FROM pmg_edycje WHERE status = 'biezaca'")->fetchColumn();
      $pmgLiczby[$mk] = pmg_odmiana($a, 'edycja', 'edycje', 'edycji') . ($b ? '' : ' · brak bieżącej edycji'); }
  if ($mk === 'konta') { $a = $db->query('SELECT COUNT(*) FROM pmg_uzytkownicy')->fetchColumn(); $b = (int) $db->query('SELECT COUNT(*) FROM pmg_uzytkownicy WHERE haslo IS NULL')->fetchColumn();
      $pmgLiczby[$mk] = pmg_odmiana($a, 'konto', 'konta', 'kont') . ($b ? ' · ' . $b . ' bez hasła' : ''); }
  if ($mk === 'dziennik') { $pmgLiczby[$mk] = pmg_odmiana($db->query('SELECT COUNT(*) FROM pmg_dziennik')->fetchColumn(), 'zmiana', 'zmiany', 'zmian') . ' w dzienniku'; }
} }
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= h(($error !== '' ? 'Błąd: ' : '') . $ng['tytul'] . ($etykieta !== '' && $ng['tytul'] !== $etykieta ? ' · ' . $etykieta : '') . ' — Panel PMG') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&amp;family=Manrope:wght@400;500;600;700&amp;display=swap">
<link rel="stylesheet" href="panel.css">
<script src="panel.js" defer></script>
</head>
<?php if ($me): ?>
<body class="pmg">
<a class="pmg-skip" href="#pmg-tresc">Pomiń do treści</a>
<header class="pmg-topbar">
  <a class="pmg-brand" href="index.php"><img src="../img/logo-90.png" width="32" height="32" alt=""><span>Panel PMG</span></a>
  <details class="pmg-menu">
    <summary class="pmg-menu__summary"><span class="pmg-icon--menu"><?= pmg_ikona('menu') ?></span><span class="pmg-icon--zamknij"><?= pmg_ikona('zamknij') ?></span> Menu</summary>
    <div class="pmg-menu__panel">
      <nav aria-label="Moduły panelu"><?php pmg_nawigacja($m, $etykietyModulow) ?></nav>
      <div class="pmg-menu__foot">
        <div class="pmg-user">
          <span class="pmg-user__avatar" aria-hidden="true"><?= h(pmg_inicjaly($me['imie_nazwisko'])) ?></span>
          <span class="pmg-user__text"><span class="pmg-user__name"><?= h($me['imie_nazwisko']) ?></span><span class="pmg-user__role"><?= $me['rola'] === 'admin' ? 'Administrator' : 'Redaktor' ?></span></span>
        </div>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="logout"><button class="pmg-nav__item pmg-nav__item--button" type="submit"><?= pmg_ikona('wyloguj') ?><span>Wyloguj</span></button></form>
      </div>
    </div>
  </details>
</header>
<div class="pmg-shell">
  <div class="pmg-sidebar">
    <a class="pmg-brand" href="index.php"><img src="../img/logo-90.png" width="32" height="32" alt=""><span>Panel PMG</span></a>
    <nav class="pmg-sidebar__nav" aria-label="Moduły panelu"><?php pmg_nawigacja($m, $etykietyModulow) ?></nav>
    <div class="pmg-sidebar__foot">
      <div class="pmg-user">
        <span class="pmg-user__avatar" aria-hidden="true"><?= h(pmg_inicjaly($me['imie_nazwisko'])) ?></span>
        <span class="pmg-user__text"><span class="pmg-user__name"><?= h($me['imie_nazwisko']) ?></span><span class="pmg-user__role"><?= $me['rola'] === 'admin' ? 'Administrator' : 'Redaktor' ?></span></span>
      </div>
      <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="logout"><button class="pmg-nav__item pmg-nav__item--button" type="submit"><?= pmg_ikona('wyloguj') ?><span>Wyloguj</span></button></form>
    </div>
  </div>
  <main id="pmg-tresc" class="pmg-main" tabindex="-1"><div class="pmg-container">
    <p class="pmg-vh">Zalogowano: <?= h($me['imie_nazwisko']) ?></p>
    <header class="pmg-page-head">
      <?php if ($ng['wstecz']): ?><a class="pmg-back" href="<?= h($ng['wstecz']['href']) ?>"><?= pmg_ikona('wstecz') ?><?= h($ng['wstecz']['etykieta']) ?></a><?php endif; ?>
      <div class="pmg-page-head__row">
        <div class="pmg-page-head__text">
          <div class="pmg-page-head__title"><h1 class="pmg-h1"><?= h($ng['tytul']) ?></h1><?php if ($ng['chip']): ?><span class="pmg-chip pmg-chip--<?= h($ng['chip']['wariant']) ?>"><?= h($ng['chip']['tekst']) ?></span><?php endif; ?></div>
          <?php if ($ng['opis'] !== ''): ?><p class="pmg-page-head__desc"><?= h($ng['opis']) ?></p><?php endif; ?>
        </div>
        <?php if ($ng['akcje']): ?><div class="pmg-page-head__actions"><?php foreach ($ng['akcje'] as $ak): ?><a class="pmg-btn pmg-btn--<?= h($ak['rodzaj']) ?>" href="<?= h($ak['href']) ?>"<?= !empty($ak['nowaKarta']) ? ' target="_blank" rel="noopener"' : '' ?>><?= !empty($ak['plus']) ? '<span class="pmg-plus" aria-hidden="true">+</span> ' : '' ?><?= h($ak['etykieta']) ?><?= !empty($ak['nowaKarta']) ? pmg_ikona('zewnetrzny') . '<span class="pmg-vh"> (otwiera nową kartę)</span>' : '' ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
    </header>
    <?= $pmgAlerty ?>
    <?php if ($m === ''): ?>
      <ul class="pmg-tiles">
        <?php foreach ($etykietyModulow as $mk => $ml): if (!wolno(MODULY[$mk])) continue; ?>
          <?php if ($mk === 'kopia'): ?>
            <li><a class="pmg-tile pmg-tile--download" href="?m=kopia">
              <span class="pmg-tile__icon"><?= pmg_ikona('kopia') ?></span>
              <span class="pmg-tile__body">
                <span class="pmg-tile__title"><?= h($ml) ?></span>
                <span class="pmg-tile__desc"><?= h($opisyModulow[$mk] ?? '') ?></span>
                <span class="pmg-tile__meta">Pobiera plik .sql</span>
              </span>
              <span class="pmg-tile__chevron"><?= pmg_ikona('pobierz') ?></span>
            </a></li>
          <?php else: ?>
            <li><a class="pmg-tile" href="?m=<?= $mk ?>">
              <span class="pmg-tile__icon"><?= pmg_ikona($mk) ?></span>
              <span class="pmg-tile__body">
                <span class="pmg-tile__title"><?= h($ml) ?></span>
                <span class="pmg-tile__desc"><?= h($opisyModulow[$mk] ?? '') ?></span>
                <?php if (isset($pmgLiczby[$mk])): ?><span class="pmg-tile__meta"><?= h($pmgLiczby[$mk]) ?></span><?php endif; ?>
              </span>
              <span class="pmg-tile__chevron"><?= pmg_ikona('chevron') ?></span>
            </a></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?= $tresc ?>
    <?php endif; ?>
  </div></main>
</div>
</body>
<?php else: ?>
<body class="pmg pmg--auth">
<a class="pmg-skip" href="#pmg-tresc">Pomiń do treści</a>
<main id="pmg-tresc" class="pmg-auth" tabindex="-1">
  <span class="pmg-brand"><img src="../img/logo-90.png" width="40" height="40" alt=""><span>Panel PMG</span></span>

  <?php if ($widok === 'pierwsze'): $cfg = pmg_config(); ?>
    <div class="pmg-card pmg-card--auth">
      <h1 class="pmg-h1"><?= h($ng['tytul']) ?></h1>
      <?php if (!pierwsze_ok($cfg)): ?>
        <?= $pmgAlerty ?>
        <p>Aby założyć pierwsze konto, wpisz w <code>api/config.php</code> hasło instalacyjne <code>'setup_haslo'</code> (min. 12 znaków) — patrz README.</p>
      <?php else: ?>
        <p>Tabela kont jest pusta — to jednorazowy ekran. Załóż konto administratora, żeby dalej zarządzać panelem.</p>
        <?= $pmgAlerty ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="pierwsze">
          <?php if (($cfg['panel_hash'] ?? '') !== ''): ?>
            <label for="stare_haslo">Dotychczasowe hasło panelu Aktualności</label>
            <p class="pmg-hint" id="stare_haslo_h">Ta instalacja miała wcześniej wspólne hasło do panelu — potwierdź je, żeby przejąć dostęp.</p>
            <input type="password" id="stare_haslo" name="stare_haslo" autocomplete="current-password" required aria-describedby="stare_haslo_h">
          <?php else: ?>
            <label for="setup_haslo">Hasło instalacyjne (z pliku api/config.php)</label>
            <p class="pmg-hint" id="setup_haslo_h">Jednorazowe hasło ustawione w <code>api/config.php</code> jako <code>setup_haslo</code>.</p>
            <input type="password" id="setup_haslo" name="setup_haslo" autocomplete="off" required aria-describedby="setup_haslo_h">
          <?php endif; ?>
          <label for="imie_nazwisko">Imię i nazwisko</label>
          <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" required>
          <label for="email">E-mail (login)</label>
          <input type="email" id="email" name="email" maxlength="150" required>
          <label for="haslo">Hasło (min. 12 znaków)</label>
          <p class="pmg-hint" id="haslo_h">Użyj hasła, którego nie używasz nigdzie indziej.</p>
          <input type="password" id="haslo" name="haslo" minlength="12" required autocomplete="new-password" aria-describedby="haslo_h">
          <button class="pmg-btn pmg-btn--primary" type="submit">Załóż konto</button>
        </form>
      <?php endif; ?>
    </div>

  <?php elseif ($widok === 'haslo'): ?>
    <div class="pmg-card pmg-card--auth">
      <h1 class="pmg-h1"><?= h($ng['tytul']) ?></h1>
      <?= $pmgAlerty ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="haslo">
        <input type="hidden" name="t" value="<?= h($_GET['t'] ?? '') ?>">
        <label for="haslo">Nowe hasło (min. 12 znaków)</label>
        <input type="password" id="haslo" name="haslo" minlength="12" required autocomplete="new-password"<?= $error === '' ? ' autofocus' : '' ?>>
        <button class="pmg-btn pmg-btn--primary" type="submit">Ustaw hasło</button>
      </form>
      <p class="pmg-auth-foot">Masz już hasło? <a href="index.php">Przejdź do logowania</a></p>
    </div>

  <?php else: ?>
    <div class="pmg-card pmg-card--auth">
      <h1 class="pmg-h1"><?= h($ng['tytul']) ?></h1>
      <p>Panel do edycji treści strony PMG.</p>
      <?= $pmgAlerty ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="login">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" autocomplete="username" required<?= $error === '' ? ' autofocus' : '' ?>>
        <label for="haslo">Hasło</label>
        <input type="password" id="haslo" name="haslo" autocomplete="current-password" required>
        <button class="pmg-btn pmg-btn--primary" type="submit">Zaloguj</button>
      </form>
      <p class="pmg-auth-foot">Nie masz hasła? Poproś administratora o link.</p>
    </div>
  <?php endif; ?>
</main>
</body>
<?php endif; ?>
</html>
