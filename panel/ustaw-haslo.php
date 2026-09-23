<?php
// Generator hasha hasła do panelu. Niczego nie zapisuje — pokazuje hash do wklejenia w api/config.php ('panel_hash').
// Działa tylko, dopóki hasło nie jest ustawione.
require __DIR__ . '/../api/lib.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
if (pmg_config()['panel_hash'] !== '') { http_response_code(403); exit('Hasło jest już ustawione. Aby je zmienić, wyczyść panel_hash w api/config.php.'); }
$p = (string) ($_POST['haslo'] ?? '');
?>
<!DOCTYPE html>
<html lang="pl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Ustaw hasło panelu</title></head>
<body style="font: 16px/1.5 system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px">
<h1>Hasło do panelu Aktualności</h1>
<?php if (mb_strlen($p) >= 12): ?>
  <p>Wklej poniższy tekst do <code>api/config.php</code> jako wartość <code>'panel_hash'</code>:</p>
  <p><textarea readonly rows="3" style="width:100%"><?= htmlspecialchars(password_hash($p, PASSWORD_DEFAULT), ENT_QUOTES, 'UTF-8') ?></textarea></p>
<?php else: ?>
  <?php if ($p !== ''): ?><p role="alert">Hasło musi mieć co najmniej 12 znaków.</p><?php endif; ?>
  <form method="post"><label for="haslo">Nowe hasło (min. 12 znaków)</label><br>
  <input type="password" id="haslo" name="haslo" minlength="12" required autocomplete="new-password" style="width:100%;padding:8px">
  <p><button type="submit">Pokaż hash</button></p></form>
<?php endif; ?>
</body></html>
