<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use Closure;
use ReflectionException;
use ReflectionFunction;

use function array_filter;
use function array_pop;
use function array_shift;
use function array_slice;
use function explode;
use function file;
use function implode;
use function in_array;
use function is_string;
use function strpos;
use function token_get_all;

/**
 * ClosureExporter exports PHP {@see \Closure} as a string containing PHP code.
 *
 * The string is a valid PHP expression that can be evaluated by PHP parser
 * and the evaluation result will give back the closure instance.
 */
final class ClosureExporter
{
    private UseStatementParser $useStatementParser;

    public function __construct()
    {
        $this->useStatementParser = new UseStatementParser();
    }

    /**
     * Export closure as a string containing PHP code.
     *
     * @param Closure $closure Closure to export.
     *
     * @throws ReflectionException
     *
     * @return string String containing PHP code.
     */
    public function export(Closure $closure): string
    {
        $reflection = new ReflectionFunction($closure);

        $fileName = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if ($fileName === false || $start === false || $end === false || ($fileContent = file($fileName)) === false) {
            return 'function () {/* Error: unable to determine Closure source */}';
        }

        --$start;
        $uses = $this->useStatementParser->fromFile($fileName);
        $tokens = token_get_all('<?php ' . implode('', array_slice($fileContent, $start, $end - $start)));
        array_shift($tokens);

        $buffer = '';
        $closureTokens = [];
        $previousUsePart = '';
        $pendingParenthesisCount = 0;

        foreach ($tokens as $token) {
            if (in_array($token[0], [T_FUNCTION, T_FN, T_STATIC], true)) {
                $closureTokens[] = $token[1];
                continue;
            }
            if ($closureTokens !== []) {
                $readableToken = $token[1] ?? $token;
                if ($this->useStatementParser->isPartOfNamespace($token)) {
                    $buffer .= $token[1];
                    if (PHP_VERSION_ID >= 80000 && $buffer !== '\\' && strpos($buffer, '\\') !== false) {
                        $usesKeys = array_filter(explode('\\', $buffer));
                        $buffer = array_pop($usesKeys);
                    }
                    if (!empty($previousUsePart) && $buffer === '\\') {
                        continue;
                    }
                    if (isset($uses[$buffer])) {
                        if ($this->isUseNamespaceAlias($buffer, $uses)) {
                            $previousUsePart = $uses[$buffer];
                            $buffer = '';
                            continue;
                        }
                        $readableToken = (empty($previousUsePart) || strpos($uses[$buffer], $previousUsePart) === false)
                            ? $previousUsePart . $uses[$buffer]
                            : $uses[$buffer]
                        ;
                        $buffer = '';
                        $previousUsePart = '';
                    } elseif (isset($uses[$token[1]])) {
                        $readableToken = $uses[$token[1]];
                        $previousUsePart = '';
                        $buffer = '';
                    }
                }
                if (is_string($token)) {
                    if ($this->isOpenParenthesis($token)) {
                        $pendingParenthesisCount++;
                    } elseif ($this->isCloseParenthesis($token)) {
                        if ($pendingParenthesisCount === 0) {
                            break;
                        }
                        $pendingParenthesisCount--;
                    } elseif ($token === ',' || $token === ';') {
                        if ($pendingParenthesisCount === 0) {
                            break;
                        }
                    }
                }

                $closureTokens[] = $readableToken;
            }
        }

        return implode('', $closureTokens);
    }

    private function isOpenParenthesis(string $value): bool
    {
        return in_array($value, ['{', '[', '(']);
    }

    private function isCloseParenthesis(string $value): bool
    {
        return in_array($value, ['}', ']', ')']);
    }

    private function isUseNamespaceAlias(string $useKey, array $uses): bool
    {
        if (!isset($uses[$useKey])) {
            return false;
        }

        $usesKeys = array_filter(explode('\\', (string) $uses[$useKey]));
        $lastPartUse = array_pop($usesKeys);

        return isset($uses[$lastPartUse]) && $uses[$lastPartUse] !== $uses[$useKey];
    }
}
