<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

use Closure;
use Yiisoft\Arrays\ArrayableInterface;

final class DummyArrayableWithClosure implements ArrayableInterface
{
    private Closure $closure;

    public function __construct()
    {
        $this->closure = static fn (): string => __CLASS__;
    }

    public function fields(): array
    {
        return ['closure'];
    }

    public function extraFields(): array
    {
        return [];
    }

    public function toArray(array $fields = [], array $expand = [], bool $recursive = true): array
    {
        return ['closure' => $this->closure];
    }
}
