<?php

declare(strict_types=1);

use Yiisoft\VarDumper\Debug\VarDumperCollector;

/**
 * @var $params array
 */

return [
    'yiisoft/yii-debug' => [
        'collectors' => [
            VarDumperCollector::class,
        ],
    ],
];
