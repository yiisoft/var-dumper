<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\Handler\StreamHandler;

final class StreamHandlerTest extends TestCase
{
    public function testUdpSocket()
    {
        $handler = new StreamHandler('udp://127.0.0.1:8890');

        $handler->handle('test', 1);

        $this->expectNotToPerformAssertions();
    }

    public function testInMemoryStream()
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);

        $handler->handle('test', 1);

        rewind($stream);

        $this->assertEquals('"test"', fread($stream, 255));
    }

    public function testDifferentEncoder()
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);

        $handler = $handler->withEncoder(fn (mixed $variable): string => (string) strlen($variable));

        $handler->handle('test', 1);

        rewind($stream);

        $this->assertEquals('4', fread($stream, 255));
    }

    public function testReopenStream()
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);

        $handler = $handler->withEncoder(fn (mixed $variable): string => (string) strlen($variable));

        $handler->handle('test', 1);

        fclose($stream);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot initialize a stream.');
        $handler->handle('test', 1);
    }

    /**
     * @dataProvider differentVariablesProvider
     */
    public function testDifferentVariables(mixed $variable)
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);

        $handler->handle($variable, 1);

        rewind($stream);

        $this->assertEquals(json_encode($variable), fread($stream, 255));
    }

    public static function differentVariablesProvider(): \Generator
    {
        yield 'string' => ['test'];
        yield 'integer' => [1];
        yield 'float' => [1.1];
        yield 'array' => [['test']];
        yield 'object' => [new \stdClass()];
        yield 'null' => [null];
    }
}
