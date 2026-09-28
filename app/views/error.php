<?php
/**
 * システムエラー画面
 *
 * @var Qryno\View $view
 * @var array<string, mixed> $site
 * @var string $detail config の debug が true のときだけ詳細が入る
 */
?>
<main id="form2025" class="ng">
<img class="headerLogo" src="<?= h($view->asset($site['logo'])) ?>" alt="<?= h($site['logo_alt']) ?>">
  <h1>システムエラー</h1>

  <div class="infoArea">

    <div class="msgArea">
      <p>システムエラーが発生したため、処理を完了できませんでした。</p>
      <p>恐れ入りますが、時間をおいて再度お試しいただくか、下記本部事務局までご連絡ください。</p>
    </div>

<?= $view->render('search/contact') ?>

  </div>
<?php if ($detail !== ''): ?>
  <pre class="debugDetail"><?= h($detail) ?></pre>
<?php endif; ?>

</main>
