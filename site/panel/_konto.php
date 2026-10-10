<?php
// Moduł „Moje konto”: każda zalogowana osoba (administrator i redaktor) zmienia tu swoje imię i nazwisko oraz hasło.
// Rola i moduły zmienia tylko inny administrator (Konta). Router wpuszcza tu każdego zalogowanego (index.php).
defined('PMG_PANEL') || exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['a'] ?? '';

    if ($action === 'imie') {
        $imie = mb_substr(trim((string) ($_POST['imie_nazwisko'] ?? '')), 0, 100);
        if ($imie === '') {
            $error = 'Podaj imię i nazwisko.';
        } else {
            pmg_db()->prepare('UPDATE pmg_uzytkownicy SET imie_nazwisko = ? WHERE id = ?')->execute([$imie, $me['id']]);
            loguj('konta', 'edycja', $me['id'], $imie);
            $_SESSION['flash'] = 'Zapisano imię i nazwisko.';
            go('?m=konto');
        }

    } elseif ($action === 'zmien_haslo') {
        $obecne = (string) ($_POST['obecne'] ?? '');
        $nowe = (string) ($_POST['nowe'] ?? '');
        // Powtórzenie nowego hasła chroni przed literówką, po której nie dałoby się zalogować.
        $powtorz = (string) ($_POST['powtorz'] ?? '');
        if (!pmg_rate_ok('haslo_konto', 5, 900, 'konto:' . $me['id'])) { // A2 3.10: osobny limit — złe hasło tutaj nie blokuje logowania
            $error = 'Za dużo prób. Spróbuj za 15 minut.';
        } elseif (!password_verify($obecne, (string) $me['haslo'])) {
            $error = 'Obecne hasło jest nieprawidłowe.';
        } elseif (mb_strlen($nowe) < 12) {
            $error = 'Nowe hasło musi mieć co najmniej 12 znaków.';
        } elseif ($nowe !== $powtorz) {
            $error = 'Nowe hasło i jego powtórzenie się różnią.';
        } else {
            $hash = password_hash($nowe, PASSWORD_DEFAULT);
            pmg_db()->prepare('UPDATE pmg_uzytkownicy SET haslo = ?, token_hash = NULL, token_do = NULL WHERE id = ?')
                ->execute([$hash, $me['id']]);
            pmg_rate_clear('haslo_konto', 'konto:' . $me['id']);
            // Inne sesje tego konta mają stary skrót hasła w 'ph' i wygasną przy następnym kliknięciu (index.php, blok $me);
            // ta sesja dostaje nowy skrót i zostaje ważna.
            session_regenerate_id(true);
            $_SESSION['ph'] = hash('sha256', $hash);
            loguj('konta', 'haslo', $me['id'], $me['imie_nazwisko']);
            $_SESSION['flash'] = 'Hasło zmienione. Na innych urządzeniach trzeba zalogować się ponownie.';
            go('?m=konto');
        }
    }
}

$pmgNaglowek = ['tytul' => 'Moje konto', 'opis' => (string) $me['email']];
?>
<form class="pmg-card" method="post" data-pmg-niezapisane>
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="imie">
  <h2 class="pmg-h2">Dane</h2>
  <label for="imie_nazwisko">Imię i nazwisko</label>
  <input type="text" id="imie_nazwisko" name="imie_nazwisko" maxlength="100" value="<?= h(($_POST['a'] ?? '') === 'imie' ? ($_POST['imie_nazwisko'] ?? '') : $me['imie_nazwisko']) ?>" required>
  <p class="pmg-hint">Rola: <?= $me['rola'] === 'admin' ? 'Administrator' : 'Redaktor' ?>. E-mail, rolę i moduły zmienia administrator w module Konta.</p>
  <div class="pmg-form-actions">
    <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
  </div>
</form>

<form class="pmg-card" method="post">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="zmien_haslo">
  <h2 class="pmg-h2">Zmiana hasła</h2>
  <label for="obecne">Obecne hasło</label>
  <input type="password" id="obecne" name="obecne" autocomplete="current-password" required>
  <label for="nowe">Nowe hasło (min. 12 znaków)</label>
  <p class="pmg-hint" id="nowe_h">Użyj hasła, którego nie używasz nigdzie indziej.</p>
  <input type="password" id="nowe" name="nowe" minlength="12" autocomplete="new-password" required aria-describedby="nowe_h">
  <label for="powtorz">Powtórz nowe hasło</label>
  <input type="password" id="powtorz" name="powtorz" minlength="12" autocomplete="new-password" required>
  <div class="pmg-form-actions">
    <button class="pmg-btn pmg-btn--primary" type="submit">Zmień hasło</button>
  </div>
</form>
