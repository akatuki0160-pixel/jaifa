<?php
/**
 * 検索結果: 会員番号をお知らせできない場合（該当なし・複数該当・回数制限・データ未登録）
 *
 * @var Qryno\View $view
 * @var array<string, mixed> $site
 * @var string $status
 */
$messages = [
    'not_found' => [
        'お問い合わせいただきました所属協会、氏名、会社名では検索できませんでした。',
        '恐れ入りますが下記本部事務局までご連絡ください。',
    ],
    'multiple' => [
        'お問い合わせいただきました所属協会、氏名、会社名に該当する会員が複数登録されているため、会員番号を特定できませんでした。',
        '恐れ入りますが下記本部事務局までご連絡ください。',
    ],
    'limited' => [
        '検索の回数が上限に達したため、一時的に検索できなくなっています。',
        '恐れ入りますが、しばらく時間をおいてから再度お試しいただくか、下記本部事務局までご連絡ください。',
    ],
    'unavailable' => [
        'ただいま会員番号のお問い合わせをご利用いただけません。',
        '恐れ入りますが下記本部事務局までご連絡ください。',
    ],
];
?>
<main id="form2025" class="ng">
<img class="headerLogo" src="<?= h($view->asset($site['logo'])) ?>" alt="<?= h($site['logo_alt']) ?>">
  <h1>会員番号のお問い合わせ結果</h1>

  <div class="infoArea">

    <div class="msgArea">
<?php foreach ($messages[$status] ?? $messages['not_found'] as $line): ?>
      <p><?= h($line) ?></p>
<?php endforeach; ?>
    </div>

<?= $view->render('search/contact') ?>

  </div>

<?= $view->render('search/buttons') ?>

</main>
