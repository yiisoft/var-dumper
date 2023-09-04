<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Handler;

use Exception;
use RuntimeException;
use Socket;
use Yiisoft\VarDumper\HandlerInterface;

final class UdpHandler implements HandlerInterface
{
    private ?Socket $socket = null;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 8890,
    ) {
        if (!extension_loaded('sockets')) {
            throw new Exception('The "ext-socket" extension is not installed.');
        }
    }

    /**
     * Sends encode with {@see \json_encode()} function $variable to a UDP socket.
     */
    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $socket = $this->getSocket();

        $data = json_encode($variable);
        if (!socket_sendto($socket, $data, strlen($data), 0, $this->host, $this->port)) {
            throw new RuntimeException(
                sprintf(
                    'Could not send a dump to %s:%d',
                    $this->host,
                    $this->port,
                )
            );
        }
    }

    /**
     * @throws RuntimeException When a connection cannot be opened.
     */
    private function getSocket(): Socket
    {
        if ($this->socket === null) {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if (!$this->socket) {
                throw new RuntimeException('Cannot create a UDP socket connection.');
            }
        }
        return $this->socket;
    }
}
