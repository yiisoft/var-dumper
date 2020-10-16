<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

class UseStatementParser
{
    public function fromFile(string $file): array
    {
        $tokens = token_get_all(file_get_contents($file));
        array_shift($tokens);

        $uses = [];
        foreach ($tokens as $i => $token) {
            if (!isset($token[0])) {
                continue;
            }

            if ($token[0] === T_USE) {
                array_push($uses, ...$this->normalizeUse(array_slice($tokens, $i + 1)));
                continue;
            }
        }

        return $uses;
    }

    private function normalizeUse(array $tokens): array
    {
        $commonNamespace = '\\';
        $current = '';
        $uses = [];
        $pendingParenthesisCount = 0;

        foreach ($tokens as $token) {
            if (!isset($token[0])) {
                continue;
            }
            if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR) {
                $current .= $token[1];
                continue;
            }
            if ($token === ',' || $token === ';') {
                if ($current !== '') {
                    $uses[] = $commonNamespace . $current;
                    $current = '';
                }
            }
            if ($token === ';') {
                break;
            }
            if ($token === '{') {
                $pendingParenthesisCount++;
                $commonNamespace .= $current;
                $current = '';
                continue;
            }

            if ($token === '}') {
                $pendingParenthesisCount--;
                if ($pendingParenthesisCount === 0) {
                    $uses[] = $commonNamespace . $current;
                    $commonNamespace = '\\';
                    $current = '';
                }
                continue;
            }
        }

        return $uses;
    }
}
