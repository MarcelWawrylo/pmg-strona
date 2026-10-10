<?php
// Wiadomości z formularza na stronie Kontakt (tabela pmg_wiadomosci, zapisuje je api/kontakt.php). Tylko administrator
// (MODULY: 'wiadomosci' => 'admin'), bo to dane osobowe gości. Wołany wyłącznie z index.php (?m=wiadomosci).
defined('PMG_PANEL') || exit;

// Retencja (polityka prywatności, 3.1): wiadomości starsze niż 12 miesięcy znikają przy każdym wejściu na ten ekran.
pmg_db()->exec('DELETE FROM pmg_wiadomosci WHERE utworzono < NOW() - INTERVAL 12 MONTH');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['a'] ?? '') === 'delete') { // CSRF sprawdza index.php dla każdego POST
    $id = (int) ($_POST['id'] ?? 0);
    $st = pmg_db()->prepare('SELECT utworzono FROM pmg_wiadomosci WHERE id = ?');
    $st->execute([$id]);
    $kiedy = $st->fetchColumn();
    $usun = pmg_db()->prepare('DELETE FROM pmg_wiadomosci WHERE id = ?');
    $usun->execute([$id]);
    if ($usun->rowCount() === 0) {
        $_SESSION['flash'] = 'Nie znaleziono wiadomości — nic nie usunięto.';
        go('?m=wiadomosci');
    }
    // W dzienniku tylko data wiadomości — bez nadawcy, tematu i treści (dane osobowe gościa).
    loguj('wiadomosci', 'usuniecie', $id, 'Wiadomość z ' . date('d.m.Y H:i', strtotime((string) $kiedy)));
    $_SESSION['flash'] = 'Wiadomość usunięta.';
    go('?m=wiadomosci');
}

$wiad = null;
if (isset($_GET['id'])) {
    $st = pmg_db()->prepare('SELECT * FROM pmg_wiadomosci WHERE id = ?');
    $st->execute([(int) $_GET['id']]);
    $wiad = $st->fetch() ?: null;
    // Otwarcie = przeczytana. Zmiana przy GET jest tu celowa: niczego nie usuwa ani nie publikuje, a link podsunięty
    // administratorowi może najwyżej oznaczyć wiadomość jako przeczytaną (lista nadal ją pokazuje).
    if ($wiad && !$wiad['przeczytana']) pmg_db()->prepare('UPDATE pmg_wiadomosci SET przeczytana = 1 WHERE id = ?')->execute([(int) $wiad['id']]);
}

if ($wiad) {
    $kiedy = strtotime((string) $wiad['utworzono']);
    $pmgNaglowek = [
        'tytul' => $wiad['temat'],
        'opis' => 'Od: ' . $wiad['imie'] . ' (' . $wiad['email'] . ') · ' . date('d.m.Y H:i', $kiedy),
        'wstecz' => ['href' => '?m=wiadomosci', 'etykieta' => 'Wszystkie wiadomości'],
        'akcje' => [['href' => 'mailto:' . rawurlencode($wiad['email']) . '?subject=' . rawurlencode('Re: ' . $wiad['temat']), 'etykieta' => 'Odpowiedz e-mailem', 'rodzaj' => 'primary']],
        'chip' => $wiad['wyslano_mailem'] ? null : ['tekst' => 'Mail nie wyszedł', 'wariant' => 'warning'],
    ];
    ?>
    <?php if (!$wiad['wyslano_mailem']): ?>
      <div class="pmg-alert pmg-alert--warning"><?= pmg_ikona('info') ?><p>Tej wiadomości nie udało się wysłać na skrzynkę koła — jest tylko tutaj. Odpowiedz przyciskiem „Odpowiedz e-mailem”.</p></div>
    <?php endif; ?>
    <div class="pmg-card">
      <p class="pmg-wiadomosc"><?= nl2br(h($wiad['tresc']), false) ?></p>
    </div>
    <form method="post" class="pmg-danger-zone" aria-labelledby="usun-h">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int) $wiad['id'] ?>">
      <h2 class="pmg-danger-zone__title" id="usun-h">Strefa usuwania</h2>
      <p class="pmg-hint">Wiadomość zniknie z panelu. Tego nie da się cofnąć. Bez usuwania zniknie sama po 12 miesiącach.</p>
      <label class="pmg-check"><input type="checkbox" required><span>Tak, usuń tę wiadomość na stałe</span></label>
      <button class="pmg-btn pmg-btn--danger" type="submit">Usuń wiadomość</button>
    </form>
    <?php
    return;
}

if (isset($_GET['id'])) echo '<div class="pmg-alert pmg-alert--info">' . pmg_ikona('info') . '<p>Nie ma już tej wiadomości (usunięta albo starsza niż 12 miesięcy).</p></div>';
$lista = pmg_db()->query('SELECT id, utworzono, imie, email, temat, przeczytana, wyslano_mailem FROM pmg_wiadomosci ORDER BY utworzono DESC, id DESC')->fetchAll();
?>
<div class="pmg-table-wrap">
  <table class="pmg-table pmg-table--klikalna">
    <caption class="pmg-vh">Wiadomości z formularza kontaktowego, od najnowszych</caption>
    <thead><tr><th scope="col">Temat</th><th scope="col">Od</th><th scope="col">Data</th><th scope="col">Status</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $w): $kiedy = strtotime((string) $w['utworzono']); ?>
      <tr>
        <td class="pmg-td-main" data-label="Temat"><a class="pmg-row-link" href="?m=wiadomosci&id=<?= (int) $w['id'] ?>"><?= $w['przeczytana'] ? h($w['temat']) : '<strong>' . h($w['temat']) . '</strong>' ?></a></td>
        <td data-label="Od"><?= h($w['imie']) ?><span class="pmg-row-sub"><?= h($w['email']) ?></span></td>
        <td class="pmg-num" data-label="Data"><time datetime="<?= h(date('Y-m-d\TH:i', $kiedy)) ?>"><?= h(date('d.m.Y H:i', $kiedy)) ?></time></td>
        <td data-label="Status">
          <?php if (!$w['przeczytana']): ?><span class="pmg-chip pmg-chip--accent">Nowa</span><?php else: ?><span class="pmg-chip pmg-chip--neutral">Przeczytana</span><?php endif; ?>
          <?php if (!$w['wyslano_mailem']): ?><span class="pmg-chip pmg-chip--warning">Mail nie wyszedł</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="4" class="pmg-empty">Nie ma jeszcze wiadomości.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
