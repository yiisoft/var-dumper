<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests\TestAsset;

/**
 * CustomDebugInfo serves for the testing of `__debugInfo()` PHP magic method.
 *
 * @see \Yiisoft\VarDumper\Tests\VarDumperTest
 */
final class DummyDebugInfo
{
    public int $volume;
    public int $unitPrice;

    /**
     * @see https://www.php.net/manual/en/language.oop5.magic.php#object.debuginfo
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'volume' => $this->volume,
            'totalPrice' => $this->volume * $this->unitPrice,
        ];
    }
}
