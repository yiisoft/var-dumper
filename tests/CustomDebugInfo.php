<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

/**
 * CustomDebugInfo serves for the testing of `__debugInfo()` PHP magic method.
 *
 * @see VarDumperTest
 */
class CustomDebugInfo
{
    public $volume;
    public $unitPrice;

    /**
     * @see http://php.net/manual/en/language.oop5.magic.php#language.oop5.magic.debuginfo
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'volume' => $this->volume,
            'totalPrice' => $this->volume * $this->unitPrice,
        ];
    }
}
