<?php

/**
 * 会員データ管理（CSV取込）
 */

declare(strict_types=1);

$app = require __DIR__ . '/../app/bootstrap.php';
(new Qryno\AdminController($app))->handle();
