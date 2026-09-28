<?php
/**
 * 入力画面（未入力があれば該当項目を赤枠で表示）
 *
 * @var Qryno\View $view
 * @var array<string, mixed> $site
 * @var Qryno\SearchForm $form
 * @var string $message
 * @var string $token
 * @var list<string> $kyokaiOptions
 * @var list<string> $companyOptions
 * @var int $maxNameLength
 */
$invalid = static fn (string $field): string => $form->hasError($field) ? ' aria-invalid="true"' : '';
$alart = static fn (string $field): string => $form->hasError($field) ? ' alart' : '';
?>
<main id="form2025">
<img class="headerLogo" src="<?= h($view->asset($site['logo'])) ?>" alt="<?= h($site['logo_alt']) ?>">
<h1>会員番号のお問い合わせ</h1>
  <!-- フォーム -->
  <form name="form1" action="qryNoExe.php" method="post">
  <input type="hidden" name="sesstxt" value="<?= h($token) ?>">

    <div class="infoArea">

    <p>以下の項目を入力のうえ、「検索」ボタンをクリックしてください。</p>
    <p class="notice"><em>※</em>すべて必須項目です。<?php if (!$form->isValid()): ?><br class="br-sp"><span class="alart">&#x26a0;未入力項目があります&#x26a0;</span><?php endif; ?></p>
<?php if ($message !== ''): ?>
    <p class="notice"><span class="alart"><?= h($message) ?></span></p>
<?php endif; ?>

      <dl>
        <dt><label for="kyokai">所属協会</label><em>※1</em></dt>
        <dd>
          <select name="kyokai" id="kyokai"<?= $form->hasError('kyokai') ? ' class="alart"' : '' ?><?= $invalid('kyokai') ?>>
            <option value="">プルダウンで選択</option>
<?php foreach ($kyokaiOptions as $option): ?>
            <option value="<?= h($option) ?>"<?= $option === $form->values['kyokai'] ? ' selected' : '' ?>><?= h($option) ?></option>
<?php endforeach; ?>
          </select>
        </dd>
      </dl>
      <dl>
        <dt>氏名(漢字)<em>※2</em></dt>
        <dd>
          <label for="sei">姓</label><input type="text" name="sei" id="sei" value="<?= h($form->values['sei']) ?>" class="textField w40per<?= $alart('sei') ?>" maxlength="<?= $maxNameLength ?>" autocomplete="family-name"<?= $invalid('sei') ?>><br class="br-sp">
          <label for="mei">名</label><input type="text" name="mei" id="mei" value="<?= h($form->values['mei']) ?>" class="textField w40per<?= $alart('mei') ?>" maxlength="<?= $maxNameLength ?>" autocomplete="given-name"<?= $invalid('mei') ?>>
        </dd>
      </dl>
      <dl>
        <dt><label for="company">会社名</label><em>※3</em></dt>
        <dd>
          <select name="company" id="company"<?= $form->hasError('company') ? ' class="alart"' : '' ?><?= $invalid('company') ?>>
            <option value="">プルダウンで選択</option>
<?php foreach ($companyOptions as $option): ?>
            <option value="<?= h($option) ?>"<?= $option === $form->values['company'] ? ' selected' : '' ?>><?= h($option) ?></option>
<?php endforeach; ?>
          </select>
        </dd>
      </dl>
    <p class="notice">※1:ご勤務先の都道府県が所属協会となります。</p>
    <p class="notice"><em>※2:登録漢字の表記違いによりエラーとなる場合は、代表受取人用の会員名簿内(代理店で議案書を直接受取された方は宛名シールに記載)の漢字氏名を入力ください。</em></p>
    <p class="notice">※3:代理店ご所属の方は選択肢「代理店」を選択ください。</p>

    </div>

    <div class="form2025BtnArea">
<?php if (($site['back_to_input_url'] ?? '') !== ''): ?>
      <a class="formBtn" href="<?= h($site['back_to_input_url']) ?>">会員番号入力に戻る</a>
<?php endif; ?>
      <input type="submit" value="検索" class="formBtn btnSearch">
    </div>

  </form>

</main>
