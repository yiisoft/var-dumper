<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

use Closure;
use JsonSerializable;

final class DummyJsonSerializableWithClosure implements JsonSerializable
{
    private Closure $closure;

    public function __construct()
    {
        $this->closure = static fn (): string => __CLASS__;
    }

    public function jsonSerialize(): array
    {
        return ['closure' => $this->closure];
    }
}
