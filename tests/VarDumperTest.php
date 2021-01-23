<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use stdClass;
use Yiisoft\VarDumper as VD;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\VarDumper\VarDumper as Dumper;

/**
 * @group helpers
 */
final class VarDumperTest extends TestCase
{
    /**
     * @dataProvider exportDataProvider
     *
     * @param mixed $var
     * @param string $expectedResult
     */
    public function testExport($var, $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->export();
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
    }

    public function exportDataProvider(): array
    {
        $customDebugInfo = new CustomDebugInfo();
        $customDebugInfo->volume = 10;
        $customDebugInfo->unitPrice = 15;

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

        return [
            'custom debug info' => [
                $customDebugInfo,
                <<<S
                unserialize('O:39:"Yiisoft\\\VarDumper\\\Tests\\\CustomDebugInfo":2:{s:6:"volume";i:10;s:9:"unitPrice";i:15;}')
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
                'function () {
                    return 1;
                }',
            ],
            'static function' => [
                static function () {
                    return 1;
                },
                'static function () {
                    return 1;
                }',
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
                [fn () => new \DateTimeZone('')],
                // @formatter:on
                <<<S
                [
                    fn () => new \DateTimeZone(''),
                ]
                S,
            ],
            'original class name' => [
                // @formatter:off
                static fn (VarDumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'class alias' => [
                // @formatter:off
                fn (Dumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'namespace alias' => [
                // @formatter:off
                fn (VD\VarDumper $date) => new \DateTimeZone(''),
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
            'closure in property supported' => [
                $objectWithClosureInProperty,
                <<<S
                'stdClass#{$objectWithClosureInPropertyId}
                (
                    [a] => fn () => 1
                )'
                S,
            ],
        ];
    }

    /**
     * @dataProvider exportWithoutFormattingDataProvider
     *
     * @param mixed $var
     * @param string $expectedResult
     */
    public function testExportWithoutFormatting($var, $expectedResult): void
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
                'function () {
                    return 1;
                }',
            ],
            'static function' => [
                static function () {
                    return 1;
                },
                'static function () {
                    return 1;
                }',
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
                [fn () => new \DateTimeZone('')],
                // @formatter:on
                "[fn () => new \DateTimeZone('')]",
            ],
            'original class name' => [
                // @formatter:off
                fn (VarDumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'class alias' => [
                // @formatter:off
                fn (Dumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'namespace alias' => [
                // @formatter:off
                fn (VD\VarDumper $date) => new \DateTimeZone(''),
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
     * @dataProvider asJsonObjectMapDataProvider
     *
     * @param mixed $var
     * @param string $expectedResult
     * @group JOM
     */
    public function testAsJsonObjectsMap($var, $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->asArraySummary();
        $this->assertEquals($expectedResult, $exportResult);
    }

    public function asJsonObjectMapDataProvider(): array
    {
        $user = new stdClass();
        $user->id = 1;
        $objectId = spl_object_id($user);

        $decoratedUser = clone $user;
        $decoratedUser->name = 'Name';
        $decoratedUser->originalUser = $user;
        $decoratedObjectId = spl_object_id($decoratedUser);

        return [
            [
                $user,
                <<<S
                [{"stdClass#{$objectId}":{"public \$id":1}}]
                S,
            ],
            [
                $decoratedUser,
                <<<S
                [{"stdClass#{$decoratedObjectId}":{"public \$id":1,"public \$name":"Name","public \$originalUser":"object@stdClass#{$objectId}"}},{"stdClass#{$objectId}":{"public \$id":1}}]
                S,
            ],
        ];
    }

    /**
     * @dataProvider jsonDataProvider()
     */
    public function testAsJson($variable, string $result): void
    {
        $output = VarDumper::create($variable)->asArray();
        $this->assertEqualsWithoutLE($result, $output);
    }

    /**
     * Asserting two strings equality ignoring line endings.
     *
     * @param string $expected
     * @param string $actual
     * @param string $message
     */
    protected function assertEqualsWithoutLE(string $expected, string $actual, string $message = ''): void
    {
        $expected = str_replace(["\r\n", '\r\n'], ["\n", '\n'], $expected);
        $actual = str_replace(["\r\n", '\r\n'], ["\n", '\n'], $actual);
        $this->assertEquals($expected, $actual, $message);
    }

    public function jsonDataProvider(): array
    {
        $objectWithClosureInProperty = new stdClass();
        // @formatter:off
        $objectWithClosureInProperty->a = fn () => 1;
        // @formatter:on
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);
        $objectWithClosureInPropertyClosureId = spl_object_id($objectWithClosureInProperty->a);

        $emptyObject = new stdClass();
        $emptyObjectId = spl_object_id($emptyObject);

        // @formatter:off
        $shortFunctionObject = fn () => 1;
        // @formatter:on
        $shortFunctionObjectId = spl_object_id($shortFunctionObject);

        // @formatter:off
        $staticShortFunctionObject = static fn () => 1;
        // @formatter:on
        $staticShortFunctionObjectId = spl_object_id($staticShortFunctionObject);

        // @formatter:off
        $functionObject = function () {
            return 1;
        };
        // @formatter:on
        $functionObjectId = spl_object_id($functionObject);

        // @formatter:off
        $staticFunctionObject = static function () {
            return 1;
        };
        // @formatter:on
        $staticFunctionObjectId = spl_object_id($staticFunctionObject);

        // @formatter:off
        $closureWithNullCollisionOperatorObject = fn () => $_ENV['var'] ?? null;
        // @formatter:on
        $closureWithNullCollisionOperatorObjectId = spl_object_id($closureWithNullCollisionOperatorObject);

        // @formatter:off
        $closureWithUsualClassNameObject = fn (VarDumper $date) => new \DateTimeZone('');
        // @formatter:on
        $closureWithUsualClassNameObjectId = spl_object_id($closureWithUsualClassNameObject);

        // @formatter:off
        $closureWithAliasedClassNameObject = fn (Dumper $date) => new \DateTimeZone('');
        // @formatter:on
        $closureWithAliasedClassNameObjectId = spl_object_id($closureWithAliasedClassNameObject);

        // @formatter:off
        $closureWithAliasedNamespaceObject = fn (VD\VarDumper $date) => new \DateTimeZone('');
        // @formatter:on
        $closureWithAliasedNamespaceObjectId = spl_object_id($closureWithAliasedNamespaceObject);

        // @formatter:off
        $closureInArrayObject = fn () => new \DateTimeZone('');
        // @formatter:on
        $closureInArrayObjectId = spl_object_id($closureInArrayObject);

        return [
            'empty object' => [
                $emptyObject,
                <<<S
                {"stdClass#{$emptyObjectId}":"{stateless object}"}
                S,
            ],
            'short function' => [
                $shortFunctionObject,
                <<<S
                {"Closure#{$shortFunctionObjectId}":"fn () => 1"}
                S,
            ],
            'short static function' => [
                $staticShortFunctionObject,
                <<<S
                {"Closure#{$staticShortFunctionObjectId}":"static fn () => 1"}
                S,
            ],
            'function' => [
                $functionObject,
                <<<S
                {"Closure#{$functionObjectId}":"function () {\\n            return 1;\\n        }"}
                S,
            ],
            'static function' => [
                $staticFunctionObject,
                <<<S
                {"Closure#{$staticFunctionObjectId}":"static function () {\\n            return 1;\\n        }"}
                S,
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
            'resource' => [
                fopen('php://input', 'rb'),
                '{"timed_out":false,"blocked":true,"eof":false,"wrapper_type":"PHP","stream_type":"Input","mode":"rb","unread_bytes":0,"seekable":true,"uri":"php:\/\/input"}',
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
                '["one","two","three"]',
            ],
            'array of 3 elements, custom keys' => [
                [
                    2 => 'one',
                    'two' => 'two',
                    0 => 'three',
                ],
                '{"2":"one","two":"two","0":"three"}',
            ],
            'closure in array' => [
                // @formatter:off
                [$closureInArrayObject],
                // @formatter:on
                <<<S
                [{"Closure#{$closureInArrayObjectId}":"fn () => new \\\DateTimeZone('')"}]
                S,
            ],
            'original class name' => [
                $closureWithUsualClassNameObject,
                <<<S
                {"Closure#{$closureWithUsualClassNameObjectId}":"fn (\\\Yiisoft\\\VarDumper\\\VarDumper \$date) => new \\\DateTimeZone('')"}
                S,
            ],
            'class alias' => [
                $closureWithAliasedClassNameObject,
                <<<S
                {"Closure#{$closureWithAliasedClassNameObjectId}":"fn (\\\Yiisoft\\\VarDumper\\\VarDumper \$date) => new \\\DateTimeZone('')"}
                S,
            ],
            'namespace alias' => [
                $closureWithAliasedNamespaceObject,
                <<<S
                {"Closure#{$closureWithAliasedNamespaceObjectId}":"fn (\\\Yiisoft\\\VarDumper\\\VarDumper \$date) => new \\\DateTimeZone('')"}
                S,
            ],
            'closure with null-collision operator' => [
                $closureWithNullCollisionOperatorObject,
                <<<S
                {"Closure#{$closureWithNullCollisionOperatorObjectId}":"fn () => \$_ENV['var'] ?? null"}
                S,
            ],
            'utf8 supported' => [
                '🤣',
                '"🤣"',
            ],
            'closure in property supported' => [
                $objectWithClosureInProperty,
                <<<S
                {"stdClass#{$objectWithClosureInPropertyId}":{"public \$a":{"Closure#{$objectWithClosureInPropertyClosureId}":"fn () => 1"}}}
                S,
            ],
            'binary string' => [
                pack('H*', md5('binary string')),
                '"ɍ��^��\u00191\u0017�]�-f�"',
            ],
        ];
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

    public function asStringDataProvider(): array
    {
        $customDebugInfo = new CustomDebugInfo();
        $customDebugInfo->volume = 10;
        $customDebugInfo->unitPrice = 15;
        $customDebugInfoObjectId = spl_object_id($customDebugInfo);

        $incompleteObject = unserialize('O:16:"nonExistingClass":0:{}');
        $incompleteObjectId = spl_object_id($incompleteObject);

        $emptyObject = new stdClass();
        $emptyObjectId = spl_object_id($emptyObject);

        $objectWithClosureInProperty = new stdClass();
        // @formatter:off
        $objectWithClosureInProperty->a = fn () => 1;
        // @formatter:on
        $objectWithClosureInPropertyId = spl_object_id($objectWithClosureInProperty);

        return [
            'custom debug info' => [
                $customDebugInfo,
                <<<S
                Yiisoft\VarDumper\Tests\CustomDebugInfo#{$customDebugInfoObjectId}
                (
                    [volume] => 10
                    [totalPrice] => 150
                )
                S,
            ],
            'incomplete object' => [
                $incompleteObject,
                <<<S
                __PHP_Incomplete_Class#{$incompleteObjectId}
                (
                    [__PHP_Incomplete_Class_Name] => 'nonExistingClass'
                )
                S,
            ],
            'empty object' => [
                $emptyObject,
                <<<S
                stdClass#{$emptyObjectId}
                (
                )
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
                'function () {
                    return 1;
                }',
            ],
            'static function' => [
                static function () {
                    return 1;
                },
                'static function () {
                    return 1;
                }',
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
                '{resource}',
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
                    0 => 'one'
                    1 => 'two'
                    2 => 'three'
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
                    2 => 'one'
                    'two' => 'two'
                    0 => 'three'
                ]
                S,
            ],
            'closure in array' => [
                // @formatter:off
                [fn () => new \DateTimeZone('')],
                // @formatter:on
                <<<S
                [
                    0 => fn () => new \DateTimeZone('')
                ]
                S,
            ],
            'original class name' => [
                // @formatter:off
                static fn (VarDumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'class alias' => [
                // @formatter:off
                fn (Dumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'namespace alias' => [
                // @formatter:off
                fn (VD\VarDumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'closure with null-collision operator' => [
                // @formatter:off
                fn () => $_ENV['var'] ?? null,
                // @formatter:on
                "fn () => \$_ENV['var'] ?? null",
            ],
            'utf8 supported' => [
                '🤣',
                "'🤣'",
            ],
            'closure in property supported' => [
                $objectWithClosureInProperty,
                <<<S
                stdClass#{$objectWithClosureInPropertyId}
                (
                    [a] => fn () => 1
                )
                S,
            ],
        ];
    }
}
