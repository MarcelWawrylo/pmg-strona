<?php
// Wspólne funkcje backendu (PHP 7.4). Tylko dołączany, nigdy wywoływany bezpośrednio.

// Błędy nie trafiają do odpowiedzi (mogłyby ujawnić np. hasło do bazy ze stack trace) — tylko do logu serwera.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function ($e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'Błąd serwera. Spróbuj za chwilę.';
});

function pmg_config()
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(503);
            exit('Brak api/config.php');
        }
        $cfg = require $file;
    }
    return $cfg;
}

function pmg_db()
{
    static $pdo = null;
    if ($pdo === null) {
        $c = pmg_config();
        $pdo = new PDO(
            'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}

// Biała lista kluczy pmg_ustawienia — używana przez panel (_admin.php) i api/ustawienia.php.
// pms_* (liczby "PM Session w liczbach") edytuje moduł pmsession w etapie 2d, ale klucze są tu od razu.
const USTAWIENIA = [
    'instagram', 'facebook', 'linkedin', 'tiktok', 'email',
    'rekrutacja_otwarta', 'rekrutacja_link', 'rekrutacja_tekst',
    'pms_edycji', 'pms_prelekcji', 'pms_prelegentow', 'pms_uczestnikow', 'pms_warsztatow', 'pms_symulacji',
];

// Tworzy brakujące tabele (IF NOT EXISTS, rodzic → dziecko). Wywoływana tylko z panelu, przy każdym żądaniu.
// Kolejne etapy dopiszą tu swoje tabele (pmg_sekcje, pmg_osoby, pmg_edycje, pmg_prelegenci, pmg_harmonogram)
// i ewentualne ALTER TABLE ... ADD COLUMN IF NOT EXISTS dla już istniejących.
function pmg_migrate()
{
    $pdo = pmg_db();
    $tabele = [
        // przeniesiona 1:1 z dawnego pmg_db() — dane zostają
        "CREATE TABLE IF NOT EXISTS pmg_aktualnosci (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            data DATE NOT NULL,
            kategoria VARCHAR(40) NOT NULL,
            kolor VARCHAR(10) NOT NULL DEFAULT 'pink',
            tytul VARCHAR(200) NOT NULL,
            zajawka VARCHAR(400) NOT NULL,
            tresc TEXT NOT NULL,
            zdjecie VARCHAR(200) NULL,
            zdjecie_alt VARCHAR(200) NOT NULL DEFAULT '',
            autor VARCHAR(100) NOT NULL DEFAULT '',
            opublikowany TINYINT(1) NOT NULL DEFAULT 0,
            zmieniono TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_uzytkownicy (
            id INT AUTO_INCREMENT PRIMARY KEY,
            imie_nazwisko VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            haslo VARCHAR(255) NULL,
            rola ENUM('admin','redaktor') NOT NULL DEFAULT 'redaktor',
            moduly SET('aktualnosci','czlonkowie','pmsession') NOT NULL DEFAULT '',
            aktywny TINYINT(1) NOT NULL DEFAULT 1,
            token_hash CHAR(64) NULL UNIQUE,
            token_do DATETIME NULL,
            ostatnie_logowanie DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_dziennik (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kiedy TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            uzytkownik_id INT NOT NULL,
            modul VARCHAR(20) NOT NULL,
            akcja VARCHAR(20) NOT NULL,
            rekord_id INT NULL,
            FOREIGN KEY (uzytkownik_id) REFERENCES pmg_uzytkownicy(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_ustawienia (
            klucz VARCHAR(40) PRIMARY KEY,
            wartosc VARCHAR(500) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_sekcje (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nazwa VARCHAR(60) NOT NULL,
            kolor ENUM('pink','purple','blue','violet') NOT NULL DEFAULT 'pink',
            opis VARCHAR(300) NOT NULL DEFAULT '',
            kolejnosc SMALLINT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_osoby (
            id INT AUTO_INCREMENT PRIMARY KEY,
            imie VARCHAR(50) NOT NULL,
            nazwisko VARCHAR(60) NOT NULL,
            funkcja VARCHAR(60) NOT NULL DEFAULT '',
            sekcja_id INT NULL,
            koordynator TINYINT(1) NOT NULL DEFAULT 0,
            email VARCHAR(150) NOT NULL,
            linkedin VARCHAR(200) NOT NULL DEFAULT '',
            zdjecie VARCHAR(200) NULL,
            zdjecie_alt VARCHAR(200) NOT NULL DEFAULT '',
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            aktywna TINYINT(1) NOT NULL DEFAULT 1,
            FOREIGN KEY (sekcja_id) REFERENCES pmg_sekcje(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_edycje (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numer VARCHAR(10) NOT NULL UNIQUE,
            temat VARCHAR(200) NOT NULL,
            data DATE NOT NULL,
            miejsce VARCHAR(200) NOT NULL,
            status ENUM('szkic','biezaca','zakonczona') NOT NULL DEFAULT 'szkic'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // notatka: odstępstwo od projektu (decyzja orkiestratora) — opcjonalna, np. "Wspólny warsztat z ...".
        "CREATE TABLE IF NOT EXISTS pmg_prelegenci (
            id INT AUTO_INCREMENT PRIMARY KEY,
            edycja_id INT NOT NULL,
            imie_nazwisko VARCHAR(100) NOT NULL,
            temat VARCHAR(300) NOT NULL,
            bio VARCHAR(1500) NOT NULL DEFAULT '',
            notatka VARCHAR(200) NOT NULL DEFAULT '',
            zdjecie VARCHAR(200) NULL,
            zdjecie_alt VARCHAR(200) NOT NULL DEFAULT '',
            linkedin VARCHAR(200) NOT NULL DEFAULT '',
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            FOREIGN KEY (edycja_id) REFERENCES pmg_edycje(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_harmonogram (
            id INT AUTO_INCREMENT PRIMARY KEY,
            edycja_id INT NOT NULL,
            godzina TIME NOT NULL,
            tytul VARCHAR(300) NOT NULL,
            prelegent VARCHAR(150) NOT NULL DEFAULT '',
            FOREIGN KEY (edycja_id) REFERENCES pmg_edycje(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($tabele as $sql) $pdo->exec($sql);
}

function pmg_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Prosty limit prób ($max zdarzeń w $window sekund), plik w katalogu tymczasowym.
// $key = klucz zdarzenia (np. id konta); domyślnie adres IP.
// $licz = false: tylko sprawdza, czy limit nie jest przekroczony, bez dopisywania nowej próby
// (do sprawdzenia limitu PRZED weryfikacją hasła — udane logowanie nie ma zużywać limitu konta).
// Cały odczyt+decyzja+zapis w jednej sekcji krytycznej (flock) — odporne na dwa równoczesne żądania.
function pmg_rate_ok($bucket, $max, $window, $key = null, $licz = true)
{
    $file = sys_get_temp_dir() . '/pmg_' . $bucket . '_' . md5($key !== null ? $key : ($_SERVER['REMOTE_ADDR'] ?? ''));
    $fh = fopen($file, 'c+');
    if ($fh === false) return true; // ponytail: fail-open, gdy katalog tymczasowy niedostępny — limit nie jest jedyną obroną
    flock($fh, LOCK_EX);
    $now = time();
    $hits = array_filter(explode(',', (string) stream_get_contents($fh)), function ($t) use ($now, $window) {
        return (int) $t > $now - $window;
    });
    $ok = count($hits) < $max;
    if ($ok && $licz) {
        $hits[] = $now;
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, implode(',', $hits));
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}
