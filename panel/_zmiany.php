<?php
// „Co nowego”: lista zmian strony i panelu z _wersja.php (tylko odczyt, dla każdego zalogowanego). Wołany wyłącznie z index.php (?m=zmiany).
defined('PMG_PANEL') || exit;
?>
<?php foreach ($pmgZmiany as $z): ?>
  <section class="pmg-card" aria-labelledby="wersja-<?= h($z['wersja']) ?>">
    <div class="pmg-card__head"><h2 class="pmg-h2" id="wersja-<?= h($z['wersja']) ?>">Wersja <?= h($z['wersja']) ?> <span class="pmg-hint">· <time datetime="<?= h($z['data']) ?>"><?= h(date('d.m.Y', strtotime($z['data']))) ?></time></span></h2></div>
    <?php foreach (['strona' => 'Strona', 'panel' => 'Panel'] as $k => $tytul): if (empty($z[$k])) continue; ?>
      <h3 class="pmg-form-section__title"><?= $tytul ?></h3>
      <ul class="pmg-zmiany"><?php foreach ($z[$k] as $zd): ?><li><?= h($zd) ?></li><?php endforeach; ?></ul>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
