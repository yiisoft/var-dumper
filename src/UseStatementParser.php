<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use function defined;

/**
 * UseStatementParser given a PHP file, returns a set of `use` statements from the code.
 */
final class UseStatementParser
{
    /**
     * @param string $file File to read.
     *
     * @return array Use statements data.
     * @psalm-return array<string, string>
     */
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
                $uses = array_merge($uses, $this->normalizeUse(array_slice($tokens, $i + 1)));
                continue;
            }
        }

        return $uses;
    }

    /**
     * Normalizes raw tokens into uniform use statement data.
     *
     * @param array $tokens Raw tokens.
     *
     * @return array Normalized use statement data.
     * @psalm-return array<string, string>
     */
    private function normalizeUse(array $tokens): array
    {
        $commonNamespace = '\\';
        $current = '';
        $uses = [];

        /** @psalm-var array<int, int|string>|string $token */
        foreach ($tokens as $token) {
            if (!isset($token[0])) {
                continue;
            }
            if (
                $token[0] === T_STRING
                || $token[0] === T_NS_SEPARATOR
                || (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED)
                || (defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED)
                || (defined('T_NAME_RELATIVE') && $token[0] === T_NAME_RELATIVE)
            ) {
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
                $commonNamespace .= $current;
                $current = '';
                continue;
            }
            if ($token[0] === T_AS) {
                $current .= '@';
            }
        }

        return $this->replaceAliases($uses);
    }

    /**
     * @param array $uses
     * @psalm-param list<string> $uses
     *
     * @return array<string, string>
     */
    private function replaceAliases(array $uses): array
    {
        $result = [];
        foreach ($uses as $use) {
            $delimiterPosition = strpos($use, '@');
            if ($delimiterPosition !== false) {
                $alias = mb_substr($use, $delimiterPosition + 1);
                $result[$alias] = mb_substr($use, 0, $delimiterPosition);
            } else {
                $part = strrchr($use, '\\');
                $result[$part === false ? $use : substr($part, 1)] = $use;
            }
        }

        return $result;
    }
}
