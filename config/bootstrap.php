<?php

declare(strict_types=1);

use Yiisoft\VarDumper\Debug\DebugHandlerInterfaceProxy;
use Yiisoft\VarDumper\Debug\VarDumperCollector;
use Yiisoft\VarDumper\VarDumper;

/**
 * @var $params array
 */

return [
    static function ($container) use ($params) {
        if (!($params['yiisoft/yii-debug']['enabled'] ?? false)) {
            return;
        }
        if (!$container->has(VarDumperCollector::class)) {
            return;
        }

        VarDumper::setDefaultHandler(
            new DebugHandlerInterfaceProxy(
                VarDumper::getDefaultHandler(),
                $container->get(VarDumperCollector::class),
            ),
        );
    },
];
