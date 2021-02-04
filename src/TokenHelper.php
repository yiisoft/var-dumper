<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use function defined;
use function is_array;

/**
 * TokenHelper contains static methods to simplify the manipulation of tokens.
 */
final class TokenHelper
{
    /**
     * Whether the token is part of the namespace.
     *
     * @param array|string $token
     *
     * @return bool
     */
    public static function isPartOfNamespace($token): bool
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
