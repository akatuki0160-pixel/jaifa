<?php
/**
 * 検索結果: 会員番号が見つかった
 *
 * @var Qryno\View $view
 * @var array<string, mixed> $site
 * @var Qryno\SearchForm $form
 * @var string $memberNo
 */
$trim = static fn (string $value): string => (string) preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $value);
?>
<main id="form2025" class="ok">
<img class="headerLogo" src="<?= h($view->asset($site['logo'])) ?>" alt="<?= h($site['logo_alt']) ?>">
  <h1>会員番号のお問い合わせ結果</h1>

  <div class="infoArea">

    <div class="msgArea">
      <p>お問い合わせいただきました会員の会員番号は以下のとおりです。</p>
      <p class="result">会員番号：<strong class="memberNo"><?= h($memberNo) ?></strong></p>
    </div>

    <dl>
      <dt>所属協会</dt>
      <dd><?= h($form->values['kyokai']) ?></dd>
    </dl>
    <dl>
      <dt>氏名(漢字)</dt>
      <dd><?= h($trim($form->values['sei'])) ?>　<?= h($trim($form->values['mei'])) ?></dd>
    </dl>
    <dl>
      <dt>会社名</dt>
      <dd><?= h($form->values['company']) ?></dd>
    </dl>
<?php if (($site['back_to_input_url'] ?? '') !== ''): ?>
    <p class="notice">※「会員番号入力に戻る」ボタンから、上記の会員番号を入力してください。</p>
<?php endif; ?>

  </div>

<?= $view->render('search/buttons') ?>

</main>
