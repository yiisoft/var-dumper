<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Yiisoft\CodeStyle\Rector\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php80: true)
    ->withSets([
        SetList::YII_CORE,
    ])
    ->withSkip([
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/tests/UseStatementParserTest.php',
        ],
        ClassConstantToSelfClassRector::class => [
            __DIR__ . '/tests/TestAsset/DummyIteratorAggregateWithClosure.php',
        ],
    ]);
