<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\Handler\CompositeHandler;
use Yiisoft\VarDumper\Tests\Support\InMemoryHandler;

final class CompositeHandlerTest extends TestCase
{
    public function testComposite()
    {
        $compositeHandler = new CompositeHandler([
            $inMemoryHandler1 = new InMemoryHandler(),
            $inMemoryHandler2 = new InMemoryHandler(),
        ]);
        $variable = 'test';

        $compositeHandler->handle($variable, 1, true);

        $this->assertEquals([[$variable, 1, true]], $inMemoryHandler1->getVariables());
        $this->assertEquals($inMemoryHandler1->getVariables(), $inMemoryHandler2->getVariables());
    }
}
