<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Handler;

use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use RuntimeException;
use stdClass;
use Yiisoft\VarDumper\Handler\StreamHandler;

final class StreamHandlerTest extends TestCase
{
    /**
     * @requires OS Linux|Darwin
     */
    public function testUnixDomainSocketPath(): void
    {
        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);

        $handler = $this->createStreamHandler('udg://' . $path);

        $handler->handle('test', 1);

        $this->assertEquals('"test"', socket_read($socket, 10));
    }

    /**
     * @requires OS Linux|Darwin
     */
    public function testUnixDomainSocket(): void
    {
        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);
        socket_connect($socket, $path);

        $handler = $this->createStreamHandler($socket);

        $handler->handle('test', 1);

        $this->assertEquals('"test"', socket_read($socket, 10));
    }

    public function testInMemoryStream(): void
    {
        $stream = fopen('php://memory', 'wb+');
        $handler = $this->createStreamHandler($stream);

        $handler->handle('test', 1);

        rewind($stream);

        $this->assertEquals('"test"', fread($stream, 255));
    }

    public function testDifferentEncoder(): void
    {
        $stream = fopen('php://memory', 'wb+');
        $handler = $this->createStreamHandler($stream);

        $handler = $handler->withEncoder(fn (mixed $variable): string => (string) strlen($variable));

        $handler->handle('test', 1);

        rewind($stream);

        $this->assertEquals('4', fread($stream, 255));
    }

    /**
     * @requires OS Linux|Darwin
     */
    public function testReopenStream(): void
    {
        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);

        $handler = $this->createStreamHandler('udg://' . $path);
        $handler->handle('test', 1);

        socket_close($socket);

        $path = '/tmp/test.sock';
        @unlink($path);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $path);
        $handler->handle('test', 1);

        $this->assertEquals('"test"', socket_read($socket, 10));
    }

    public function testFailedToReopenStream(): void
    {
        $stream = fopen('php://memory', 'wb+');
        $handler = $this->createStreamHandler($stream);

        $handler->handle('test', 1);

        fclose($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot initialize a stream.');
        $handler->handle('test', 1);
    }

    /**
     * @dataProvider differentVariablesProvider
     */
    public function testDifferentVariables(mixed $variable): void
    {
        $stream = fopen('php://memory', 'wb+');
        $handler = $this->createStreamHandler($stream);

        $handler->handle($variable, 1);

        rewind($stream);

        $this->assertEquals(json_encode($variable), fread($stream, 255));
    }

    public static function differentVariablesProvider(): Generator
    {
        yield 'string' => ['test'];
        yield 'integer' => [1];
        yield 'float' => [1.1];
        yield 'array' => [['test']];
        yield 'object' => [new stdClass()];
        yield 'null' => [null];
    }

    public function testIncorrectValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $message = 'Argument $uri must be either a string, a resource or a Socket instance, "array" given.';
        $this->expectExceptionMessage($message);
        $this->createStreamHandler([]);
    }

    public function testIncorrectEncoderReturnType(): void
    {
        $stream = fopen('php://memory', 'wb+');
        $handler = $this->createStreamHandler($stream);

        $handler = $handler->withEncoder(fn (mixed $variable): int => strlen($variable));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encoder must return a string, "int" returned.');
        $handler->handle('test', 1);
    }

    public function testDestructStreamResource(): void
    {
        $stream = fopen('php://memory', 'wb+');
        $handler = $this->createStreamHandler($stream);
        $handler->handle('test', 1);
        unset($handler);

        $this->assertTrue(is_resource($stream));
    }

    public function testDestructStringResource(): void
    {
        $handler = $this->createStreamHandler('php://memory');

        $handler->handle('test', 1);

        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('stream');
        $property->setAccessible(true);
        $resource = $property->getValue($handler);

        $this->assertTrue(is_resource($resource));

        $handler->__destruct();

        $this->assertFalse(is_resource($resource));
    }

    public function testImmutability(): void
    {
        $handler1 = $this->createStreamHandler('php://memory');
        $handler2 = $handler1->withEncoder(fn (mixed $variable): string => (string) strlen($variable));

        $this->assertInstanceOf(StreamHandler::class, $handler2);
        $this->assertNotSame($handler1, $handler2);
    }

    /**
     * @param mixed|resource|string $stream
     */
    private function createStreamHandler(mixed $stream): StreamHandler
    {
        return new StreamHandler($stream);
    }
}
