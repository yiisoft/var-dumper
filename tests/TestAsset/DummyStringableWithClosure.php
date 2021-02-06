<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

use Closure;

final class DummyStringableWithClosure
{
    private Closure $closure;

    public function __construct()
    {
        $this->closure = static fn (): string => __CLASS__;
    }

    public function __toString(): string
    {
        return 'Closure:' . ($this->closure)();
    }
}
