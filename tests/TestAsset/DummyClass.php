<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

/**
 * CustomDebugInfo serves for the testing of `__debugInfo()` PHP magic method.
 *
 * @see \Yiisoft\VarDumper\Tests\VarDumperTest
 */
final class DummyClass
{
    public int $volume;
    public int $unitPrice;
    public \Closure $params;
    public \Closure $config;
}
