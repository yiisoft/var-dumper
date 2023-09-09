<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Handler;

use RuntimeException;
use Yiisoft\VarDumper\HandlerInterface;

final class StreamHandler implements HandlerInterface
{
    /**
     * @var callable|null
     */
    private $encoder = null;
    /**
     * @var null|resource
     */
    private $stream = null;

    /**
     * @var string|resource|null
     */
    private $uri = null;

    /**
     * @param resource|string|mixed $uri
     */
    public function __construct(
        $uri = 'udp://127.0.0.1:8890'
    ) {
        if (!is_string($uri) && !is_resource($uri)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument $uri must be a string or a resource, "%s" given.',
                    gettype($uri)
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
        $data = ($this->encoder ?? 'json_encode')($variable);
        if (!is_string($data)) {
            throw new RuntimeException(
                sprintf(
                    'Encoder must return a string, %s returned.',
                    gettype($data)
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
        if ($this->stream === null) {
            return false;
        }
        return @fwrite($this->stream, $data) !== false;
    }
}
