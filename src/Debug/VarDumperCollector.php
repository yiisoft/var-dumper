<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Debug;

use Yiisoft\Yii\Debug\Collector\CollectorTrait;
use Yiisoft\Yii\Debug\Collector\SummaryCollectorInterface;

final class VarDumperCollector implements SummaryCollectorInterface
{
    use CollectorTrait;

    private array $vars = [];

    public function collectVar(mixed $variable, string $line): void
    {
        $this->vars[] = [
            'variable' => $variable,
            'line' => $line,
        ];
    }

    public function getCollected(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        return [
            'var-dumper' => $this->vars,
        ];
    }

    public function getSummary(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        return [
            'var-dumper' => [
                'total' => count($this->vars),
            ],
        ];
    }
}
