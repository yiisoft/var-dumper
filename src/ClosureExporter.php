<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use Closure;
use ReflectionException;
use ReflectionFunction;

use function array_key_exists;
use function array_slice;
use function in_array;
use function is_array;

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
            return 'function() {/* Error: unable to determine Closure source */}';
        }

        --$start;
        $uses = $this->useStatementParser->fromFile($fileName);

        $source = implode('', array_slice($fileContent, $start, $end - $start));
        $tokens = token_get_all('<?php ' . $source);
        array_shift($tokens);

        $closureTokens = [];
        $pendingParenthesisCount = 0;
        $isShortClosure = false;
        $buffer = '';
        foreach ($tokens as $token) {
            if (!isset($token[0])) {
                continue;
            }
            if (in_array($token[0], [T_FUNCTION, T_FN, T_STATIC], true)) {
                $closureTokens[] = $token[1];
                if (!$isShortClosure && $token[0] === T_FN) {
                    $isShortClosure = true;
                }
                continue;
            }
            if ($closureTokens !== []) {
                $readableToken = $token[1] ?? $token;
                if ($this->isNextTokenIsPartOfNamespace($token)) {
                    $buffer .= $token[1];
                    // HERE we need to match partially because now NS can be a single token in PHP 8
                    if (array_key_exists($buffer, $uses) && !$this->isNextTokenIsPartOfNamespace(next($tokens))) {
                        $readableToken = $uses[$buffer];
                        $buffer = '';
                    }
                }
                if ($token === '{' || $token === '[') {
                    $pendingParenthesisCount++;
                } elseif ($token === '}' || $token === ']') {
                    if ($pendingParenthesisCount === 0) {
                        break;
                    }
                    $pendingParenthesisCount--;
                } elseif ($token === ',' || $token === ';') {
                    if ($pendingParenthesisCount === 0) {
                        break;
                    }
                }
                $closureTokens[] = $readableToken;
            }
        }

        return implode('', $closureTokens);
    }

    private function isNextTokenIsPartOfNamespace($token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR || $token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED) {
            return true;
        }

        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            return $token[0] === T_NAME_RELATIVE;
        }

        return false;
    }
}
