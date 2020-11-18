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

    private ?UseStatementParser $useStatementParser = null;

    private bool $beautify = true;

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
        $options = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        if ($prettyPrint) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->asArray($depth), $options);
    }

    public function asJsonObjectsMap(int $depth = 50, bool $prettyPrint = false): string
    {
        $options = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

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
        $output = $var;

        switch (gettype($var)) {
            case 'array':
                if ($depth <= $level) {
                    return 'array [...]';
                }

                $output = [];
                foreach ($var as $key => $value) {
                    if (is_object($value)) {
                        $keyDisplay = spl_object_id($value);
                    } else {
                        $keyDisplay = str_replace("\0", '::', trim($key));
                    }
                    $output[$keyDisplay] = $this->dumpNestedInternal($value, $depth, $level + 1, $objectCollapseLevel);
                }

                break;
            case 'object':
                $className = get_class($var);
                /**
                 * @psalm-var array<string, array<string, array|string>> $output
                 */
                if (($objectCollapseLevel < $level) && (in_array($var, self::$objects, true))) {
                    if ($var instanceof \Closure) {
                        $output = $this->exportClosure($var);
                    } else {
                        $output = 'object@' . $this->getObjectDescription($var);
                    }
                } elseif ($depth <= $level) {
                    $output = $className . ' (...)';
                } else {
                    $output = [];
                    $dumpValues = $this->getVarDumpValuesArray($var);
                    if (empty($dumpValues)) {
                        $output[$className] = '{stateless object}';
                    }
                    foreach ($dumpValues as $key => $value) {
                        $keyDisplay = $this->normalizeProperty($key);
                        /**
                         * @psalm-suppress InvalidArrayOffset
                         */
                        $output[$className][$keyDisplay] = $this->dumpNestedInternal(
                            $value,
                            $depth,
                            $level + 1,
                            $objectCollapseLevel
                        );
                    }
                }

                break;
            case 'resource':
                $output = $this->getResourceDescription($var);
                break;
        }

        return $output;
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
            if (!is_array($object)) {
                continue;
            }
            $className = array_key_first($object);
            $objects[$className . '#' . $index] = $object[$className];
        }
        return $objects;
    }

    /**
     * @param mixed $var variable to be dumped
     * @param int $level depth level
     * @return string
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
                    if ($this->beautify) {
                        $output .= "\n" . $spaces . '    ';
                    }
                    $output .= $this->dumpInternal($key, $depth, 0);
                    $output .= ' => ';
                    $output .= $this->dumpInternal($var[$key], $depth, $level + 1);
                }

                return $this->beautify
                    ? $output . "\n" . $spaces . ']'
                    : $output . ']';
            case 'object':
                if ($var instanceof \Closure) {
                    return $this->exportClosure($var);
                }
                if (in_array($var, self::$objects, true)) {
                    return $this->getObjectDescription($var) . '(...)';
                }

                if ($depth <= $level) {
                    return get_class($var) . '(...)';
                }

                self::$objects[] = $var;
                $spaces = str_repeat(' ', $level * 4);
                $output = $this->getObjectDescription($var) . "\n" . $spaces . '(';
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
     * @return string
     * @throws \ReflectionException
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
                    if ($this->beautify) {
                        $output .= "\n" . $spaces . '    ';
                    }
                    if ($outputKeys) {
                        $output .= $this->exportInternal($key, 0);
                        $output .= ' => ';
                    }
                    $output .= $this->exportInternal($var[$key], $level + 1);
                    if ($this->beautify || next($keys) !== false) {
                        $output .= ',';
                    }
                }
                return $this->beautify
                    ? $output . "\n" . $spaces . ']'
                    : $output . ']';
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
     * @throws \ReflectionException
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
        $uses = $this->getUsesParser()->fromFile($fileName);

        $source = implode('', array_slice(file($fileName), $start, $end - $start));
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
            if (in_array($token[0], $this->exportClosureTokens, true)) {
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

    public function asPhpString(): string
    {
        $this->exportClosureTokens = [T_FUNCTION, T_FN, T_STATIC];
        $this->beautify = false;
        return $this->export();
    }

    private function getUsesParser(): UseStatementParser
    {
        if ($this->useStatementParser === null) {
            $this->useStatementParser = new UseStatementParser();
        }

        return $this->useStatementParser;
    }

    private function isNextTokenIsPartOfNamespace($token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        return $token[0] === T_STRING || $token[0] === T_NS_SEPARATOR;
    }

    private function getObjectDescription(object $object): string
    {
        return get_class($object) . '#' . spl_object_id($object);
    }
}
