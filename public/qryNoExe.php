<?php

/**
 * 会員番号のお問い合わせ（検索実行・結果表示）
 */

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
(new Qryno\SearchController($app))->execute();
