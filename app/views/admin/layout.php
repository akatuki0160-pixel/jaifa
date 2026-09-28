<?php
/**
 * 管理画面のレイアウト
 *
 * @var Qryno\View $view
 * @var array<string, mixed> $site
 * @var string $content
 * @var string $token
 * @var bool $loggedIn
 */
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no">
<meta name="robots" content="noindex, nofollow">
<title>会員データ管理 | <?= h($site['title']) ?></title>
<link rel="stylesheet" href="<?= h($view->asset('assets/css/reset.css')) ?>">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/style2025.css')) ?>">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/smapho2025.css')) ?>" media="only screen and (max-width:769px)">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/admin.css')) ?>">
</head>

<body>

<main id="form2025" class="admin">
  <h1>会員データ管理</h1>
<?php if ($loggedIn): ?>
  <form class="adminLogout" action="admin.php" method="post">
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <input type="hidden" name="action" value="logout">
    <input type="submit" value="ログアウト" class="formBtn">
  </form>
<?php endif; ?>

<?= $content ?>

</main>

</body>
</html>
