<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\Handler\EchoHandler;

final class EchoHandlerTest extends TestCase
{
    public function testEcho(): void
    {
        $handler = new EchoHandler();

        $handler->handle('test', 1);

        $this->expectOutputString("'test'");
    }

    public function testHighlight(): void
    {
        $handler = new EchoHandler();

        $handler->handle('test', 1, true);

        if (PHP_VERSION_ID >= 80300) {
            $expected = <<<HTML
                <pre><code style="color: #000000"><span style="color: #DD0000">'test'</span></code></pre>
                HTML;
        } else {
            $expected = <<<HTML
                <code><span style="color: #000000">\n<span style="color: #DD0000">'test'</span>\n</span>\n</code>
                HTML;
        }

        $this->expectOutputString($expected);
    }
}
