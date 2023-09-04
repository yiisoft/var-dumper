<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Handler;

use Yiisoft\VarDumper\HandlerInterface;

final class CompositeHandler implements HandlerInterface
{
    /**
     * @param HandlerInterface[] $handlers
     */
    public function __construct(
        private array $handlers
    ) {
    }

    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($variable, $depth, $highlight);
        }
    }

}
