<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Handler;

use InvalidArgumentException;
use RuntimeException;
use Socket;
use Yiisoft\VarDumper\HandlerInterface;

use function fsockopen;
use function fwrite;
use function get_debug_type;
use function is_resource;
use function is_string;

final class StreamHandler implements HandlerInterface
{
    /**
     * @var callable|null
     */
    private $encoder = null;
    /**
     * @var resource|Socket|null
     */
    private $stream = null;

    /**
     * @var resource|Socket|string|null
     */
    private $uri = null;

    /**
     * @param mixed|resource|string $uri
     */
    public function __construct(
        mixed $uri = 'udp://127.0.0.1:8890'
    ) {
        if (!is_string($uri) && !is_resource($uri) && !$uri instanceof Socket) {
            throw new InvalidArgumentException(
                sprintf(
                    'Argument $uri must be a string or a resource, "%s" given.',
                    get_debug_type($uri)
                )
            );
        }
        $this->uri = $uri;
    }

    /**
     * Encodes {@param $variable} with {@see self::$encoder} and sends the result to the stream.
     */
    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $data = ($this->encoder ?? '\json_encode')($variable);
        if (!is_string($data)) {
            throw new RuntimeException(
                sprintf(
                    'Encoder must return a string, "%s" returned.',
                    get_debug_type($data)
                )
            );
        }

        if (!is_resource($this->stream) && !$this->stream instanceof Socket) {
            $this->initializeStream();
        }

        if ($this->stream instanceof Socket) {
            socket_write($this->stream, $data, strlen($data));
            return;
        }

        if (@fwrite($this->stream, $data) === false) {
            $this->initializeStream();

            if ($this->stream instanceof Socket) {
                socket_write($this->stream, $data, strlen($data));
                return;
            }

            if (@fwrite($this->stream, $data) === false) {
                throw new RuntimeException('Cannot write a stream.');
            }
        }
    }

    public function withEncoder(callable $encoder): HandlerInterface
    {
        $new = clone $this;
        $new->encoder = $encoder;
        return $new;
    }

    private function initializeStream(): void
    {
        if (is_string($this->uri)) {
            if (
                str_starts_with($this->uri, 'udp://') ||
                str_starts_with($this->uri, 'udg://') ||
                str_starts_with($this->uri, 'tcp://') ||
                str_starts_with($this->uri, 'unix://')
            ) {
                $this->stream = fsockopen($this->uri);
            } else {
                $this->stream = fopen($this->uri, 'wb+');
            }
        } else {
            $this->stream = $this->uri;
        }

        if (!is_resource($this->stream) && !$this->stream instanceof Socket) {
            throw new RuntimeException('Cannot initialize a stream.');
        }
    }

    public function __destruct()
    {
        if (!is_string($this->uri) || !is_resource($this->stream)) {
            return;
        }
        fclose($this->stream);
    }
}
