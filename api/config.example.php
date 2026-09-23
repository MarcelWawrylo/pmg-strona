<?php
// Wzór konfiguracji. Na serwerze skopiuj ten plik jako config.php (ten sam katalog) i uzupełnij.
// config.php NIE trafia do repozytorium (.gitignore) — trzyma dane dostępowe do bazy.
return [
    // MariaDB na serwerze PWr
    'db_host' => 'localhost',
    'db_name' => 'do_ustalenia',
    'db_user' => 'do_ustalenia',
    'db_pass' => 'do_ustalenia',

    // Panel aktualności: hash hasła z panel/ustaw-haslo.php (samo hasło nigdzie nie jest zapisywane)
    'panel_hash' => '',

    // Formularz kontaktowy
    'mail_to'   => 'pmgroup.kontakt@gmail.com',
    'mail_from' => 'noreply@pmgroup.pwr.edu.pl', // adres w domenie serwera, inaczej Gmail odrzuca jako spam
];
