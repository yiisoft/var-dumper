<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

class ClosureExporter
{
    private UseStatementParser $useStatementParser;

    public function __construct()
    {
        $this->useStatementParser = new UseStatementParser();
    }

    public function export(\Closure $closure)
    {
        $reflection = new \ReflectionFunction($closure);

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
                    if (!$this->isNextTokenIsPartOfNamespace(next($tokens)) && array_key_exists($buffer, $uses)) {
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

        return $token[0] === T_STRING || $token[0] === T_NS_SEPARATOR;
    }
}
