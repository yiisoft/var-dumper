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
     * @var resource|string|Socket|null
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
     * Encodes with {@see self::$encoder} {@param $variable} and sends the result to the stream.
     */
    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $data = ($this->encoder ?? '\json_encode')($variable);
        if (!is_string($data)) {
            throw new RuntimeException(
                sprintf(
                    'Encoder must return a string, %s returned.',
                    get_debug_type($data)
                )
            );
        }

        $this->initializeStream();

        if (!$this->writeToStream($data)) {
            $this->initializeStream();

            $this->writeToStream($data);
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
            $this->stream = fsockopen($this->uri);
        } else {
            $this->stream = $this->uri;
        }

        if (!is_resource($this->stream)) {
            throw new RuntimeException('Cannot initialize a stream.');
        }
    }

    private function writeToStream(string $data): bool
    {
        if (!is_resource($this->stream)) {
            return false;
        }
        return @fwrite($this->stream, $data) !== false;
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        if (is_string($this->uri)) {
            @unlink($this->uri);
        }
    }
}
