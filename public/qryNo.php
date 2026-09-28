<?php

/**
 * 会員番号のお問い合わせ（入力画面）
 */

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
(new Qryno\SearchController($app))->showForm();
