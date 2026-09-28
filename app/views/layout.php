<?php
/**
 * 共通レイアウト
 *
 * @var Qryno\View $view
 * @var array<string, mixed> $site
 * @var string $content
 */
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no">
<meta name="robots" content="noindex, nofollow">
<title><?= h($site['title']) ?></title>
<link rel="stylesheet" href="<?= h($view->asset('assets/css/reset.css')) ?>">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/style2025.css')) ?>">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/smapho2025.css')) ?>" media="only screen and (max-width:769px)">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/tablet2025.css')) ?>" media="only screen and (min-width:770px) and (max-width:1013px)">
<link rel="stylesheet" href="<?= h($view->asset('assets/css/app.css')) ?>">
</head>

<body>

<?= $content ?>

</body>
</html>
