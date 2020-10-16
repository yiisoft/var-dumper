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
        /**
         * use Yiisoft\Arrays\ArrayHelper;
         * use Yiisoft\Arrays\ArrayHelper, Yiisoft\Arrays\ArraySorter;
         * use Yiisoft\{Arrays\ArrayHelper, Arrays\ArrayableTrait};
         * use Yiisoft\{Arrays\ArrayHelper, Arrays\ArrayableTrait}, Yiisoft\Arrays\ArraySorter;
         */
        $parentNamespace = '\\';
        $useParts = [];
        $pendingParenthesisCount = 0;

        foreach ($tokens as $token) {
            if ($token === ';') {
                break;
            }
            if (!isset($token[0])) {
                continue;
            }
            if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR) {
                $parentNamespace .= $token[1];
                continue;
            }
//            if ($token[1] === '{') {
//                $pendingParenthesisCount++;
//                continue;
//            }
//
//            if ($token[1] === '}') {
//                $pendingParenthesisCount--;
//                if ($pendingParenthesisCount === 0) {
//                    $parentNamespace = '';
//                }
//                continue;
//            }

//            $useParts[] = $token[1];
        }
//        var_dump($useParts);
//        exit();

//        var_dump($parentNamespace . implode('', array_filter($useParts)));
//        exit();
        return [$parentNamespace];
//        return [$parentNamespace . implode('', array_filter($useParts))];
    }
}
