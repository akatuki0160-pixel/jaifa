<?php
/**
 * 結果画面のボタン
 *
 * @var array<string, mixed> $site
 */
?>
  <div class="form2025BtnArea">
<?php if (($site['back_to_input_url'] ?? '') !== ''): ?>
    <a class="formBtn" href="<?= h($site['back_to_input_url']) ?>">会員番号入力に戻る</a>
<?php endif; ?>
    <a class="formBtn btnBack" href="qryNo.php">問い合せ画面に戻る</a>
  </div>
