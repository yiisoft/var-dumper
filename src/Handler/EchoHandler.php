<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Handler;

use Yiisoft\VarDumper\HandlerInterface;
use Yiisoft\VarDumper\VarDumper;

final class EchoHandler implements HandlerInterface
{
    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $varDumper = VarDumper::create($variable);
        $output = $varDumper->asString($depth);

        echo $highlight
            ? $this->highlight($output)
            : $output;
    }

    private function highlight(string $string): string
    {
        $result = highlight_string("<?php\n" . $string, true);

        $pattern = PHP_VERSION_ID >= 80300
            ? '~<span style="color: #0000BB">&lt;\\?php\n</span>~'
            : '~<span style="color: #0000BB">&lt;\\?php<br \\/></span>~';

        return preg_replace($pattern, '', $result, 1);
    }
}
