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
final class VarDumper
{
    private $variable;
    private static array $objects = [];

    private array $exportClosureTokens = [T_FUNCTION, T_FN];

    private function __construct($variable)
    {
        $this->variable = $variable;
    }

    public static function create($variable): self
    {
        return new self($variable);
    }

    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $variable variable to be dumped
     * @param int $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight whether the result should be syntax-highlighted
     */
    public static function dump($variable, int $depth = 10, bool $highlight = false): void
    {
        echo self::create($variable)->asString($depth, $highlight);
    }

    /**
     * Dumps a variable in terms of a string.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param int $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight whether the result should be syntax-highlighted
     * @return string the string representation of the variable
     */
    public function asString(int $depth = 10, bool $highlight = false): string
    {
        $output = '';
        $output .= $this->dumpInternal($this->variable, $depth, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . $output, true);
            $output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }

        return $output;
    }

    private function asArray(int $depth, int $objectCollapseLevel = 0): array
    {
        $this->buildVarObjectsCache($this->variable, $depth);
        return $this->dumpNestedInternal($this->variable, $depth, 0, $objectCollapseLevel);
    }

    public function asJson(int $depth = 50, bool $prettyPrint = false): string
    {
        $options = JSON_THROW_ON_ERROR;

        if ($prettyPrint) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->asArray($depth), $options);
    }

    public function asJsonObjectsMap(int $depth = 50, bool $prettyPrint = false): string
    {
        $options = JSON_THROW_ON_ERROR;

        if ($prettyPrint) {
            $options |= JSON_PRETTY_PRINT;
        }

        $this->buildVarObjectsCache($this->variable, $depth);

        $backup = $this->variable;
        $this->variable = self::$objects;
        $output = json_encode($this->getObjectsMap($this->asArray($depth, 1)), $options);
        $this->variable = $backup;
        return $output;
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
     * @return string a string representation of the variable
     */
    public function export(): string
    {
        return $this->exportInternal($this->variable, 0);
    }

    private function buildVarObjectsCache($var, int $depth, int $level = 0): void
    {
        if (is_array($var)) {
            if ($depth <= $level) {
                return;
            }
            foreach ($var as $key => $value) {
                $this->buildVarObjectsCache($value, $depth, $level + 1);
            }
        } elseif (is_object($var)) {
            if ($depth <= $level || in_array($var, self::$objects, true)) {
                return;
            }
            self::$objects[] = $var;
            $dumpValues = $this->getVarDumpValuesArray($var);
            foreach ($dumpValues as $key => $value) {
                $this->buildVarObjectsCache($value, $depth, $level + 1);
            }
        }
    }

    private function dumpNestedInternal($var, int $depth, int $level, int $objectCollapseLevel = 0)
    {
        if (is_array($var)) {
            if ($depth <= $level) {
                return 'array [...]';
            }

            $output = [];
            foreach ($var as $key => $value) {
                $keyDisplay = str_replace("\0", '::', trim($key));
                $output[$keyDisplay] = $this->dumpNestedInternal($value, $depth, $level + 1, $objectCollapseLevel);
            }
            return $output;
        }

        if (is_object($var)) {
            $className = get_class($var);
            $output = [];
            if (($objectCollapseLevel < $level) && (($id = array_search($var, self::$objects, true)) !== false)) {
                if ($var instanceof \Closure) {
                    $output = $this->exportClosure($var);
                } else {
                    $classRef = 'object@' . get_class(self::$objects[$id]) . '#' . ($id + 1);
                    $output = $classRef;
                }
            } elseif ($depth <= $level) {
                $output = $className . ' (...)';
            } else {
                $dumpValues = $this->getVarDumpValuesArray($var);
                if (empty($dumpValues)) {
                    $output[$className] = '{stateless object}';
                }
                foreach ($dumpValues as $key => $value) {
                    $keyDisplay = $this->normalizeProperty($key);
                    $output[$className][$keyDisplay] = $this->dumpNestedInternal($value, $depth, $level + 1, $objectCollapseLevel);
                }
            }
            return $output;
        }

        if (is_resource($var)) {
            return $this->getResourceDescription($var);
        }

        return $var;
    }

    private function normalizeProperty(string $property): string
    {
        $property = str_replace("\0", '::', trim($property));

        if (($pos = strpos($property, '*::')) === 0) {
            return 'protected::' . substr($property, 3);
        }

        if (($pos = strpos($property, '::')) !== false) {
            return 'private::' . substr($property, $pos + 2);
        }

        return 'public::' . $property;
    }

    private function getObjectsMap(array $objectsArray): array
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
    private function dumpInternal($var, int $depth, int $level): string
    {
        $type = gettype($var);
        switch ($type) {
            case 'boolean':
                return $var ? 'true' : 'false';
            case 'integer':
            case 'double':
                return (string)$var;
            case 'string':
                return "'" . addslashes($var) . "'";
            case 'resource':
                return '{resource}';
            case 'NULL':
                return 'null';
            case 'unknown type':
                return '{unknown}';
            case 'array':
                if ($depth <= $level) {
                    return '[...]';
                }

                if (empty($var)) {
                    return '[]';
                }

                $output = '';
                $keys = array_keys($var);
                $spaces = str_repeat(' ', $level * 4);
                $output .= '[';
                foreach ($keys as $key) {
                    $output .= "\n" . $spaces . '    ';
                    $output .= $this->dumpInternal($key, $depth, 0);
                    $output .= ' => ';
                    $output .= $this->dumpInternal($var[$key], $depth, $level + 1);
                }
                return $output . "\n" . $spaces . ']';
            case 'object':
                if (($id = array_search($var, self::$objects, true)) !== false) {
                    return get_class($var) . '#' . ($id + 1) . '(...)';
                }

                if ($depth <= $level) {
                    return get_class($var) . '(...)';
                }

                $id = array_push(self::$objects, $var);
                $className = get_class($var);
                $spaces = str_repeat(' ', $level * 4);
                $output = "$className#$id\n" . $spaces . '(';
                $dumpValues = $this->getVarDumpValuesArray($var);
                foreach ($dumpValues as $key => $value) {
                    $keyDisplay = strtr(trim($key), "\0", ':');
                    $output .= "\n" . $spaces . "    [$keyDisplay] => ";
                    $output .= $this->dumpInternal($value, $depth, $level + 1);
                }
                return $output . "\n" . $spaces . ')';
            default:
                return $type;
        }
    }

    private function getVarDumpValuesArray($var): array
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

    private function getResourceDescription($resource)
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
    private function exportInternal($var, int $level): string
    {
        switch (gettype($var)) {
            case 'NULL':
                return 'null';
            case 'array':
                if (empty($var)) {
                    return '[]';
                }

                $keys = array_keys($var);
                $outputKeys = ($keys !== range(0, count($var) - 1));
                $spaces = str_repeat(' ', $level * 4);
                $output = '[';
                foreach ($keys as $key) {
                    $output .= "\n" . $spaces . '    ';
                    if ($outputKeys) {
                        $output .= $this->exportInternal($key, 0);
                        $output .= ' => ';
                    }
                    $output .= $this->exportInternal($var[$key], $level + 1);
                    $output .= ',';
                }
                return $output . "\n" . $spaces . ']';
            case 'object':
                if ($var instanceof \Closure) {
                    return $this->exportClosure($var);
                }

                try {
                    return 'unserialize(' . var_export(serialize($var), true) . ')';
                } catch (\Exception $e) {
                    // serialize may fail, for example: if object contains a `\Closure` instance
                    // so we use a fallback
                    if ($var instanceof ArrayableInterface) {
                        return $this->exportInternal($var->toArray(), $level);
                    }

                    if ($var instanceof \IteratorAggregate) {
                        $varAsArray = [];
                        foreach ($var as $key => $value) {
                            $varAsArray[$key] = $value;
                        }
                        return $this->exportInternal($varAsArray, $level);
                    }

                    if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__toString')) {
                        return var_export($var->__toString(), true);
                    }

                    return var_export(self::create($var)->asString(), true);
                }
            default:
                return var_export($var, true);
        }
    }

    /**
     * Exports a [[Closure]] instance.
     * @param \Closure $closure closure instance.
     * @return string
     */
    private function exportClosure(\Closure $closure): string
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
        $isShortClosure = false;
        foreach ($tokens as $token) {
            if (!isset($token[0])) {
                continue;
            }
            if (in_array($token[0], $this->exportClosureTokens, true)) {
                $closureTokens[] = $token[1];
                if (!$isShortClosure && $token[0] === T_FN) {
                    $isShortClosure = true;
                }
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
        if ($isShortClosure) {
            $closureTokens= $this->cleanShortClosureTokens($closureTokens);
        }

        return implode('', $closureTokens);
    }

    public function asPhpString(): string
    {
        $this->exportClosureTokens = [T_FUNCTION, T_FN, T_STATIC];
        return $this->export();
    }

    private function cleanShortClosureTokens(array $tokens): array
    {
        $count = count($tokens);
        for ($i = $count; $i > 0; $i--) {
            if ($tokens[$i - 1] === ',') {
                return array_slice($tokens, 0, $i - 1);
            }
        }

        return $tokens;
    }
}
