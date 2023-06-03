<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Yiisoft\VarDumper as VD;
use Yiisoft\VarDumper\ClosureExporter;
use Yiisoft\VarDumper\Tests\TestAsset\DummyArrayableWithClosure;
use Yiisoft\VarDumper\Tests\TestAsset\DummyClass;
use Yiisoft\VarDumper\Tests\TestAsset\DummyDebugInfo;
use Yiisoft\VarDumper\Tests\TestAsset\DummyIteratorAggregateWithClosure;
use Yiisoft\VarDumper\Tests\TestAsset\DummyJsonSerializableWithClosure;
use Yiisoft\VarDumper\Tests\TestAsset\DummyStringableWithClosure;
use Yiisoft\VarDumper\Tests\TestAsset\PrivateProperties;
use Yiisoft\VarDumper\UseStatementParser;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\VarDumper\VarDumper as Dumper;

use function fopen;
use function get_class;
use function highlight_string;
use function preg_replace;
use function spl_object_id;
use function str_replace;
use function unserialize;
use function var_export;

final class VarDumperTest extends TestCase
{
    /**
     * @dataProvider exportDataProvider
     *
     * @param mixed $var
     * @param string $expectedResult
     *
     * @throws ReflectionException
     */
    public function testExport($var, string $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->export();
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public function exportDataProvider(): array
    {
        $dummyDebugInfo = new DummyDebugInfo();
        $dummyDebugInfo->volume = 10;
        $dummyDebugInfo->unitPrice = 15;

        $incompleteObject = unserialize('O:16:"nonExistingClass":0:{}');

        $emptyObject = new stdClass();

        $objectWithReferences1 = new stdClass();
        $objectWithReferences2 = new stdClass();
        $objectWithReferences1->object = $objectWithReferences2;
        $objectWithReferences2->object = $objectWithReferences1;

        $objectWithClosureInProperty = new stdClass();
        // @formatter:off
        $objectWithClosureInProperty->a = fn () => 1;
        // @formatter:on
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);

        $dateTime = new DateTime('now');
        $dateTimeInterpretation = $dateTime->format(DateTimeInterface::RFC3339_EXTENDED);
        $dateTimeImmutable = new DateTimeImmutable();
        $dateTimeImmutableInterpretation = $dateTimeImmutable->format(DateTimeInterface::RFC3339_EXTENDED);

        return [
            'DateTime object' => [
                $dateTime,
                <<<S
                new DateTime('$dateTimeInterpretation', new DateTimeZone('UTC'))
                S,
            ],
            'DateTimeImmutable object' => [
                $dateTimeImmutable,
                <<<S
                new DateTimeImmutable('$dateTimeImmutableInterpretation', new DateTimeZone('UTC'))
                S,
            ],
            'incomplete object' => [
                $incompleteObject,
                <<<S
                unserialize('O:16:"nonExistingClass":0:{}')
                S,
            ],
            'empty object' => [
                $emptyObject,
                <<<S
                unserialize('O:8:"stdClass":0:{}')
                S,
            ],
            'short function' => [
                // @formatter:off
                fn () => 1,
                // @formatter:on
                'fn () => 1',
            ],
            'short static function' => [
                // @formatter:off
                static fn () => 1,
                // @formatter:on
                'static fn () => 1',
            ],
            'function' => [
                function () {
                    return 1;
                },
                <<<PHP
                function () {
                    return 1;
                }
                PHP,
            ],
            'static function' => [
                static function () {
                    return 1;
                },
                <<<PHP
                static function () {
                    return 1;
                }
                PHP,
            ],
            'string' => [
                'Hello, Yii!',
                "'Hello, Yii!'",
            ],
            'empty string' => [
                '',
                "''",
            ],
            'null' => [
                null,
                'null',
            ],
            'integer' => [
                1,
                '1',
            ],
            'integer with separator' => [
                1_23_456,
                '123456',
            ],
            'boolean' => [
                true,
                'true',
            ],
            'resource' => [
                fopen('php://input', 'rb'),
                'NULL',
            ],
            'empty array' => [
                [],
                '[]',
            ],
            'array of 3 elements, automatic keys' => [
                [
                    'one',
                    'two',
                    'three',
                ],
                <<<S
                [
                    'one',
                    'two',
                    'three',
                ]
                S,
            ],
            'array of 3 elements, custom keys' => [
                [
                    2 => 'one',
                    'two' => 'two',
                    0 => 'three',
                ],
                <<<S
                [
                    2 => 'one',
                    'two' => 'two',
                    0 => 'three',
                ]
                S,
            ],
            'closure in array' => [
                // @formatter:off
                [fn () => new DateTimeZone('')],
                // @formatter:on
                <<<S
                [
                    fn () => new \DateTimeZone(''),
                ]
                S,
            ],
            'original class name' => [
                // @formatter:off
                static fn (VarDumper $date) => new DateTimeZone(''),
                // @formatter:on
                "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'class alias' => [
                // @formatter:off
                fn (Dumper $date) => new DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'namespace alias' => [
                // @formatter:off
                fn (VD\VarDumper $date) => new DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'closure with null-collision operator' => [
                // @formatter:off
                fn () => $_ENV['var'] ?? null,
                // @formatter:on
                "fn () => \$_ENV['var'] ?? null",
            ],
            'object with references' => [
                $objectWithReferences1,
                <<<S
                unserialize('O:8:"stdClass":1:{s:6:"object";O:8:"stdClass":1:{s:6:"object";r:1;}}')
                S,
            ],
            'utf8 supported' => [
                '🤣',
                "'🤣'",
            ],
        ];
    }

    /**
     * @dataProvider exportWithoutFormattingDataProvider
     *
     * @param mixed $var
     * @param string $expectedResult
     *
     * @throws ReflectionException
     */
    public function testExportWithoutFormatting($var, string $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->export(false);
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public function exportWithoutFormattingDataProvider(): array
    {
        return [
            'short function' => [
                // @formatter:off
                fn () => 1,
                // @formatter:on
                'fn () => 1',
            ],
            'short static function' => [
                // @formatter:off
                static fn () => 1,
                // @formatter:on
                'static fn () => 1',
            ],
            'function' => [
                function () {
                    return 1;
                },
                <<<PHP
                function () {
                    return 1;
                }
                PHP,
            ],
            'static function' => [
                static function () {
                    return 1;
                },
                <<<PHP
                static function () {
                    return 1;
                }
                PHP,
            ],
            'string' => [
                'Hello, Yii!',
                "'Hello, Yii!'",
            ],
            'empty string' => [
                '',
                "''",
            ],
            'null' => [
                null,
                'null',
            ],
            'integer' => [
                1,
                '1',
            ],
            'integer with separator' => [
                1_23_456,
                '123456',
            ],
            'boolean' => [
                true,
                'true',
            ],
            'resource' => [
                fopen('php://input', 'rb'),
                'NULL',
            ],
            'empty array' => [
                [],
                '[]',
            ],
            'array of 3 elements' => [
                [
                    'one',
                    'two',
                    'three',
                ],
                "['one','two','three']",
            ],
            'array of 3 elements, custom keys' => [
                [
                    2 => 'one',
                    'two' => 'two',
                    0 => 'three',
                ],
                "[2 => 'one','two' => 'two',0 => 'three']",
            ],
            'closure in array' => [
                // @formatter:off
                [fn () => new DateTimeZone('')],
                // @formatter:on
                "[fn () => new \DateTimeZone('')]",
            ],
            'original class name' => [
                // @formatter:off
                fn (VarDumper $date) => new DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'class alias' => [
                // @formatter:off
                fn (Dumper $date) => new DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'namespace alias' => [
                // @formatter:off
                fn (VD\VarDumper $date) => new DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'closure with null-collision operator' => [
                // @formatter:off
                fn () => $_ENV['var'] ?? null,
                // @formatter:on
                "fn () => \$_ENV['var'] ?? null",
            ],
        ];
    }

    /**
     * @dataProvider exportWithObjectSerializationFailDataProvider
     *
     * @param object $object
     * @param string $expectedResult
     *
     * @throws ReflectionException
     */
    public function testExportWithObjectSerializationFail(object $object, string $expectedResult): void
    {
        $exportResult = VarDumper::create($object)->export();
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public function exportWithObjectSerializationFailDataProvider(): array
    {
        return [
            'Anonymous-instance' => [
                $object = new class () {
                },
                var_export(VarDumper::create($object)->asString(), true),
            ],
        ];
    }

    /**
     * @dataProvider exportObjectWithClosureDataProvider
     *
     * @param object $object
     * @param string $expectedResult
     *
     * @throws ReflectionException
     */
    public function testExportObjectWithClosure(object $object, string $expectedResult): void
    {
        $exportResult = VarDumper::create($object)->export();
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public function exportObjectWithClosureDataProvider(): array
    {
        $objectWithClosureInProperty = new stdClass();
        $objectWithClosureInProperty->a = fn () => 1;
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);

        return [
            'closure in stdClass property' => [
                $objectWithClosureInProperty,
                <<<S
                'stdClass#{$objectWithClosureInPropertyId}
                (
                    [a] => fn () => 1
                )'
                S,
            ],
            'ArrayableInterface-instance-with-Closure' => [
                $object = new DummyArrayableWithClosure(),
                <<<S
               (static function () {
                   \$class = new \ReflectionClass('Yiisoft\VarDumper\Tests\TestAsset\DummyArrayableWithClosure');
                   \$object = \$class->newInstanceWithoutConstructor();
                   (function () {
                       \$this->closure = static fn (): string => __CLASS__;
                   })->bindTo(\$object, 'Yiisoft\VarDumper\Tests\TestAsset\DummyArrayableWithClosure')();

                   return \$object;
               })()
               S,
            ],
            'JsonSerializable-instance-with-Closure' => [
                $object = new DummyJsonSerializableWithClosure(),
                <<<S
                (static function () {
                    \$class = new \ReflectionClass('Yiisoft\VarDumper\Tests\TestAsset\DummyJsonSerializableWithClosure');
                    \$object = \$class->newInstanceWithoutConstructor();
                    (function () {
                        \$this->closure = static fn (): string => __CLASS__;
                    })->bindTo(\$object, 'Yiisoft\VarDumper\Tests\TestAsset\DummyJsonSerializableWithClosure')();

                    return \$object;
                })()
                S,
            ],
            'IteratorAggregate-instance-with-Closure' => [
                $object = new DummyIteratorAggregateWithClosure(),
                <<<S
                (static function () {
                    \$class = new \ReflectionClass('Yiisoft\VarDumper\Tests\TestAsset\DummyIteratorAggregateWithClosure');
                    \$object = \$class->newInstanceWithoutConstructor();
                    (function () {
                        \$this->closure = static fn (): string => __CLASS__;
                    })->bindTo(\$object, 'Yiisoft\VarDumper\Tests\TestAsset\DummyIteratorAggregateWithClosure')();

                    return \$object;
                })()
                S,
            ],
            'Stringable-instance-with-Closure' => [
                $object = new DummyStringableWithClosure(),
                <<<S
                (static function () {
                    \$class = new \ReflectionClass('Yiisoft\VarDumper\Tests\TestAsset\DummyStringableWithClosure');
                    \$object = \$class->newInstanceWithoutConstructor();
                    (function () {
                        \$this->closure = static fn (): string => __CLASS__;
                    })->bindTo(\$object, 'Yiisoft\VarDumper\Tests\TestAsset\DummyStringableWithClosure')();

                    return \$object;
                })()
                S,
            ],
        ];
    }

    public function testExportWithClosureArray(): void
    {
        $var = [
            ClosureExporter::class => static fn () => new ClosureExporter(),
            UseStatementParser::class => static function ($container) {
                return $container->get(UseStatementParser::class);
            },
        ];

        $exportResult = preg_replace('/\s/', '', VarDumper::create($var)->export());
        $expectedResult = preg_replace(
            '/\s/',
            '',
            <<<S
            [
                'Yiisoft\\\\VarDumper\\\\ClosureExporter' => static fn () => new \Yiisoft\VarDumper\ClosureExporter(),
                'Yiisoft\\\\VarDumper\\\\UseStatementParser' => static function (\$container) {
                    return \$container->get(\Yiisoft\VarDumper\UseStatementParser::class);
                },
            ]
        S
        );

        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public static function exportWithoutObjectSerializationDataProvider(): iterable
    {
        $dummyDebugInfo = new DummyClass();
        $dummyDebugInfo->volume = 10;
        $dummyDebugInfo->unitPrice = 15;

        yield 'custom debug info' => [
            $dummyDebugInfo,
            [],
            <<<S
                (static function () {
                    \$object = new Yiisoft\VarDumper\Tests\TestAsset\DummyClass();
                    (function () {
                        \$this->volume = 10;
                        \$this->unitPrice = 15;
                    })->bindTo(\$object, 'Yiisoft\VarDumper\Tests\TestAsset\DummyClass')();

                    return \$object;
                })()
                S,
        ];

        $params = ['key' => 5];
        $config = ['value' => 5];
        $dummyDebugInfoWithClosure = new DummyClass();
        $dummyDebugInfoWithClosure->volume = 10;
        $dummyDebugInfoWithClosure->params = fn () => $params;
        $dummyDebugInfoWithClosure->config = fn () => $config;
        $dummyDebugInfoWithClosure->unitPrice = 15;

        yield 'custom debug info with use vars' => [
            $dummyDebugInfoWithClosure,
            ['$config', '$params'],
            <<<S
                (static function () use (\$config, \$params) {
                    \$object = new Yiisoft\VarDumper\Tests\TestAsset\DummyClass();
                    (function () use (\$config, \$params) {
                        \$this->volume = 10;
                        \$this->unitPrice = 15;
                        \$this->params = fn () => \$params;
                        \$this->config = fn () => \$config;
                    })->bindTo(\$object, 'Yiisoft\VarDumper\Tests\TestAsset\DummyClass')();

                    return \$object;
                })()
                S,
        ];

        $dateTimeImmutable = new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Moscow'));
        $dateTimeImmutableInterpretation = $dateTimeImmutable->format(DateTimeInterface::RFC3339_EXTENDED);

        yield 'DateTimeImmutable object' => [
            $dateTimeImmutable,
            [],
            <<<S
            new DateTimeImmutable('$dateTimeImmutableInterpretation', new DateTimeZone('Europe/Moscow'))
            S,
        ];

        $dateTime = new DateTime('yesterday', new DateTimeZone('Europe/Berlin'));
        $dateTimeInterpretation = $dateTime->format(DateTimeInterface::RFC3339_EXTENDED);

        yield 'DateTime object' => [
            $dateTime,
            [],
            <<<S
            new DateTime('$dateTimeInterpretation', new DateTimeZone('Europe/Berlin'))
            S,
        ];
    }

    /**
     * @dataProvider exportWithoutObjectSerializationDataProvider
     *
     * @param object $object Object to export.
     * @param array $useVariables Variables to add to closures via use statement.
     * @param string $expectedResult Expected result.
     *
     * @throws ReflectionException
     */
    public function testExportWithoutObjectSerialization(
        object $object,
        array $useVariables,
        string $expectedResult
    ): void {
        $exportResult = VarDumper::create($object)->export(true, $useVariables, false);
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public function testExportClosureWithAnImmutableInstanceOfClosureExporter(): void
    {
        $varDumper1 = VarDumper::create(fn (): int => 1);
        $reflection1 = new ReflectionClass($varDumper1);

        $this->assertSame('fn (): int => 1', $varDumper1->export());

        $closureExporter1 = $reflection1->getStaticPropertyValue('closureExporter');
        $this->assertInstanceOf(ClosureExporter::class, $closureExporter1);
        $this->assertSame(
            $closureExporter1,
            (new ReflectionClass($varDumper1))->getStaticPropertyValue('closureExporter'),
        );

        $varDumper2 = VarDumper::create(fn (): int => 2);
        $reflection2 = new ReflectionClass($varDumper2);
        $closureExporter2 = $reflection2->getStaticPropertyValue('closureExporter');

        $this->assertInstanceOf(ClosureExporter::class, $closureExporter2);
        $this->assertSame('fn (): int => 2', $varDumper2->export());
        $this->assertSame($closureExporter1, $closureExporter2);
    }

    /**
     * @dataProvider asStringDataProvider
     *
     * @param mixed $variable
     * @param string $result
     */
    public function testAsString($variable, string $result): void
    {
        $output = VarDumper::create($variable)->asString();
        $this->assertEqualsWithoutLE($result, $output);
    }

    public static function asStringDataProvider(): iterable
    {
        $dummyDebugInfo = new DummyDebugInfo();
        $dummyDebugInfo->volume = 10;
        $dummyDebugInfo->unitPrice = 15;
        $dummyDebugInfoObjectId = spl_object_id($dummyDebugInfo);

        yield 'custom debug info' => [
            $dummyDebugInfo,
            <<<S
            Yiisoft\VarDumper\Tests\TestAsset\DummyDebugInfo#{$dummyDebugInfoObjectId}
            (
                [volume] => 10
                [totalPrice] => 150
            )
            S,
        ];

        $incompleteObject = unserialize('O:16:"nonExistingClass":0:{}');
        $incompleteObjectId = spl_object_id($incompleteObject);

        yield 'incomplete object' => [
            $incompleteObject,
            <<<S
            __PHP_Incomplete_Class#{$incompleteObjectId}
            (
                [__PHP_Incomplete_Class_Name] => 'nonExistingClass'
            )
            S,
        ];

        $emptyObject = new stdClass();
        $emptyObjectId = spl_object_id($emptyObject);

        yield 'empty object' => [
            $emptyObject,
            <<<S
            stdClass#{$emptyObjectId}
            (
            )
            S,
        ];
        yield 'short function' => [
            // @formatter:off
            fn () => 1,
            // @formatter:on
            'fn () => 1',
        ];
        yield 'short static function' => [
            // @formatter:off
            static fn () => 1,
            // @formatter:on
            'static fn () => 1',
        ];
        yield 'function' => [
            function () {
                return 1;
            },
            <<<PHP
            function () {
                return 1;
            }
            PHP,
        ];
        yield 'static function' => [
            static function () {
                return 1;
            },
            <<<PHP
            static function () {
                return 1;
            }
            PHP,
        ];
        yield 'string' => [
            'Hello, Yii!',
            "'Hello, Yii!'",
        ];
        yield 'empty string' => [
            '',
            "''",
        ];
        yield 'null' => [
            null,
            'null',
        ];
        yield 'integer' => [
            1,
            '1',
        ];
        yield 'integer with separator' => [
            1_23_456,
            '123456',
        ];
        yield 'boolean' => [
            true,
            'true',
        ];
        yield 'resource' => [
            fopen('php://input', 'rb'),
            '{resource}',
        ];
        yield 'empty array' => [
            [],
            '[]',
        ];
        yield 'array of 3 elements, automatic keys' => [
            [
                'one',
                'two',
                'three',
            ],
        <<<S
        [
            0 => 'one'
            1 => 'two'
            2 => 'three'
        ]
        S,
        ];
        yield 'array of 3 elements, custom keys' => [
            [
                2 => 'one',
                'two' => 'two',
                0 => 'three',
            ],
            <<<S
            [
                2 => 'one'
                'two' => 'two'
                0 => 'three'
            ]
            S,
        ];
        yield 'closure in array' => [
            // @formatter:off
            [fn () => new DateTimeZone('')],
            // @formatter:on
            <<<S
            [
                0 => fn () => new \DateTimeZone('')
            ]
            S,
        ];
        yield 'original class name' => [
            // @formatter:off
            static fn (VarDumper $date) => new DateTimeZone(''),
            // @formatter:on
            "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
        ];
        yield 'class alias' => [
            // @formatter:off
            fn (Dumper $date) => new DateTimeZone(''),
            // @formatter:on
            "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
        ];
        yield 'namespace alias' => [
            // @formatter:off
            fn (VD\VarDumper $date) => new DateTimeZone(''),
            // @formatter:on
            "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
        ];
        yield 'closure with null-collision operator' => [
            // @formatter:off
            fn () => $_ENV['var'] ?? null,
            // @formatter:on
            "fn () => \$_ENV['var'] ?? null",
        ];
        yield 'utf8 supported' => [
            '🤣',
            "'🤣'",
        ];

        $objectWithClosureInProperty = new stdClass();
        // @formatter:off
        $objectWithClosureInProperty->a = fn () => 1;
        // @formatter:on
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);


        yield 'closure in property supported' => [
            $objectWithClosureInProperty,
            <<<S
            stdClass#{$objectWithClosureInPropertyId}
            (
                [a] => fn () => 1
            )
            S,
        ];

        $dateTime = new DateTime();
        $dateTimeInterpretation = $dateTime->format(DateTimeInterface::RFC3339_EXTENDED);

        yield 'DateTime' => [
            $dateTime,
            <<<S
            new DateTime('$dateTimeInterpretation', new DateTimeZone('UTC'))
            S,
        ];
    }

    /**
     * @dataProvider asJsonDataProvider
     *
     * @param mixed $variable
     * @param mixed $result
     *
     * @psalm-suppress MixedAssignment
     */
    public function testAsJson($variable, $result): void
    {
        $output = VarDumper::create($variable)->asJson(depth: 3);
        $this->assertEquals($result, $output);
    }

    public static function asJsonDataProvider(): array
    {
        $dummyDebugInfo = new DummyDebugInfo();
        $dummyDebugInfo->volume = 10;
        $dummyDebugInfo->unitPrice = 15;
        $dummyDebugInfoObjectId = spl_object_id($dummyDebugInfo);

        $incompleteObject = unserialize('O:16:"nonExistingClass":0:{}');
        $incompleteObjectId = spl_object_id($incompleteObject);

        $integerPropertyObject = unserialize('O:8:"stdClass":1:{i:5;i:5;}');
        $integerPropertyObjectId = spl_object_id($integerPropertyObject);

        $emptyObject = new stdClass();
        $emptyObjectId = spl_object_id($emptyObject);

        $nestedObject = new stdClass();
        $nestedObject->nested = $nestedObject;
        $nestedObjectId = spl_object_id($nestedObject);

        $objectWithClosureInProperty = new stdClass();
        // @formatter:off
        $objectWithClosureInProperty->a = fn () => 1;
        // @formatter:on
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);

        $objectWithPrivateProperties = new PrivateProperties();
        $objectWithPrivatePropertiesId = spl_object_id($objectWithPrivateProperties);
        $objectWithPrivatePropertiesClass = str_replace('\\', '\\\\', PrivateProperties::class);

        $openedResource = fopen('php://input', 'rb');
        $openedResourceId = get_resource_id($openedResource);

        $closedResource = fopen('php://input', 'rb');
        $closedResourceId = get_resource_id($closedResource);
        fclose($closedResource);

        return [
            'custom debug info' => [
                $dummyDebugInfo,
                <<<JSON
                {
                    "\$__id__\$": "{$dummyDebugInfoObjectId}",
                    "\$__class__\$": "Yiisoft\\\VarDumper\\\Tests\\\TestAsset\\\DummyDebugInfo",
                    "volume": 10,
                    "totalPrice": 150
                }
                JSON,
                        ],
                        'incomplete object' => [
                            $incompleteObject,
                            <<<JSON
                {
                    "\$__id__\$": "{$incompleteObjectId}",
                    "\$__class__\$": "__PHP_Incomplete_Class",
                    "__PHP_Incomplete_Class_Name": "nonExistingClass"
                }
                JSON,
                        ],
                        'integer property object' => [
                            $integerPropertyObject,
                            <<<JSON
                {
                    "\$__id__\$": "{$integerPropertyObjectId}",
                    "\$__class__\$": "stdClass",
                    "5": 5
                }
                JSON,
                        ],
                        'empty object' => [
                            $emptyObject,
                            <<<JSON
                {
                    "\$__id__\$": "{$emptyObjectId}",
                    "\$__class__\$": "stdClass"
                }
                JSON,
                        ],
                        'short function' => [
                            // @formatter:off
                            fn () => 1,
                            // @formatter:on
                            '"fn () => 1"',
                        ],
                        'short static function' => [
                            // @formatter:off
                            static fn () => 1,
                            // @formatter:on
                            '"static fn () => 1"',
                        ],
                        'function' => [
                            function () {
                                return 1;
                            },
                            <<<JSON
                "function () {\\n    return 1;\\n}"
                JSON,
                        ],
                        'static function' => [
                            static function () {
                                return 1;
                            },
                <<<JSON
                "static function () {\\n    return 1;\\n}"
                JSON,
                        ],
                        'string' => [
                            'Hello, Yii!',
                            '"Hello, Yii!"',
                        ],
                        'empty string' => [
                            '',
                            '""',
                        ],
                        'null' => [
                            null,
                            'null',
                        ],
                        'integer' => [
                            1,
                            '1',
                        ],
                        'integer with separator' => [
                            1_23_456,
                            '123456',
                        ],
                        'boolean' => [
                            true,
                            'true',
                        ],
                        'opened resource' => [
                            $openedResource,
                            <<<JSON
                {
                    "\$__type__\$": "resource",
                    "id": {$openedResourceId},
                    "type": "stream",
                    "closed": false
                }
                JSON,
                        ],
                        'closed resource' => [
                            $closedResource,
                            <<<JSON
                {
                    "\$__type__\$": "resource",
                    "id": {$closedResourceId},
                    "type": "Unknown",
                    "closed": true
                }
                JSON,
                        ],
                        'empty array' => [
                            [],
                            '[]',
                        ],
                        'array of 3 elements, automatic keys' => [
                            [
                                'one',
                                'two',
                                'three',
                            ],
                            <<<JSON
                [
                    "one",
                    "two",
                    "three"
                ]
                JSON,
                        ],
                        'array of 3 elements, custom keys' => [
                            [
                                2 => 'one',
                                'two' => 'two',
                                0 => 'three',
                            ],
                            <<<JSON
                {
                    "2": "one",
                    "two": "two",
                    "0": "three"
                }
                JSON,
                        ],
                        'closure in array' => [
                            // @formatter:off
                            [fn () => new DateTimeZone('')],
                            // @formatter:on
                            <<<JSON
                [
                    "fn () => new \\\\DateTimeZone('')"
                ]
                JSON,
                        ],
                        'original class name' => [
                            // @formatter:off
                            static fn (VarDumper $date) => new DateTimeZone(''),
                            // @formatter:on
                            '"static fn (\\\\Yiisoft\\\\VarDumper\\\\VarDumper $date) => new \\\\DateTimeZone(\'\')"',
                        ],
                        'class alias' => [
                            // @formatter:off
                            fn (Dumper $date) => new DateTimeZone(''),
                            // @formatter:on
                            '"fn (\\\\Yiisoft\\\\VarDumper\\\\VarDumper $date) => new \\\\DateTimeZone(\'\')"',
                        ],
                        'namespace alias' => [
                            // @formatter:off
                            fn (VD\VarDumper $date) => new DateTimeZone(''),
                            // @formatter:on
                            '"fn (\\\\Yiisoft\\\\VarDumper\\\\VarDumper $date) => new \\\\DateTimeZone(\'\')"',
                        ],
                        'closure with null-collision operator' => [
                            // @formatter:off
                            fn () => $_ENV['var'] ?? null,
                            // @formatter:on
                            '"fn () => $_ENV[\'var\'] ?? null"',
                        ],
                        'utf8 supported' => [
                            '🤣',
                            '"\ud83e\udd23"',
                        ],
                        'closure in property supported' => [
                            $objectWithClosureInProperty,
                            <<<JSON
                {
                    "\$__id__\$": "{$objectWithClosureInPropertyId}",
                    "\$__class__\$": "stdClass",
                    "a": "fn () => 1"
                }
                JSON,
                        ],
                        'private properties supported' => [
                            $objectWithPrivateProperties,
                            <<<JSON
                {
                    "\$__id__\$": "{$objectWithPrivatePropertiesId}",
                    "\$__class__\$": "{$objectWithPrivatePropertiesClass}",
                    "age": 0,
                    "names": [
                        "first",
                        "last"
                    ]
                }
                JSON,
                        ],
                        'nested properties limit' => [
                            $nestedObject,
                            <<<JSON
                {
                    "\$__id__\$": "{$nestedObjectId}",
                    "\$__class__\$": "stdClass",
                    "nested": {
                        "\$__id__\$": "{$nestedObjectId}",
                        "\$__class__\$": "stdClass",
                        "nested": {
                            "\$__id__\$": "{$nestedObjectId}",
                            "\$__class__\$": "stdClass",
                            "nested": {
                                "\$__type__\$": "object",
                                "\$__id__\$": "{$nestedObjectId}",
                                "\$__class__\$": "stdClass",
                                "\$__depth_limit_exceeded__\$": true
                            }
                        }
                    }
                }
                JSON,
                        ],
                        'nested array limit' => [
                            [
                                [
                                    [
                                        [
                                            [],
                                        ],
                                    ],
                                ],
                            ],
                            <<<JSON
                [
                    [
                        [
                            {
                                "\$__type__\$": "array",
                                "\$__depth_limit_exceeded__\$": true
                            }
                        ]
                    ]
                ]
                JSON,
                        ],
                    ];
                }

    /**
     * @dataProvider asPrimitivesDataProvider
     *
     * @param mixed $variable
     * @param mixed $result
     *
     * @psalm-suppress MixedAssignment
     */
    public function testAsPrimitives($variable, $result): void
    {
        $output = VarDumper::create($variable)->asPrimitives(depth: 3);
        $this->assertEquals($result, $output);
    }

    public static function asPrimitivesDataProvider(): iterable
    {
        $dummyDebugInfo = new DummyDebugInfo();
        $dummyDebugInfo->volume = 10;
        $dummyDebugInfo->unitPrice = 15;
        $dummyDebugInfoObjectId = spl_object_id($dummyDebugInfo);

        $incompleteObject = unserialize('O:16:"nonExistingClass":0:{}');
        $incompleteObjectId = spl_object_id($incompleteObject);

        $integerPropertyObject = unserialize('O:8:"stdClass":1:{i:5;i:5;}');
        $integerPropertyObjectId = spl_object_id($integerPropertyObject);

        $emptyObject = new stdClass();
        $emptyObjectId = spl_object_id($emptyObject);

        $nestedObject = new stdClass();
        $nestedObject->nested = $nestedObject;
        $nestedObjectId = spl_object_id($nestedObject);

        $objectWithClosureInProperty = new stdClass();
        // @formatter:off
        $objectWithClosureInProperty->a = fn () => 1;
        // @formatter:on
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);

        $objectWithPrivateProperties = new PrivateProperties();
        $objectWithPrivatePropertiesId = spl_object_id($objectWithPrivateProperties);
        $objectWithPrivatePropertiesClass = PrivateProperties::class;

        $openedResource = fopen('php://input', 'rb');
        $openedResourceId = get_resource_id($openedResource);

        $closedResource = fopen('php://input', 'rb');
        $closedResourceId = get_resource_id($closedResource);
        fclose($closedResource);

        yield 'custom debug info' => [
            $dummyDebugInfo,
            [
                '$__id__$' => $dummyDebugInfoObjectId,
                '$__class__$' => DummyDebugInfo::class,
                'volume' => 10,
                'totalPrice' => 150,
            ],
        ];
        yield 'incomplete object' => [
            $incompleteObject,
            [
                '$__id__$' => $incompleteObjectId,
                '$__class__$' => '__PHP_Incomplete_Class',
                '__PHP_Incomplete_Class_Name' => 'nonExistingClass',
            ],
        ];
        yield 'integer property object' => [
            $integerPropertyObject,
            [
                '$__id__$' => $integerPropertyObjectId,
                '$__class__$' => stdClass::class,
                '5' => 5,
            ],
        ];
        yield 'empty object' => [
            $emptyObject,
            [
                '$__id__$' => $emptyObjectId,
                '$__class__$' => stdClass::class,
            ],
        ];
        yield 'short function' => [
            // @formatter:off
                fn () => 1,
                // @formatter:on
            'fn () => 1',
        ];
        yield 'short static function' => [
            // @formatter:off
                static fn () => 1,
                // @formatter:on
            'static fn () => 1',
        ];
        yield 'function' => [
            function () {
                return 1;
            },
            <<<PHP
            function () {
                return 1;
            }
            PHP,
        ];
        yield 'static function' => [
            static function () {
                return 1;
            },
            <<<PHP
            static function () {
                return 1;
            }
            PHP,
        ];
        yield 'string' => [
            'Hello, Yii!',
            'Hello, Yii!',
        ];
        yield 'empty string' => [
            '',
            '',
        ];
        yield 'null' => [
            null,
            null,
        ];
        yield 'integer' => [
            1,
            1,
        ];
        yield 'integer with separator' => [
            1_23_456,
            123456,
        ];
        yield 'boolean' => [
            true,
            true,
        ];
        yield 'opened resource' => [
            $openedResource,
            [
                '$__type__$' => 'resource',
                'id' => $openedResourceId,
                'type' => 'stream',
                'closed' => false,
            ],
        ];
        yield 'closed resource' => [
            $closedResource,
            [
                '$__type__$' => 'resource',
                'id' => $closedResourceId,
                'type' => 'Unknown',
                'closed' => true,
            ],
        ];
        yield 'empty array' => [
            [],
            [],
        ];
        yield 'array of 3 elements, automatic keys' => [
            [
                'one',
                'two',
                'three',
            ],
            [
                'one',
                'two',
                'three',
            ],
        ];
        yield 'array of 3 elements, custom keys' => [
            [
                2 => 'one',
                'two' => 'two',
                0 => 'three',
            ],
            [
                2 => 'one',
                'two' => 'two',
                0 => 'three',
            ],
        ];
        yield 'closure in array' => [
            // @formatter:off
                [fn () => new DateTimeZone('')],
                ["fn () => new \DateTimeZone('')"],
        ];
       yield      'original class name' => [
                // @formatter:off
                static fn (VarDumper $date) => new DateTimeZone(''),
                // @formatter:on
           'static fn (\Yiisoft\\VarDumper\VarDumper $date) => new \DateTimeZone(\'\')',
       ];
        yield 'class alias' => [
            // @formatter:off
                fn (Dumper $date) => new DateTimeZone(''),
                // @formatter:on
            'fn (\Yiisoft\VarDumper\VarDumper $date) => new \DateTimeZone(\'\')',
        ];
        yield 'namespace alias' => [
            // @formatter:off
                fn (VD\VarDumper $date) => new DateTimeZone(''),
                // @formatter:on
            'fn (\Yiisoft\VarDumper\VarDumper $date) => new \DateTimeZone(\'\')',
        ];
        yield 'closure with null-collision operator' => [
            // @formatter:off
                fn () => $_ENV['var'] ?? null,
                // @formatter:on
            'fn () => $_ENV[\'var\'] ?? null',
        ];
        yield 'utf8 supported' => [
            '🤣',
            '🤣',
        ];
        yield 'closure in property supported' => [
            $objectWithClosureInProperty,
            [
                '$__id__$' => $objectWithClosureInPropertyId,
                '$__class__$' => stdClass::class,
                'a' => 'fn () => 1',
            ],
        ];
        yield 'private properties supported' => [
            $objectWithPrivateProperties,
            [
                '$__id__$' => "$objectWithPrivatePropertiesId",
                '$__class__$' => "$objectWithPrivatePropertiesClass",
                'age' => 0,
                'names' => [
                    'first',
                    'last',
                ],
            ],
        ];
        yield 'nested properties limit' => [
            $nestedObject,
            [
                '$__id__$' => "$nestedObjectId",
                '$__class__$' => stdClass::class,
                'nested' => [
                    '$__id__$' => "$nestedObjectId",
                    '$__class__$' => stdClass::class,
                    'nested' => [
                        '$__id__$' => "$nestedObjectId",
                        '$__class__$' => stdClass::class,
                        'nested' => [
                            '$__id__$' => "$nestedObjectId",
                            '$__class__$' => stdClass::class,
                            '$__type__$' => 'object',
                            '$__depth_limit_exceeded__$' => true,
                        ],
                    ],
                ],
            ],
        ];
        yield 'nested array limit' => [
            [
                [
                    [
                        [
                            [],
                        ],
                    ],
                ],
            ],
            [
                [
                    [
                        [
                            '$__type__$' => 'array',
                            '$__depth_limit_exceeded__$' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testDFunction(): void
    {
        d($variable = 'content');
        $this->expectOutputString("'{$variable}'" . PHP_EOL);
    }

    public function testDFunctionWithMultipleVariables(): void
    {
        d([], 123, true);
        $this->expectOutputString('[]' . PHP_EOL . '123' . PHP_EOL . 'true' . PHP_EOL);
    }

    public function testDumpWithHighlight(): void
    {
        $var = 'content';
        $result = highlight_string("<?php\n'{$var}'", true);
        $output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        VarDumper::dump($var);
        $this->expectOutputString($output);
    }

    public function testDumpWithOutHighlight(): void
    {
        $var = 'content';
        VarDumper::dump($var, 10, false);
        $this->expectOutputString("'{$var}'");
    }

    public function testDumpWithoutDepthForArray(): void
    {
        VarDumper::dump(['content'], 0, false);
        $this->expectOutputString('[...]');
    }

    public function testDumpWithoutDepthForObject(): void
    {
        $object = new DummyDebugInfo();
        VarDumper::dump($object, 0, false);
        $this->expectOutputString(get_class($object) . '#' . spl_object_id($object) . ' (...)');
    }

    /**
     * Asserting two strings equality ignoring line endings.
     *
     * @param string $expected
     * @param string $actual
     * @param string $message
     */
    private function assertEqualsWithoutLE(string $expected, string $actual, string $message = ''): void
    {
        $expected = str_replace(["\r\n", '\r\n'], ["\n", '\n'], $expected);
        $actual = str_replace(["\r\n", '\r\n'], ["\n", '\n'], $actual);
        $this->assertEquals($expected, $actual, $message);
    }
}
