<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\Handler\EchoHandler;

final class EchoHandlerTest extends TestCase
{
    public function testEcho()
    {
        $handler = new EchoHandler();

        $handler->handle('test', 1);

        $this->expectOutputString("'test'");
    }

    public function testHighlight()
    {
        $handler = new EchoHandler();

        $handler->handle('test', 1, true);

        $this->expectOutputString(
            <<<HTML
<code><span style="color: #000000">\n<span style="color: #0000BB"></span><span style="color: #DD0000">'test'</span>\n</span>\n</code>
HTML
        );
    }
}
