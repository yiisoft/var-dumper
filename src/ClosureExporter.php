<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use Closure;
use ReflectionException;
use ReflectionFunction;

use function array_key_exists;
use function array_filter;
use function array_pop;
use function array_shift;
use function array_slice;
use function defined;
use function explode;
use function implode;
use function in_array;
use function is_array;
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
                    if (
                        PHP_VERSION_ID >= 80000
                        && $buffer !== '\\'
                        && strpos($buffer, '\\') !== false
                    ) {
                        $usesKeys = array_filter(explode('\\', $buffer));
                        $buffer = array_pop($usesKeys);
                    }
                    if (
                        array_key_exists($buffer, $uses)
                        && !$this->isNextTokenIsPartOfNamespace(next($tokens))
                    ) {
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

    /**
     * @param mixed $token
     *
     * @return bool
     */
    private function isNextTokenIsPartOfNamespace($token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        return $token[0] === T_STRING
            || $token[0] === T_NS_SEPARATOR
            || (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED)
            || (defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED)
            || (defined('T_NAME_RELATIVE') && $token[0] === T_NAME_RELATIVE);
    }
}
