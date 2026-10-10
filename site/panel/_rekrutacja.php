<?php
// Moduł „Rekrutacja”: status naboru, link do formularza i krótki tekst na stronie Dołącz. Administrator i redaktor
// z modułem 'rekrutacja' (np. osoba z HR). Klucze w pmg_ustawienia te same co dawniej w Ustawieniach strony, więc
// api/ustawienia.php i strona się nie zmieniają. Zapis zmienia tylko klucze z $pola — pozostałych ustawień nie rusza.
defined('PMG_PANEL') || exit;

$pola = ['rekrutacja_otwarta', 'rekrutacja_link', 'rekrutacja_tekst'];

$st = pmg_db()->prepare('SELECT klucz, wartosc FROM pmg_ustawienia WHERE klucz IN (' . implode(',', array_fill(0, count($pola), '?')) . ')');
$st->execute($pola);
$wartosci = array_fill_keys($pola, '');
foreach ($st->fetchAll() as $r) $wartosci[$r['klucz']] = $r['wartosc'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wejscie = [
        'rekrutacja_otwarta' => ($_POST['rekrutacja_otwarta'] ?? '') === '0' ? '0' : '1',
        'rekrutacja_link' => trim((string) ($_POST['rekrutacja_link'] ?? '')),
        'rekrutacja_tekst' => trim((string) ($_POST['rekrutacja_tekst'] ?? '')),
    ];
    $wartosci = $wejscie; // formularz zachowuje wpisane wartości, jeśli coś jest nie tak

    if (!url_ok($wejscie['rekrutacja_link'])) $bledyPol['rekrutacja_link'] = 'Link do formularza rekrutacyjnego: podaj pełny adres zaczynający się od https:// albo zostaw puste pole.';
    if (mb_strlen($wejscie['rekrutacja_tekst']) > 300) $bledyPol['rekrutacja_tekst'] = 'Tekst o rekrutacji może mieć maksymalnie 300 znaków.';

    if (!$bledyPol) {
        $pdo = pmg_db();
        $pdo->beginTransaction();
        $upd = $pdo->prepare('REPLACE INTO pmg_ustawienia (klucz, wartosc) VALUES (?,?)');
        foreach ($pola as $k) $upd->execute([$k, $wejscie[$k]]);
        $pdo->commit();
        loguj('rekrutacja', 'edycja', null, 'Status, link i tekst rekrutacji');
        $_SESSION['flash'] = 'Zapisano. Zmiany widać na stronie w ciągu 5 minut.';
        go('?m=rekrutacja');
    }
}
?>
<?php // Błąd ($error) wyświetla wspólny szablon w index.php — nie powielamy go tutaj. ?>
<form class="pmg-card" method="post" data-pmg-niezapisane>
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

  <fieldset class="pmg-fieldset">
    <legend class="pmg-legend">Status rekrutacji</legend>
    <p class="pmg-hint" id="rekrutacja_otwarta_h">Przy „Zamknięta” strona Dołącz ukrywa przycisk do formularza i podpowiedź pod nim.</p>
    <div class="pmg-options">
      <label class="pmg-option"><input type="radio" name="rekrutacja_otwarta" value="1" aria-describedby="rekrutacja_otwarta_h"<?= $wartosci['rekrutacja_otwarta'] !== '0' ? ' checked' : '' ?>><span>Otwarta</span></label>
      <label class="pmg-option"><input type="radio" name="rekrutacja_otwarta" value="0" aria-describedby="rekrutacja_otwarta_h"<?= $wartosci['rekrutacja_otwarta'] === '0' ? ' checked' : '' ?>><span>Zamknięta</span></label>
    </div>
  </fieldset>

  <label for="rekrutacja_link">Link do formularza rekrutacyjnego</label>
  <p class="pmg-hint" id="rekrutacja_link_h">Pełny adres zaczynający się od https://. Puste pole = strona pokazuje obecny link.</p>
  <input type="text" id="rekrutacja_link" name="rekrutacja_link" value="<?= h($wartosci['rekrutacja_link']) ?>"<?= blad_pola('rekrutacja_link', 'rekrutacja_link_h') ?>><?= komunikat_pola('rekrutacja_link') ?>

  <label for="rekrutacja_tekst">Krótki tekst o rekrutacji</label>
  <p class="pmg-hint" id="rekrutacja_tekst_h">Maksymalnie 300 znaków. Puste pole = strona pokazuje obecny tekst.</p>
  <textarea id="rekrutacja_tekst" name="rekrutacja_tekst" maxlength="300"<?= blad_pola('rekrutacja_tekst', 'rekrutacja_tekst_h') ?> data-pmg-licznik><?= h($wartosci['rekrutacja_tekst']) ?></textarea><?= komunikat_pola('rekrutacja_tekst') ?>

  <div class="pmg-form-actions">
    <button class="pmg-btn pmg-btn--primary" type="submit">Zapisz</button>
  </div>
</form>
