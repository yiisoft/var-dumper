<?php

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
    public function testExportIncompleteObject(): void
    {
        $serializedObj = 'O:16:"nonExistingClass":0:{}';
        $incompleteObj = unserialize($serializedObj);
        $exportResult = VarDumper::create($incompleteObj)->export();
        $this->assertStringContainsString('nonExistingClass', $exportResult);
    }

    /**
     * Data provider for [[testExport()]].
     *
     * @return array test data
     */
    public function dataProviderExport(): array
    {
        // Regular :

        $data = [
            [
                'test string',
                "'test string'",
            ],
            'emoji supported' => [
                '🤣',
                "'🤣'",
            ],
            'hex supported' => [
                pack('H*', md5('binary string')),
                var_export(pack('H*', md5('binary string')), true),
            ],
            [
                75,
                75,
            ],
            [
                7.5,
                7.5,
            ],
            [
                null,
                'null',
            ],
            [
                true,
                'true',
            ],
            [
                false,
                'false',
            ],
            [
                [],
                '[]',
            ],
        ];

        // Arrays :

        $var = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $expectedResult = <<<'RESULT'
[
    'key1' => 'value1',
    'key2' => 'value2',
]
RESULT;
        $data[] = [$var, $expectedResult];

        $var = [
            'value1',
            'value2',
        ];
        $expectedResult = <<<'RESULT'
[
    'value1',
    'value2',
]
RESULT;
        $data[] = [$var, $expectedResult];

        $var = [
            'key1' => [
                'subkey1' => 'value2',
            ],
            'key2' => [
                'subkey2' => 'value3',
            ],
        ];
        $expectedResult = <<<'RESULT'
[
    'key1' => [
        'subkey1' => 'value2',
    ],
    'key2' => [
        'subkey2' => 'value3',
    ],
]
RESULT;
        $data[] = [$var, $expectedResult];

        // Objects :

        $var = new stdClass();
        $var->testField = 'Test Value';
        $expectedResult = "unserialize('" . serialize($var) . "')";
        $data[] = [$var, $expectedResult];

        // @formatter:off
        $var = static function () {return 2;};
        // @formatter:on
        $expectedResult = 'function () {return 2;}';
        $data[] = [$var, $expectedResult];

        // @formatter:off
        $var = new stdClass();
        $var->a = static fn () => '123';
        // @formatter:on
        $objectId = spl_object_id($var);

        $expectedResult = <<<DUMP
        'stdClass#{$objectId}
        (
            [a] => fn () => \'123\'
        )'
        DUMP;
        $data[] = [$var, $expectedResult];

        return $data;
    }

    /**
     * @dataProvider dataProviderExport
     *
     * @param mixed $var
     * @param string $expectedResult
     */
    public function testExport($var, $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->export();
        $this->assertEquals($expectedResult, $exportResult);
    }

    /**
     * @dataProvider asPhpStringDataProvider
     *
     * @param mixed $var
     * @param string $expectedResult
     */
    public function testAsPhpString($var, $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->asPhpString();
        $this->assertEquals($expectedResult, $exportResult);
    }

    public function asPhpStringDataProvider(): array
    {
        return [
            [
                "123",
                "'123'",
            ],
            'integer'=>[
                123,
                "123",
            ],
            [
                // @formatter:off
                 static function () {return 2;},
                // @formatter:on
                'static function () {return 2;}',
            ],
            [
                // @formatter:off
                 fn () => 2,
                // @formatter:on
                'fn () => 2',
            ],
            'closure in array' => [
                // @formatter:off
                [fn () => new \DateTimeZone('')],
                // @formatter:on
                "[fn () => new \DateTimeZone('')]",
            ],
            'original class name' => [
                // @formatter:off
                static fn (VarDumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'class alias' => [
                // @formatter:off
                static fn (Dumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
            ],
            'namespace alias' => [
                // @formatter:off
                static fn (VD\VarDumper $date) => new \DateTimeZone(''),
                // @formatter:on
                "static fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')",
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
     * @dataProvider asJsonObjectMap
     *
     * @param mixed $var
     * @param string $expectedResult
     * @group JOM
     */
    public function testAsJsonObjectsMap($var, $expectedResult): void
    {
        $exportResult = VarDumper::create($var)->asJsonObjectsMap();
        $this->assertStringContainsString($expectedResult, $exportResult);
    }

    public function asJsonObjectMap(): array
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
                "stdClass#{$objectId}":{"public::id":1}
                S,
            ],
            [
                $decoratedUser,
                <<<S
                "stdClass#{$decoratedObjectId}":{"public::id":1,"public::name":"Name","public::originalUser":"object@stdClass#{$objectId}"}
                S,
            ],
        ];
    }

    public function testExportObjectFallback(): void
    {
        $var = new stdClass();
        $var->testFunction = static function () {
            return 2;
        };
        $exportResult = VarDumper::create($var)->export();
        $this->assertNotEmpty($exportResult);

        $master = new stdClass();
        $slave = new stdClass();
        $master->slave = $slave;
        $slave->master = $master;
        $master->function = static function () {
            return true;
        };

        $exportResult = VarDumper::create($master)->export();
        $this->assertNotEmpty($exportResult);
    }

    /**
     * @dataProvider jsonDataProvider()
     */
    public function testAsJson($variable, string $result): void
    {
        $output = VarDumper::create($variable)->asJson();
        $this->assertEquals($result, $output);
    }

    public function jsonDataProvider(): array
    {
        $var = new stdClass();
        $var->name = 'Dmitry';
        $binaryString = pack('H*', md5('binary string'));

        $var2 = new stdClass();
        $var2->a = fn () => 1;

        return [
            'object1'=>[
                $var,
                '{"stdClass":{"public::name":"Dmitry"}}',
            ],
            'emoji supported' => [
                ['emoji' => '🤣'],
                '{"emoji":"🤣"}',
            ],
            'closure supported' => [
                $var2,
                '{"stdClass":{"public::a":"fn () => 1"}}',
            ],
            'hex supported' => [
                ['string' => $binaryString],
                '{"string":"ɍ��^��\u00191\u0017�]�-f�"}',
            ],
            [
                fopen('php://input', 'rb'),
                '{"timed_out":false,"blocked":true,"eof":false,"wrapper_type":"PHP","stream_type":"Input","mode":"rb","unread_bytes":0,"seekable":true,"uri":"php:\/\/input"}',
            ],
        ];
    }

    /**
     * @dataProvider asStringDataProvider
     * @param mixed $variable
     * @param string $result
     */
    public function testAsString($variable, string $result): void
    {
        $output = VarDumper::create($variable)->asString();
        $this->assertEquals($result, $output);
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
                'fn () => 1',
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
                'function () {
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
        ];
    }
}
