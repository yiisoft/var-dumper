<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use Traversable;

final class DummyIteratorAggregateWithClosure implements IteratorAggregate
{
    private Closure $closure;

    public function __construct()
    {
        $this->closure = static fn (): string => __CLASS__;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator(['closure' => $this->closure]);
    }
}
