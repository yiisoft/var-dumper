<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use RuntimeException;

use function array_slice;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;
use function mb_substr;
use function strpos;
use function strrchr;
use function substr;

/**
 * UseStatementParser given a PHP file, returns a set of `use` statements from the code.
 */
final class UseStatementParser
{
    /**
     * @param string $file File to read.
     *
     * @throws RuntimeException if there is a problem reading file.
     *
     * @return array Use statements data.
     * @psalm-return array<string, string>
     */
    public function fromFile(string $file): array
    {
        if (!file_exists($file)) {
            throw new RuntimeException('File "' . $file . '" does not exist.');
        }

        if (!is_readable($file)) {
            throw new RuntimeException('File "' . $file . '" is not readable.');
        }

        $fileContent = file_get_contents($file);

        if ($fileContent === false) {
            throw new RuntimeException('Failed to read file "' . $file . '".');
        }

        $tokens = token_get_all($fileContent);
        array_shift($tokens);
        $uses = [];

        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_USE && isset($tokens[$i + 2]) && TokenHelper::isPartOfNamespace($tokens[$i + 2])) {
                $uses = $uses + $this->normalize(array_slice($tokens, $i + 1));
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
    private function normalize(array $tokens): array
    {
        $commonNamespace = '\\';
        $current = '';
        $uses = [];

        /** @psalm-var array<int, int|string>|string $token */
        foreach ($tokens as $token) {
            if (TokenHelper::isPartOfNamespace($token)) {
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
                continue;
            }

            $part = strrchr($use, '\\');
            $result[$part === false ? $use : substr($part, 1)] = $use;
        }

        return $result;
    }
}
