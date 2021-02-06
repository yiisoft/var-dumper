<?php

declare(strict_types=1);

use Yiisoft\VarDumper\VarDumper;

if (!function_exists('d')) {
    /**
     * Prints variables.
     *
     * @param mixed ...$variables Variables to be dumped.
     *
     * @see \Yiisoft\VarDumper\VarDumper::dump()
     *
     * @psalm-suppress MixedAssignment
     */
    function d(...$variables): void
    {
        foreach ($variables as $variable) {
            VarDumper::dump($variable, 10, PHP_SAPI !== 'cli');
        }
    }
}

if (!function_exists('dd')) {
    /**
     * Prints variables and terminate the current script.
     *
     * @param mixed ...$variables Variables to be dumped.
     *
     * @see \Yiisoft\VarDumper\VarDumper::dump()
     *
     * @psalm-suppress MixedAssignment
     */
    function dd(...$variables): void
    {
        foreach ($variables as $variable) {
            VarDumper::dump($variable, 10, PHP_SAPI !== 'cli');
        }
        die();
    }
}
