<?php
// Formularz kontaktowy (kontakt.html) → e-mail na adres z config.php. Odpowiedź JSON.
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') pmg_json(['ok' => false, 'error' => 'Metoda niedozwolona.'], 405);

// honeypot: pole niewidoczne dla ludzi — boty je wypełniają; udajemy sukces
if (($_POST['website'] ?? '') !== '') pmg_json(['ok' => true]);

$clean = function ($k, $max) {
    $v = trim((string) ($_POST[$k] ?? ''));
    return mb_strlen($v) > $max ? '' : $v;
};
$name = str_replace(["\r", "\n"], ' ', $clean('name', 100));
$email = $clean('email', 150);
$subject = str_replace(["\r", "\n"], ' ', $clean('subject', 150));
$message = $clean('message', 5000);

if ($name === '' || $subject === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    pmg_json(['ok' => false, 'error' => 'Uzupełnij wszystkie pola i podaj poprawny adres e-mail.'], 422);
}
if (!pmg_rate_ok('kontakt', 5, 600)) {
    pmg_json(['ok' => false, 'error' => 'Za dużo wiadomości w krótkim czasie. Spróbuj za kilka minut.'], 429);
}

$c = pmg_config();
$headers = implode("\r\n", [
    'From: Strona PMG <' . $c['mail_from'] . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);
$body = $message . "\n\n—\n" . $name . "\n" . $email . "\n(wiadomość z formularza na stronie PMG)";
$sent = mail($c['mail_to'], '=?UTF-8?B?' . base64_encode('[Strona PMG] ' . $subject) . '?=', $body, $headers);

$sent ? pmg_json(['ok' => true]) : pmg_json(['ok' => false, 'error' => 'Nie udało się wysłać wiadomości.'], 500);
