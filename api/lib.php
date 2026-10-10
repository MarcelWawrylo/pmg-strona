<?php
// Wspólne funkcje backendu (PHP 7.4). Tylko dołączany, nigdy wywoływany bezpośrednio.

// Błędy nie trafiają do odpowiedzi (mogłyby ujawnić np. hasło do bazy ze stack trace) — tylko do logu serwera.
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1'); // stack trace w logu bez argumentów (np. hasła z new PDO)
ini_set('log_errors', '1');
date_default_timezone_set('Europe/Warsaw'); // spójnie z NOW() w bazie i z nazwami plików
set_exception_handler(function ($e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'Błąd serwera. Spróbuj za chwilę.';
});

// Lista miejsc, w których szukamy pliku konfiguracji (pierwszy istniejący wygrywa):
//  1. zmienna środowiskowa PMG_CONFIG (np. SetEnv w konfiguracji Apache / panelu hostingu) — pełna ścieżka do pliku,
//  2. pmg-config.php w katalogu NAD katalogiem głównym domeny (np. ~/pmg-config.php, gdy strona jest w ~/public_html),
//  3. pmg-config.php nad katalogiem site/ (gdy strona leży w podkatalogu domeny),
//  4. dotychczasowy api/config.php (leży w webroot, chroniony tylko przez api/.htaccess — rozwiązanie awaryjne).
// Pliki 2–3 są poza webroot, więc nie da się ich pobrać z przeglądarki nawet przy błędzie .htaccess.
// Katalog nad webrootem to zwykle katalog domowy konta, który mieści się w open_basedir; ścieżki spoza
// open_basedir są po cichu pomijane (@), a nie kończą się błędem.
function pmg_config_kandydaci()
{
    $k = [];
    $env = getenv('PMG_CONFIG');
    if (($env === false || $env === '') && !empty($_SERVER['PMG_CONFIG'])) $env = $_SERVER['PMG_CONFIG']; // SetEnv z Apache
    if (is_string($env) && $env !== '') $k[] = $env;
    if (!empty($_SERVER['DOCUMENT_ROOT'])) $k[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')) . '/pmg-config.php';
    $k[] = dirname(__DIR__, 2) . '/pmg-config.php';
    $k[] = __DIR__ . '/config.php';
    return array_values(array_unique($k));
}

function pmg_config()
{
    static $cfg = null;
    if ($cfg === null) {
        foreach (pmg_config_kandydaci() as $file) {
            if (@is_file($file) && @is_readable($file)) {
                $c = require $file;
                if (is_array($c)) { $cfg = $c; break; }
            }
        }
        if ($cfg === null) {
            http_response_code(503);
            exit('Brak pliku konfiguracji (pmg-config.php poza katalogiem strony albo api/config.php).');
        }
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

// Wersja schematu zapisana w pmg_ustawienia (klucz 'schema'). Zwiększ ją przy każdej zmianie w pmg_migrate() —
// migracja uruchomi się wtedy raz, a nie przy każdym żądaniu do panelu.
const PMG_SCHEMA = 12;

// Tworzy brakujące tabele (IF NOT EXISTS, rodzic → dziecko) i dokłada kolumny dodane później.
// Wywoływana tylko z panelu; gdy wersja schematu w bazie jest aktualna, kończy się jednym szybkim SELECT-em.
// Idempotentna: można ją bezpiecznie uruchomić ponownie (np. dwa równoczesne pierwsze żądania).
function pmg_migrate()
{
    $pdo = pmg_db();
    $v = false; // wersja schematu przed migracją (false = świeża baza)
    try {
        $v = $pdo->query("SELECT wartosc FROM pmg_ustawienia WHERE klucz = 'schema'")->fetchColumn();
        if ($v !== false && (int) $v >= PMG_SCHEMA) return;
    } catch (PDOException $e) {
        // brak tabeli pmg_ustawienia = świeża baza, migrujemy
    }
    $podcastBylo = $pdo->query("SHOW TABLES LIKE 'pmg\\_odcinki'")->fetchColumn() !== false;
    $podcastEdycjeBylo = $pdo->query("SHOW TABLES LIKE 'pmg\\_podcast\\_edycje'")->fetchColumn() !== false;
    $tabele = [
        // przeniesiona 1:1 z dawnego pmg_db() — dane zostają
        "CREATE TABLE IF NOT EXISTS pmg_aktualnosci (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            data DATE NOT NULL,
            kategoria VARCHAR(40) NOT NULL DEFAULT '',
            kolor VARCHAR(10) NOT NULL DEFAULT 'pink',
            tytul VARCHAR(200) NOT NULL,
            lead VARCHAR(600) NOT NULL DEFAULT '',
            zajawka VARCHAR(400) NOT NULL DEFAULT '',
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
            moduly SET('aktualnosci','czlonkowie','pmsession','podcast','case','tresci','rekrutacja','glowna','dolacz','kontakt') NOT NULL DEFAULT '',
            aktywny TINYINT(1) NOT NULL DEFAULT 1,
            token_hash CHAR(64) NULL UNIQUE,
            token_do DATETIME NULL,
            ostatnie_logowanie DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS pmg_dziennik (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kiedy TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            uzytkownik_id INT NULL,
            modul VARCHAR(20) NOT NULL,
            akcja VARCHAR(20) NOT NULL,
            rekord_id INT NULL,
            opis VARCHAR(200) NOT NULL DEFAULT '',
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
            opis VARCHAR(600) NOT NULL DEFAULT '',
            status ENUM('szkic','biezaca','zakonczona') NOT NULL DEFAULT 'szkic'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // notatka: odstępstwo od projektu (decyzja orkiestratora) — opcjonalna, np. "Wspólny warsztat z ...".
        "CREATE TABLE IF NOT EXISTS pmg_prelegenci (
            id INT AUTO_INCREMENT PRIMARY KEY,
            edycja_id INT NOT NULL,
            imie_nazwisko VARCHAR(100) NOT NULL,
            temat VARCHAR(300) NOT NULL,
            bio VARCHAR(2500) NOT NULL DEFAULT '',
            opis VARCHAR(4000) NOT NULL DEFAULT '',
            plec CHAR(1) NOT NULL DEFAULT 'm',
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
            znacznik VARCHAR(40) NOT NULL DEFAULT '',
            FOREIGN KEY (edycja_id) REFERENCES pmg_edycje(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    // Podcast: odcinki. spotify_id = samo 22-znakowe ID odcinka — adres odtwarzacza składa strona (main.js), nie panel.
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_odcinki (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numer SMALLINT NOT NULL UNIQUE,
            tytul VARCHAR(200) NOT NULL,
            data DATE NOT NULL,
            czas_min SMALLINT NULL,
            opis TEXT NOT NULL,
            prowadzacy VARCHAR(200) NOT NULL DEFAULT '',
            gosc VARCHAR(200) NOT NULL DEFAULT '',
            gosc_bio VARCHAR(1000) NOT NULL DEFAULT '',
            spotify_id VARCHAR(22) NOT NULL DEFAULT '',
            apple_url VARCHAR(400) NOT NULL DEFAULT '',
            youtube_url VARCHAR(300) NOT NULL DEFAULT '',
            zdjecie VARCHAR(200) NULL,
            zdjecie_alt VARCHAR(200) NOT NULL DEFAULT '',
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            opublikowany TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    // Schemat 4: edycje podcastu (zespół edycji + grupowanie odcinków na stronie). Nazwa z przedrostkiem podcast_,
    // bo pmg_edycje to edycje PM Session. mentorzy i zespol to zwykły tekst (imiona po przecinku), tak jak na stronie.
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_podcast_edycje (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numer SMALLINT NOT NULL UNIQUE,
            lata VARCHAR(20) NOT NULL DEFAULT '',
            koordynator VARCHAR(200) NOT NULL DEFAULT '',
            mentorzy VARCHAR(400) NOT NULL DEFAULT '',
            zespol VARCHAR(800) NOT NULL DEFAULT '',
            opis VARCHAR(600) NOT NULL DEFAULT '',
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            widoczna TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    // Schemat 6: Case Koła. Jedna edycja = jedna karta w hubie i jedna podstrona (case-kola-edycja.html?nr=N albo istniejący
    // plik z adres_strony). Teksty sekcji to zwykły tekst z akapitami oddzielonymi pustą linią. Zdjęcia: ścieżki względem katalogu
    // strony (img/… z repozytorium albo uploads/case/… z panelu). pelne = osobny plik do powiększenia w galerii (NULL = ten sam).
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_case_edycje (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numer SMALLINT NOT NULL UNIQUE,
            nazwa VARCHAR(80) NOT NULL,
            tytul_karty VARCHAR(80) NOT NULL DEFAULT '',
            naglowek VARCHAR(120) NOT NULL DEFAULT '',
            adres_strony VARCHAR(100) NOT NULL DEFAULT '',
            opis_meta VARCHAR(300) NOT NULL DEFAULT '',
            logo VARCHAR(200) NULL,
            logo_styl VARCHAR(20) NOT NULL DEFAULT 'ciemne',
            hero VARCHAR(200) NULL,
            hero_alt VARCHAR(200) NOT NULL DEFAULT '',
            o_partnerze TEXT NOT NULL,
            wyzwanie TEXT NOT NULL,
            co_zrobilismy TEXT NOT NULL,
            rezultat TEXT NOT NULL,
            w_toku VARCHAR(300) NOT NULL DEFAULT '',
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            widoczna TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_case_galeria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            edycja_id INT NOT NULL,
            zdjecie VARCHAR(200) NOT NULL,
            pelne VARCHAR(200) NULL,
            podpis VARCHAR(200) NOT NULL,
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            FOREIGN KEY (edycja_id) REFERENCES pmg_case_edycje(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    // Schemat 7: teksty stron nadpisane w panelu (moduł „Treści stron”). Klucz = wartość data-tresc z HTML; brak wiersza = tekst z HTML.
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_tresci (
            klucz VARCHAR(80) NOT NULL PRIMARY KEY,
            wartosc TEXT NOT NULL,
            data_zmiany DATETIME NOT NULL,
            kto VARCHAR(100) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    foreach ($tabele as $sql) $pdo->exec($sql);
    // Schemat 8: galeria zdjęć wpisu Aktualności (pod treścią artykułu). Zdjęcia: uploads/aktualnosci/… z panelu; podpis jest też opisem (alt).
    // Klucz obcy wymaga InnoDB u rodzica, a pmg_aktualnosci (z dawnego pmg_db()) nie ma jawnego ENGINE — na starszej bazie mógł być MyISAM.
    if (strcasecmp((string) $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pmg_aktualnosci'")->fetchColumn(), 'InnoDB') !== 0) {
        $pdo->exec('ALTER TABLE pmg_aktualnosci ENGINE=InnoDB');
    }
    $tabele = [];
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_aktualnosci_galeria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wpis_id INT NOT NULL,
            zdjecie VARCHAR(200) NOT NULL,
            podpis VARCHAR(200) NOT NULL,
            kolejnosc SMALLINT NOT NULL DEFAULT 0,
            FOREIGN KEY (wpis_id) REFERENCES pmg_aktualnosci(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    // Schemat 12: wiadomości z formularza kontaktowego (api/kontakt.php), ekran „Wiadomości” w panelu (tylko administrator).
    // Bez adresu IP. Retencja: wiersze starsze niż 12 miesięcy usuwa panel przy wejściu na ten ekran.
    $tabele[] = "CREATE TABLE IF NOT EXISTS pmg_wiadomosci (
            id INT AUTO_INCREMENT PRIMARY KEY,
            utworzono DATETIME NOT NULL,
            imie VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            temat VARCHAR(150) NOT NULL,
            tresc TEXT NOT NULL,
            przeczytana TINYINT(1) NOT NULL DEFAULT 0,
            wyslano_mailem TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    foreach ($tabele as $sql) $pdo->exec($sql);

    // Bazy utworzone wcześniej: nowy moduł w SET uprawnień (schemat 6: 'case', schemat 7: 'tresci', schemat 9: 'rekrutacja') i kolumna opisu edycji PM Session.
    // Schemat 11 (W6, menu według stron): glowna, dolacz, kontakt — dopisane NA KOŃCU SET, więc kolejność bitów i zapisane
    // uprawnienia się nie zmieniają (danych nie przenosimy). 'tresci' zostaje w SET, ale panel już go nie nadaje.
    $pdo->exec("ALTER TABLE pmg_uzytkownicy MODIFY moduly SET('aktualnosci','czlonkowie','pmsession','podcast','case','tresci','rekrutacja','glowna','dolacz','kontakt') NOT NULL DEFAULT ''");
    // Schemat 9: „Treści stron” tylko dla administratorów — zdejmujemy 'tresci' z modułów wszystkich kont. Wartość zostaje w SET
    // (W6, schemat 11, danych nie przenosi: uprawnienie do strony daje jej teksty). Tylko przy przejściu z wersji < 9, żeby kolejne migracje nie zdejmowały uprawnień nadanych później.
    if ((int) $v < 9) {
        $pdo->exec("UPDATE pmg_uzytkownicy SET moduly = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', moduly, ','), ',tresci,', ',')) WHERE FIND_IN_SET('tresci', moduly) > 0");
    }
    // Schemat 10 (W8): dziennik w zdaniach — opis = czytelna nazwa rzeczy (np. tytuł wpisu) z chwili zdarzenia; starsze wpisy
    // dostają pusty opis (panel pokazuje wtedy #id). uzytkownik_id NULL = nieudane logowanie na nieznany adres (adresu nie zapisujemy).
    if ($pdo->query("SHOW COLUMNS FROM pmg_dziennik LIKE 'opis'")->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE pmg_dziennik ADD COLUMN IF NOT EXISTS opis VARCHAR(200) NOT NULL DEFAULT '' AFTER rekord_id");
    }
    $st = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pmg_dziennik' AND COLUMN_NAME = 'uzytkownik_id'");
    if ($st->fetchColumn() === 'NO') {
        // Klucz obcy do pmg_uzytkownicy zostaje (ten sam typ INT, zmienia się tylko NULL); sprawdzanie kluczy wyłączone tylko na czas
        // tej zmiany, bo część wersji MariaDB/MySQL odmawia MODIFY kolumny z kluczem obcym. Istniejące wiersze się nie zmieniają.
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $pdo->exec('ALTER TABLE pmg_dziennik MODIFY uzytkownik_id INT NULL');
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
    // Schemat 3: biogram prelegenta do 2500 znaków (dane startowe PM Session XIV mają biogramy dłuższe niż 1500).
    $pdo->exec("ALTER TABLE pmg_prelegenci MODIFY bio VARCHAR(2500) NOT NULL DEFAULT ''");
    if ($pdo->query("SHOW COLUMNS FROM pmg_edycje LIKE 'opis'")->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE pmg_edycje ADD COLUMN IF NOT EXISTS opis VARCHAR(600) NOT NULL DEFAULT '' AFTER miejsce");
    }
    // Schemat 4: przypisanie odcinka do edycji podcastu (NULL = bez edycji; usunięcie edycji z odcinkami blokuje panel).
    if ($pdo->query("SHOW COLUMNS FROM pmg_odcinki LIKE 'edycja_id'")->fetchColumn() === false) {
        $pdo->exec('ALTER TABLE pmg_odcinki ADD COLUMN IF NOT EXISTS edycja_id INT NULL');
    }
    // Schemat 5: lead wpisu (akapit pod tytułem w artykule) oddzielony od zajawki (krótki tekst na kafelku). Dotychczasowa
    // zajawka była jednym i drugim, więc istniejące wpisy dostają lead = zajawka i wyglądają tak samo; kategoria staje się opcjonalna.
    if ($pdo->query("SHOW COLUMNS FROM pmg_aktualnosci LIKE 'lead'")->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE pmg_aktualnosci ADD COLUMN IF NOT EXISTS lead VARCHAR(600) NOT NULL DEFAULT '' AFTER tytul");
        $pdo->exec('UPDATE pmg_aktualnosci SET lead = zajawka');
    }
    $pdo->exec("ALTER TABLE pmg_aktualnosci MODIFY kategoria VARCHAR(40) NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE pmg_aktualnosci MODIFY zajawka VARCHAR(400) NOT NULL DEFAULT ''");
    // Schemat 5: prelegent — opis prelekcji (wieloakapitowy) i rodzaj etykiety biogramu (m = „O prelegencie”, k = „O prelegentce”);
    // harmonogram — znacznik pod godziną (np. „3 sesje równoległe”), pusty = bez znacznika.
    if ($pdo->query("SHOW COLUMNS FROM pmg_prelegenci LIKE 'opis'")->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE pmg_prelegenci ADD COLUMN IF NOT EXISTS opis VARCHAR(4000) NOT NULL DEFAULT '' AFTER bio");
    }
    if ($pdo->query("SHOW COLUMNS FROM pmg_prelegenci LIKE 'plec'")->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE pmg_prelegenci ADD COLUMN IF NOT EXISTS plec CHAR(1) NOT NULL DEFAULT 'm' AFTER opis");
    }
    if ($pdo->query("SHOW COLUMNS FROM pmg_harmonogram LIKE 'znacznik'")->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE pmg_harmonogram ADD COLUMN IF NOT EXISTS znacznik VARCHAR(40) NOT NULL DEFAULT '' AFTER prelegent");
    }

    // Dane startowe: 4 odcinki, które do tej pory były wpisane na sztywno w podcast.html. Tylko przy pierwszym
    // utworzeniu tabeli — późniejsze usunięcie odcinków w panelu ich nie przywraca.
    if (!$podcastBylo) {
        $ins = $pdo->prepare('INSERT IGNORE INTO pmg_odcinki (numer, tytul, data, czas_min, opis, prowadzacy, gosc, gosc_bio, spotify_id, apple_url, zdjecie, zdjecie_alt, kolejnosc, opublikowany) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
        foreach (require __DIR__ . '/seed-podcast.php' as $o) $ins->execute($o);
    }

    // Dane startowe: Edycja 1 (2025/2026) z zespołem, który był wpisany na sztywno w podcast.html, i przypisanie do niej
    // wszystkich dotychczasowych odcinków. Tylko przy pierwszym utworzeniu tabeli edycji — późniejsze zmiany w panelu
    // (np. usunięcie edycji) nie są przywracane.
    if (!$podcastEdycjeBylo) {
        $ins = $pdo->prepare('INSERT IGNORE INTO pmg_podcast_edycje (numer, lata, koordynator, mentorzy, zespol, opis, kolejnosc, widoczna) VALUES (?,?,?,?,?,?,?,1)');
        foreach (require __DIR__ . '/seed-podcast-edycje.php' as $e) $ins->execute($e);
        $pdo->exec('UPDATE pmg_odcinki SET edycja_id = (SELECT id FROM pmg_podcast_edycje WHERE numer = 1) WHERE edycja_id IS NULL');
    }

    $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?, ?)')->execute(['schema', (string) PMG_SCHEMA]);
}

// Składa odpowiedź api/podcast.php: widoczne edycje (w kolejności z panelu) z ich opublikowanymi odcinkami.
// $edycje = wiersze pmg_podcast_edycje (widoczne, posortowane), $odcinki = opublikowane odcinki (posortowane), każdy z edycja_id.
// Odcinki bez edycji (edycja_id NULL) trafiają do ostatniej grupy bez nagłówka (numer = null); odcinki edycji
// ukrytej lub nieistniejącej nie są pokazywane. Zwraca [grupy, płaska lista odcinków w kolejności wyświetlania].
function pmg_podcast_grupuj($edycje, $odcinki)
{
    $grupy = [];
    $wg = [];
    foreach ($edycje as $e) {
        $wg[(int) $e['id']] = count($grupy);
        $grupy[] = [
            'numer' => (int) $e['numer'], 'lata' => $e['lata'], 'koordynator' => $e['koordynator'],
            'mentorzy' => $e['mentorzy'], 'zespol' => $e['zespol'], 'opis' => $e['opis'], 'odcinki' => [],
        ];
    }
    $bez = [];
    foreach ($odcinki as $o) {
        $eid = $o['edycja_id'];
        unset($o['edycja_id']);
        if ($eid === null) $bez[] = $o;
        elseif (isset($wg[(int) $eid])) $grupy[$wg[(int) $eid]]['odcinki'][] = $o;
    }
    if ($bez) $grupy[] = ['numer' => null, 'lata' => '', 'koordynator' => '', 'mentorzy' => '', 'zespol' => '', 'opis' => '', 'odcinki' => $bez];
    $plaska = [];
    foreach ($grupy as $g) foreach ($g['odcinki'] as $o) $plaska[] = $o;
    return [$grupy, $plaska];
}

// Karta edycji Case Koła w hubie (api/case-kola.php bez parametru). url = istniejący plik (adres_strony) albo wspólna podstrona.
// logo_styl: 'ciemne' (jasne logo na ciemnej karcie), 'jasne' (kolorowe logo na białej płytce), 'jasne-wysokie' (j.w., wyższe logo).
function pmg_case_karta($e, $root = null)
{
    return [
        'numer' => (int) $e['numer'], 'nazwa' => $e['nazwa'],
        'tytul' => $e['tytul_karty'] !== '' ? $e['tytul_karty'] : $e['nazwa'],
        'adres_strony' => $e['adres_strony'],
        'url' => $e['adres_strony'] !== '' ? $e['adres_strony'] : 'case-kola-edycja.html?nr=' . (int) $e['numer'],
        'logo' => $e['logo'], 'logo_obraz' => pmg_obraz($e['logo'], $root), 'logo_styl' => $e['logo_styl'],
    ];
}

// Pełna edycja Case Koła (api/case-kola.php?nr=N): karta + treść podstrony. hero_tryb: 'zdjecie' (zdjęcie główne), 'logo'
// (brak zdjęcia, a logo jest jasne — pokazujemy je na ciemnym tle jak w Solvro) albo 'brak' (sekcja pominięta).
// Puste teksty zostają pustymi napisami — strona pomija sekcję. $galeria = wiersze pmg_case_galeria w kolejności.
function pmg_case_pelna($e, $galeria, $root = null)
{
    $d = pmg_case_karta($e, $root);
    $d['naglowek'] = $e['naglowek'] !== '' ? $e['naglowek'] : $e['nazwa'];
    $d['opis_meta'] = $e['opis_meta'];
    $d['hero'] = $e['hero'];
    $d['hero_obraz'] = pmg_obraz($e['hero'], $root);
    $d['hero_alt'] = $e['hero_alt'];
    $d['hero_tryb'] = !empty($e['hero']) ? 'zdjecie' : (!empty($e['logo']) && $e['logo_styl'] === 'ciemne' ? 'logo' : 'brak');
    foreach (['o_partnerze', 'wyzwanie', 'co_zrobilismy', 'rezultat', 'w_toku'] as $k) $d[$k] = $e[$k];
    $d['galeria'] = [];
    foreach ($galeria as $g) {
        $d['galeria'][] = ['zdjecie' => $g['zdjecie'], 'obraz' => pmg_obraz($g['zdjecie'], $root), 'pelne' => $g['pelne'], 'podpis' => $g['podpis']];
    }
    return $d;
}

// Warianty szerokości zdjęcia z katalogu img/ do <picture>. Dla ścieżki img/<baza>-<szerokość>.<jpg|png> szuka plików
// img/<baza>-<N>.<to samo rozszerzenie> i ich odpowiedników .webp. Zwraca [src => najmniejszy wariant, srcset => 'ścieżka Nw, …',
// webp => srcset z .webp albo '', w/h => wymiary największego wariantu] albo null (zdjęcie wgrane z panelu w uploads/, plik
// nietypowy albo brak pliku — wtedy strona używa zwykłego <img>). $root = katalog główny strony (z końcowym ukośnikiem).
function pmg_obraz($sciezka, $root = null)
{
    if (!is_string($sciezka) || !preg_match('~^(img/[a-z0-9-]+)-(\d+)\.(jpg|png)$~', $sciezka, $m)) return null;
    if ($root === null) $root = dirname(__DIR__) . '/';
    $baza = $m[1];
    $ext = $m[3];
    $szer = [];
    foreach ((array) glob($root . $baza . '-*.' . $ext) as $plik) {
        // dokładnie <baza>-<liczba>.<ext>: wzorzec glob dopasowałby też dłuższe nazwy (np. -43-800 przy bazie bez -43)
        if (preg_match('~^' . preg_quote(basename($baza), '~') . '-(\d+)\.' . $ext . '$~', basename($plik), $n)) $szer[(int) $n[1]] = true;
    }
    if (!$szer || !isset($szer[(int) $m[2]])) return null;
    ksort($szer);
    $skladaj = function ($rozsz) use ($baza, $szer) {
        $c = [];
        foreach (array_keys($szer) as $w) $c[] = $baza . '-' . $w . '.' . $rozsz . ' ' . $w . 'w';
        return implode(', ', $c);
    };
    $webp = [];
    foreach (array_keys($szer) as $w) {
        if (is_file($root . $baza . '-' . $w . '.webp')) $webp[] = $baza . '-' . $w . '.webp ' . $w . 'w';
    }
    $szerokosci = array_keys($szer);
    $najw = end($szerokosci);
    $wym = @getimagesize($root . $baza . '-' . $najw . '.' . $ext);
    return [
        'src' => $baza . '-' . $szerokosci[0] . '.' . $ext,
        'srcset' => $skladaj($ext),
        'webp' => count($webp) === count($szer) ? implode(', ', $webp) : '',
        'w' => $wym ? (int) $wym[0] : (int) $najw,
        'h' => $wym ? (int) $wym[1] : 0,
    ];
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
// $key = klucz zdarzenia (np. id konta); domyślnie adres IP. Każde wywołanie liczy się jako próba.
// Cały odczyt+decyzja+zapis w jednej sekcji krytycznej (flock) — równoległe żądania nie przejdą ponad limit.
// Katalog na liczniki prób i sesje panelu: config 'tmp_dir' (poza webrootem, tworzony z prawami 0700) albo
// systemowy katalog tymczasowy (może być współdzielony z innymi kontami na hostingu).
function pmg_tmp_dir()
{
    static $dir = null;
    if ($dir === null) {
        $dir = sys_get_temp_dir();
        $c = pmg_config();
        $wlasny = isset($c['tmp_dir']) ? rtrim((string) $c['tmp_dir'], '/\\') : '';
        if ($wlasny !== '') {
            if (!@is_dir($wlasny)) @mkdir($wlasny, 0700, true);
            if (@is_dir($wlasny) && @is_writable($wlasny)) $dir = $wlasny;
            else error_log('pmg_tmp_dir: katalog z configu niedostępny do zapisu: ' . $wlasny);
        }
    }
    return $dir;
}

function pmg_rate_file($bucket, $key)
{
    return pmg_tmp_dir() . '/pmg_' . $bucket . '_' . md5($key !== null ? $key : ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function pmg_rate_ok($bucket, $max, $window, $key = null)
{
    if (mt_rand(1, 100) === 1) pmg_rate_sprzatanie();
    $fh = @fopen(pmg_rate_file($bucket, $key), 'c+');
    // A2 3.12: ostrzeżenie w logu serwera (bez klucza — może zawierać e-mail), gdy limit nie działa, bo pliku nie da się otworzyć lub zapisać.
    if ($fh === false) { error_log('pmg_rate_ok: OSTRZEŻENIE — limit prób nie działa, brak zapisu w ' . pmg_tmp_dir()); return true; } // ponytail: fail-open — limit nie jest jedyną obroną
    flock($fh, LOCK_EX);
    $now = time();
    $hits = array_filter(explode(',', (string) stream_get_contents($fh)), function ($t) use ($now, $window) {
        return (int) $t > $now - $window;
    });
    $ok = count($hits) < $max;
    if ($ok) {
        $hits[] = $now;
        rewind($fh);
        $dane = implode(',', $hits);
        if (!ftruncate($fh, 0) || fwrite($fh, $dane) !== strlen($dane)) error_log('pmg_rate_ok: OSTRZEŻENIE — limit prób nie działa, nie udał się zapis pliku w ' . pmg_tmp_dir());
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}

// Po udanym logowaniu: licznik prób konta od zera (udane logowania nie blokują konta).
function pmg_rate_clear($bucket, $key)
{
    @unlink(pmg_rate_file($bucket, $key));
}

// Usuwa pliki liczników starsze niż doba (wołane przy ok. 1% żądań).
function pmg_rate_sprzatanie()
{
    foreach ((array) @glob(pmg_tmp_dir() . '/pmg_*') as $f) {
        if (is_file($f) && filemtime($f) < time() - 86400) @unlink($f);
    }
}
