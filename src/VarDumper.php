<?php

namespace Yiisoft\VarDumper;

use Yiisoft\Arrays\ArrayableInterface;

/**
 * VarDumper is intended to replace the PHP functions var_dump and print_r.
 * It can correctly identify the recursively referenced objects in a complex
 * object structure. It also has a recursive depth control to avoid indefinite
 * recursive display of some peculiar variables.
 *
 * VarDumper can be used as follows,
 *
 * ```php
 * VarDumper::dump($var);
 *
 */
class VarDumper
{
    private static $objects = [];
    private static $output;
    private static $depth;


    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param int $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight whether the result should be syntax-highlighted
     */
    public static function dump($var, int $depth = 10, bool $highlight = false): void
    {
        echo static::dumpAsString($var, $depth, $highlight);
    }

    /**
     * Dumps a variable in terms of a string.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param int $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight whether the result should be syntax-highlighted
     * @return string the string representation of the variable
     */
    public static function dumpAsString($var, int $depth = 10, bool $highlight = false): string
    {
        self::$output = '';
        self::$depth = $depth;
        self::dumpInternal($var, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . self::$output, true);
            self::$output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }

        return self::$output;
    }

    public static function dumpAsJson($var, int $depth = 50): string
    {
        self::$depth = $depth;
        self::buildVarObjectsCache($var);

        return json_encode(self::dumpNestedInternal($var, 0));
    }

    public static function dumpObjectsAsJson(): string
    {
        $objects = self::$objects;
        $json = self::dumpNestedInternal($objects, 0, 1);

        return json_encode(self::getObjectsMap($json));
    }

    /**
     * Exports a variable as a string representation.
     *
     * The string is a valid PHP expression that can be evaluated by PHP parser
     * and the evaluation result will give back the variable value.
     *
     * This method is similar to `var_export()`. The main difference is that
     * it generates more compact string representation using short array syntax.
     *
     * It also handles objects by using the PHP functions serialize() and unserialize().
     *
     * PHP 5.4 or above is required to parse the exported value.
     *
     * @param mixed $var the variable to be exported.
     * @return string a string representation of the variable
     */
    public static function export($var): string
    {
        self::$output = '';
        self::exportInternal($var, 0);
        return self::$output;
    }

    private static function buildVarObjectsCache($var, int $level = 0): void
    {
        if (is_array($var)) {
            if (self::$depth <= $level) {
                return;
            }
            foreach ($var as $key => $value) {
                self::buildVarObjectsCache($value, $level + 1);
            }
        } elseif (is_object($var)) {
            if ((array_search($var, self::$objects, true) !== false) || (self::$depth <= $level)) {
                return;
            }
            array_push(self::$objects, $var);
            if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__debugInfo')) {
                $dumpValues = $var->__debugInfo();
                if (!is_array($dumpValues)) {
                    throw new \Exception('__debugInfo() must return an array');
                }
            } else {
                $dumpValues = (array)$var;
            }
            foreach ($dumpValues as $key => $value) {
                self::buildVarObjectsCache($value, $level + 1);
            }
        }
    }

    private static function dumpNestedInternal($var, int $level, int $objectCollapseLevel = 0)
    {
        $output = [];
        if (is_array($var)) {
            if (self::$depth <= $level) {
                $output = ['@array' => '[...]'];
            } else {
                foreach ($var as $key => $value) {
                    $keyDisplay = str_replace("\0", '::', trim($key));
                    $output[$keyDisplay] = self::dumpNestedInternal($value, $level + 1, $objectCollapseLevel);
                }
            }
        } elseif (is_object($var)) {
            $className = get_class($var);
            if (($objectCollapseLevel < $level) && (($id = array_search($var, self::$objects, true)) !== false)) {
                $classRef = get_class(self::$objects[$id]) . '#' . ($id + 1);
                $output[$className] = ['@object' => $classRef];
            } elseif (self::$depth <= $level) {
                $output[$className] = ['@object' => '(...)'];
            } else {
                $dumpValues = self::getVarDumpValuesArray($var);
                if (empty($dumpValues)) {
                    $output[$className] = '{stateless object}';
                }
                foreach ($dumpValues as $key => $value) {
                    $keyDisplay = str_replace("\0", '::', trim($key));
                    $output[$className][$keyDisplay] = self::dumpNestedInternal($value, $level + 1, $objectCollapseLevel);
                }
            }
        } elseif (is_resource($var)) {
            $output = self::getResourceDescription($var);
        } else {
            $output = $var;
        }

        return $output;
    }

    private static function getObjectsMap(array $objectsArray): array
    {
        $objects = [];
        foreach ($objectsArray as $index => $object) {
            $className = array_key_first($object);
            $objects[$className . '#' . ($index + 1)] = $object[$className];
        }
        return $objects;
    }

    /**
     * @param mixed $var variable to be dumped
     * @param int $level depth level
     */
    private static function dumpInternal($var, int $level): void
    {
        switch (gettype($var)) {
            case 'boolean':
                self::$output .= $var ? 'true' : 'false';
                break;
            case 'integer':
                self::$output .= (string)$var;
                break;
            case 'double':
                self::$output .= (string)$var;
                break;
            case 'string':
                self::$output .= "'" . addslashes($var) . "'";
                break;
            case 'resource':
                self::$output .= '{resource}';
                break;
            case 'NULL':
                self::$output .= 'null';
                break;
            case 'unknown type':
                self::$output .= '{unknown}';
                break;
            case 'array':
                if (self::$depth <= $level) {
                    self::$output .= '[...]';
                } elseif (empty($var)) {
                    self::$output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$output .= '[';
                    foreach ($keys as $key) {
                        self::$output .= "\n" . $spaces . '    ';
                        self::dumpInternal($key, 0);
                        self::$output .= ' => ';
                        self::dumpInternal($var[$key], $level + 1);
                    }
                    self::$output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
                if (($id = array_search($var, self::$objects, true)) !== false) {
                    self::$output .= get_class($var) . '#' . ($id + 1) . '(...)';
                } elseif (self::$depth <= $level) {
                    self::$output .= get_class($var) . '(...)';
                } else {
                    $id = array_push(self::$objects, $var);
                    $className = get_class($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$output .= "$className#$id\n" . $spaces . '(';
                    $dumpValues = self::getVarDumpValuesArray($var);
                    foreach ($dumpValues as $key => $value) {
                        $keyDisplay = strtr(trim($key), "\0", ':');
                        self::$output .= "\n" . $spaces . "    [$keyDisplay] => ";
                        self::dumpInternal($value, $level + 1);
                    }
                    self::$output .= "\n" . $spaces . ')';
                }
                break;
        }
    }

    private static function getVarDumpValuesArray($var): array
    {
        if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__debugInfo')) {
            $dumpValues = $var->__debugInfo();
            if (!is_array($dumpValues)) {
                throw new \Exception('__debugInfo() must return an array');
            }
            return $dumpValues;
        }

        return (array)$var;
    }

    private static function getResourceDescription($resource)
    {
        $type = get_resource_type($resource);
        if ($type === 'stream') {
            $desc = stream_get_meta_data($resource);
        } else {
            $desc = '{resource}';
        }

        return $desc;
    }

    /**
     * @param mixed $var variable to be exported
     * @param int $level depth level
     */
    private static function exportInternal($var, int $level): void
    {
        switch (gettype($var)) {
            case 'NULL':
                self::$output .= 'null';
                break;
            case 'array':
                if (empty($var)) {
                    self::$output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $outputKeys = ($keys !== range(0, count($var) - 1));
                    $spaces = str_repeat(' ', $level * 4);
                    self::$output .= '[';
                    foreach ($keys as $key) {
                        self::$output .= "\n" . $spaces . '    ';
                        if ($outputKeys) {
                            self::exportInternal($key, 0);
                            self::$output .= ' => ';
                        }
                        self::exportInternal($var[$key], $level + 1);
                        self::$output .= ',';
                    }
                    self::$output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
                if ($var instanceof \Closure) {
                    self::$output .= self::exportClosure($var);
                } else {
                    try {
                        $output = 'unserialize(' . var_export(serialize($var), true) . ')';
                    } catch (\Exception $e) {
                        // serialize may fail, for example: if object contains a `\Closure` instance
                        // so we use a fallback
                        if ($var instanceof ArrayableInterface) {
                            self::exportInternal($var->toArray(), $level);
                            return;
                        }

                        if ($var instanceof \IteratorAggregate) {
                            $varAsArray = [];
                            foreach ($var as $key => $value) {
                                $varAsArray[$key] = $value;
                            }
                            self::exportInternal($varAsArray, $level);
                            return;
                        }

                        if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__toString')) {
                            $output = var_export($var->__toString(), true);
                        } else {
                            $outputBackup = self::$output;
                            $output = var_export(self::dumpAsString($var), true);
                            self::$output = $outputBackup;
                        }
                    }
                    self::$output .= $output;
                }
                break;
            default:
                self::$output .= var_export($var, true);
        }
    }

    /**
     * Exports a [[Closure]] instance.
     * @param \Closure $closure closure instance.
     * @return string
     */
    private static function exportClosure(\Closure $closure): string
    {
        $reflection = new \ReflectionFunction($closure);

        $fileName = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if ($fileName === false || $start === false || $end === false) {
            return 'function() {/* Error: unable to determine Closure source */}';
        }

        --$start;

        $source = implode("\n", array_slice(file($fileName), $start, $end - $start));
        $tokens = token_get_all('<?php ' . $source);
        array_shift($tokens);

        $closureTokens = [];
        $pendingParenthesisCount = 0;
        foreach ($tokens as $token) {
            if (isset($token[0]) && $token[0] === T_FUNCTION) {
                $closureTokens[] = $token[1];
                continue;
            }
            if ($closureTokens !== []) {
                $closureTokens[] = $token[1] ?? $token;
                if ($token === '}') {
                    $pendingParenthesisCount--;
                    if ($pendingParenthesisCount === 0) {
                        break;
                    }
                } elseif ($token === '{') {
                    $pendingParenthesisCount++;
                }
            }
        }

        return implode('', $closureTokens);
    }
}
