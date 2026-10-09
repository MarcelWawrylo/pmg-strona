<?php
// Wzór konfiguracji (hasła do bazy i poczty). Ten plik jest tylko wzorem — nie wpisuj tu prawdziwych haseł.
//
// GDZIE POŁOŻYĆ PLIK Z HASŁAMI (najlepiej POZA katalogiem strony, żeby nie dało się go pobrać z przeglądarki):
//   skopiuj ten plik jako  pmg-config.php  do katalogu NAD katalogiem głównym domeny,
//   np. gdy strona leży w  /home/konto/public_html/  → plik ma być w  /home/konto/pmg-config.php
// Kolejność szukania (api/lib.php, pmg_config()): zmienna środowiskowa PMG_CONFIG (pełna ścieżka) →
//   pmg-config.php nad katalogiem głównym domeny → pmg-config.php nad katalogiem site/ → api/config.php.
// api/config.php (w katalogu strony, chroniony tylko przez api/.htaccess) działa nadal, ale jest rozwiązaniem awaryjnym.
// Żaden z tych plików nie trafia do repozytorium (.gitignore).
return [
    // MariaDB na serwerze PWr
    'db_host' => 'localhost',
    'db_name' => 'do_ustalenia',
    'db_user' => 'do_ustalenia',
    'db_pass' => 'do_ustalenia',

    // Jednorazowe hasło do założenia pierwszego konta admina (min. 12 znaków, losowe, najlepiej 20+).
    // Załóż konto od razu po wgraniu strony, potem usuń tę linię.
    'setup_haslo' => '',

    // Opcjonalnie: katalog na sesje panelu i liczniki prób logowania — POZA webrootem, np. '/home/konto/pmg-tmp'.
    // Panel sam go utworzy (uprawnienia 0700). Puste = systemowy katalog tymczasowy (może być współdzielony z innymi kontami).
    'tmp_dir' => '',

    // Formularz kontaktowy
    'mail_to'   => 'pmgroup.kontakt@gmail.com',
    'mail_from' => 'noreply@pmgroup.pwr.edu.pl', // adres w domenie serwera, inaczej Gmail odrzuca jako spam
];
