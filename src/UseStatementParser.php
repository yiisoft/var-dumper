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
        $commonNamespace = '\\';
        $tempNamespace = '';
        $uses = [];
        $pendingParenthesisCount = 0;

        foreach ($tokens as $token) {
            if (!isset($token[0])) {
                continue;
            }
            if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR) {
                $commonNamespace .= $token[1];
                continue;
            }
            //            if ($pendingParenthesisCount === 0) {
            //                $commonNamespace .= $token[1];
            //            }
            if ($token === ',' || $token === ';') {
                if ($pendingParenthesisCount === 0) {
                    $uses[] = $commonNamespace;
                    $commonNamespace = '\\';
                }
            }
            if ($token === ';') {
                break;
            }
            //            if ($token[1] === '{') {
            //                $pendingParenthesisCount++;
            //                continue;
            //            }
            //
            //            if ($token[1] === '}') {
            //            $pendingParenthesisCount--;
            //            if ($pendingParenthesisCount === 0) {
            //                    $commonNamespace = '';
            //                }
            //                continue;
            //            }

            //            if ($token[0] === T_STRING) {
            //                $uses[] = $token[1];
            //            }
        }
        //        var_dump($uses);
        //        exit();

        //        var_dump($commonNamespace . implode('', array_filter($uses)));
        //        exit();
        //        return [$commonNamespace];

        if (!empty($uses)) {
            return $uses;
        }

        return [$commonNamespace];
    }
}
