<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

use Closure;

final class DummyStringableWithClosure implements \Stringable
{
    private Closure $closure;

    public function __construct()
    {
        $this->closure = static fn (): string => self::class;
    }

    public function __toString(): string
    {
        return 'Closure:' . ($this->closure)();
    }
}
