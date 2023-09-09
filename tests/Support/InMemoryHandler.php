<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\Support;

use Yiisoft\VarDumper\HandlerInterface;

final class InMemoryHandler implements HandlerInterface
{
    private array $variables;

    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $this->variables[] = [$variable, $depth, $highlight];
    }

    public function getVariables(): array
    {
        return $this->variables;
    }
}
