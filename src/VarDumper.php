<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use __PHP_Incomplete_Class;
use Closure;
use Exception;
use IteratorAggregate;
use JsonSerializable;
use ReflectionException;
use Yiisoft\Arrays\ArrayableInterface;

use function array_keys;
use function count;
use function get_class;
use function gettype;
use function highlight_string;
use function method_exists;
use function next;
use function preg_replace;
use function range;
use function spl_object_id;
use function str_repeat;
use function strtr;
use function trim;
use function var_export;

/**
 * VarDumper provides enhanced versions of the PHP functions {@see var_dump()} and {@see var_export()}.
 * It can:
 *
 * - Correctly identify the recursively referenced objects in a complex object structure.
 * - Recursively control depth to avoid indefinite recursive display of some peculiar variables.
 * - Export closures and objects.
 * - Highlight output.
 * - Format output.
 */
final class VarDumper
{
    /**
     * @var mixed Variable to dump.
     */
    private $variable;
    private array $objects = [];

    private static ?ClosureExporter $closureExporter = null;

    /**
     * @param mixed $variable Variable to dump.
     */
    private function __construct($variable)
    {
        $this->variable = $variable;
    }

    /**
     * @param mixed $variable Variable to dump.
     *
     * @return static An instance containing variable to dump.
     */
    public static function create($variable): self
    {
        return new self($variable);
    }

    /**
     * Prints a variable.
     *
     * This method achieves the similar functionality as {@see var_dump()} and {@see print_r()}
     * but is more robust when handling complex objects.
     *
     * @param mixed $variable Variable to be dumped.
     * @param int $depth Maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight Whether the result should be syntax-highlighted.
     */
    public static function dump($variable, int $depth = 10, bool $highlight = true): void
    {
        echo self::create($variable)->asString($depth, $highlight);
    }

    /**
     * Dumps a variable in terms of a string.
     *
     * This method achieves the similar functionality as {@see var_dump()} and {@see print_r()}
     * but is more robust when handling complex objects.
     *
     * @param int $depth Maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight Whether the result should be syntax-highlighted.
     *
     * @return string The string representation of the variable.
     */
    public function asString(int $depth = 10, bool $highlight = false): string
    {
        $output = $this->dumpInternal($this->variable, true, $depth, 0);

        if ($highlight) {
            $result = highlight_string("<?php\n" . $output, true);
            $output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }

        return $output;
    }

    /**
     * Exports a variable as a string containing PHP code.
     *
     * The string is a valid PHP expression that can be evaluated by PHP parser
     * and the evaluation result will give back the variable value.
     *
     * This method is similar to {@see var_export()}. The main difference is that
     * it generates more compact string representation using short array syntax.
     *
     * It also handles closures with {@see ClosureExporter} and objects
     * by using the PHP functions {@see serialize()} and {@see unserialize()}.
     *
     * @param bool $format Whatever to format code.
     *
     * @throws ReflectionException
     *
     * @return string A PHP code representation of the variable.
     */
    public function export(bool $format = true): string
    {
        return $this->exportInternal($this->variable, $format, 0);
    }

    /**
     * @param mixed $var Variable to be dumped.
     * @param bool $format Whatever to format code.
     * @param int $depth Maximum depth.
     * @param int $level Current depth.
     *
     * @throws ReflectionException
     *
     * @return string
     */
    private function dumpInternal($var, bool $format, int $depth, int $level): string
    {
        switch (gettype($var)) {
            case 'resource':
            case 'resource (closed)':
                return '{resource}';
            case 'NULL':
                return 'null';
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

                foreach ($keys as $name) {
                    if ($format) {
                        $output .= "\n" . $spaces . '    ';
                    }
                    $output .= $this->exportVariable($name);
                    $output .= ' => ';
                    $output .= $this->dumpInternal($var[$name], $format, $depth, $level + 1);
                }

                return $format
                    ? $output . "\n" . $spaces . ']'
                    : $output . ']';
            case 'object':
                if ($var instanceof Closure) {
                    return $this->exportClosure($var);
                }

                if ($depth <= $level) {
                    return $this->getObjectDescription($var) . ' (...)';
                }

                $this->objects[] = $var;
                $spaces = str_repeat(' ', $level * 4);
                $output = $this->getObjectDescription($var) . "\n" . $spaces . '(';
                $objectProperties = $this->getObjectProperties($var);

                /** @psalm-var mixed $value */
                foreach ($objectProperties as $name => $value) {
                    $propertyName = strtr(trim((string) $name), "\0", '::');
                    $output .= "\n" . $spaces . "    [$propertyName] => ";
                    $output .= $this->dumpInternal($value, $format, $depth, $level + 1);
                }
                return $output . "\n" . $spaces . ')';
            default:
                return $this->exportVariable($var);
        }
    }

    /**
     * @param mixed $variable Variable to be exported.
     * @param bool $format Whatever to format code.
     * @param int $level Current depth.
     *
     * @throws ReflectionException
     *
     * @return string
     */
    private function exportInternal($variable, bool $format, int $level): string
    {
        switch (gettype($variable)) {
            case 'NULL':
                return 'null';
            case 'array':
                if (empty($variable)) {
                    return '[]';
                }

                $keys = array_keys($variable);
                $outputKeys = ($keys !== range(0, count($variable) - 1));
                $spaces = str_repeat(' ', $level * 4);
                $output = '[';

                foreach ($keys as $key) {
                    if ($format) {
                        $output .= "\n" . $spaces . '    ';
                    }
                    if ($outputKeys) {
                        $output .= $this->exportVariable($key);
                        $output .= ' => ';
                    }
                    $output .= $this->exportInternal($variable[$key], $format, $level + 1);
                    if ($format || next($keys) !== false) {
                        $output .= ',';
                    }
                }

                return $format
                    ? $output . "\n" . $spaces . ']'
                    : $output . ']';
            case 'object':
                if ($variable instanceof Closure) {
                    return $this->exportClosure($variable);
                }

                try {
                    return "unserialize({$this->exportVariable(serialize($variable))})";
                } catch (Exception $e) {
                    // Serialize may fail, for example: if object contains a `\Closure` instance so we use a fallback.
                    if ($variable instanceof ArrayableInterface) {
                        return $this->exportInternal($variable->toArray(), $format, $level);
                    }

                    if ($variable instanceof JsonSerializable) {
                        return $this->exportInternal($variable->jsonSerialize(), $format, $level);
                    }

                    if ($variable instanceof IteratorAggregate) {
                        return $this->exportInternal(iterator_to_array($variable), $format, $level);
                    }

                    if ('__PHP_Incomplete_Class' !== get_class($variable) && method_exists($variable, '__toString')) {
                        return $this->exportVariable($variable->__toString());
                    }

                    return $this->exportVariable(self::create($variable)->asString());
                }
            default:
                return $this->exportVariable($variable);
        }
    }

    /**
     * Exports a {@see \Closure} instance.
     *
     * @param Closure $closure Closure instance.
     *
     * @throws ReflectionException
     *
     * @return string
     */
    private function exportClosure(Closure $closure): string
    {
        if (self::$closureExporter === null) {
            self::$closureExporter = new ClosureExporter();
        }

        return self::$closureExporter->export($closure);
    }

    /**
     * @param mixed $variable
     *
     * @return string
     */
    private function exportVariable($variable): string
    {
        return var_export($variable, true);
    }

    private function getObjectDescription(object $object): string
    {
        return get_class($object) . '#' . spl_object_id($object);
    }

    private function getObjectProperties(object $var): array
    {
        if (!$var instanceof __PHP_Incomplete_Class && method_exists($var, '__debugInfo')) {
            /** @var array $var */
            $var = $var->__debugInfo();
        }

        return (array) $var;
    }
}
