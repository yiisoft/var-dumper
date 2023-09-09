<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\Handler\StreamHandler;

final class StreamHandlerTest extends TestCase
{
    /**
     * @requires OS Linux|Darwin
     */
    public function testUnixUDPSocket()
    {
        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);

        $handler = new StreamHandler('udg://' . $path);

        $handler->handle('test', 1);

        $this->assertEquals('"test"', socket_read($socket, 10));
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

    /**
     * @requires OS Linux|Darwin
     */
    public function testReopenStream()
    {
        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);

        $handler = new StreamHandler('udg://' . $path);
        $handler->handle('test', 1);

        socket_close($socket);

        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);
        $handler->handle('test', 1);

        $this->assertEquals('"test"', socket_read($socket, 10));
    }

    public function testFailedToReopenStream()
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);

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

    public function testIncorrectValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument $uri must be a string or a resource, "array" given.');
        new StreamHandler([]);
    }

    public function testIncorrectEncoderReturnType(): void
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);

        $handler = $handler->withEncoder(fn (mixed $variable): int => strlen($variable));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encoder must return a string, "int" returned.');
        $handler->handle('test', 1);
    }

    public function testDestructStreamResource(): void
    {
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream);
        $handler->handle('test', 1);
        unset($handler);

        $this->assertTrue(is_resource($stream));
    }

    public function testDestructStringResource(): void
    {
        $handler = new StreamHandler('php://memory');

        $handler->handle('test', 1);

        $reflection = new \ReflectionObject($handler);
        $resource = $reflection->getProperty('stream')->getValue($handler);

        $this->assertTrue(is_resource($resource));

        $handler->__destruct();

        $this->assertFalse(is_resource($resource));
    }
}
