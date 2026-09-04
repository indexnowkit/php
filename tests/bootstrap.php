<?php

declare(strict_types=1);

/*
 * Monorepo test bootstrap: composer autoload plus the framework shims the suites need. Yii2 is not autoloadable
 * (class Yii is defined by a file that also registers its own autoloader and the YII_* constants).
 */
require __DIR__ . '/../vendor/autoload.php';

if (is_file(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php')) {
    defined('YII_ENV') or define('YII_ENV', 'test');
    defined('YII_DEBUG') or define('YII_DEBUG', true);
    require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
}
