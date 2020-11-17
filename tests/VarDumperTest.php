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
    public function testDumpIncompleteObject(): void
    {
        $serializedObj = 'O:16:"nonExistingClass":0:{}';
        $incompleteObj = unserialize($serializedObj);
        $dumpResult = VarDumper::create($incompleteObj)->asString();
        $objectId = spl_object_id($incompleteObj);

        $this->assertStringContainsString("__PHP_Incomplete_Class#{$objectId}\n(", $dumpResult);
        $this->assertStringContainsString('nonExistingClass', $dumpResult);
    }

    public function testExportIncompleteObject(): void
    {
        $serializedObj = 'O:16:"nonExistingClass":0:{}';
        $incompleteObj = unserialize($serializedObj);
        $exportResult = VarDumper::create($incompleteObj)->export();
        $this->assertStringContainsString('nonExistingClass', $exportResult);
    }

    public function testDumpObject(): void
    {
        $obj = new stdClass();
        $objectId = spl_object_id($obj);
        $this->assertEquals("stdClass#{$objectId}\n(\n)", VarDumper::create($obj)->asString());

        $obj = new stdClass();
        $obj->name = 'test-name';
        $obj->price = 19;
        $dumpResult = VarDumper::create($obj)->asString();
        $objectId = spl_object_id($obj);

        $this->assertStringContainsString("stdClass#{$objectId}\n(", $dumpResult);
        $this->assertStringContainsString("[name] => 'test-name'", $dumpResult);
        $this->assertStringContainsString('[price] => 19', $dumpResult);
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
                var_export('test string', true),
            ],
            'emoji supported' => [
                '🤣',
                var_export('🤣', true),
            ],
            'hex supported' => [
                pack('H*', md5('binary string')),
                var_export(pack('H*', md5('binary string')), true),
            ],
            [
                75,
                var_export(75, true),
            ],
            [
                7.5,
                var_export(7.5, true),
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
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
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
        $this->assertEqualsWithoutLE($expectedResult, $exportResult);
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

    public function asPhpStringDataProvider(): array
    {
        return [
            [
                "123",
                "'123'",
            ],
            [
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

    public function asJsonObjectMap(): array
    {
        $user = new stdClass();
        $user->id = 1;
        $objectId = spl_object_id($user);

        return [
            [
                $user,
                "\"stdClass#{$objectId}\":{\"public::id\":1}",
            ],
        ];
    }

    /**
     * @depends testExport
     */
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
     * @depends testDumpObject
     */
    public function testDumpClassWithCustomDebugInfo(): void
    {
        $object = new CustomDebugInfo();
        $object->volume = 10;
        $object->unitPrice = 15;

        $dumpResult = VarDumper::create($object)->asString();

        $this->assertStringContainsString('totalPrice', $dumpResult);
        $this->assertStringNotContainsString('unitPrice', $dumpResult);
    }

    /**
     * @dataProvider jsonDataProvider()
     */
    public function testAsJson($variable, string $result): void
    {
        $output = VarDumper::create($variable)->asJson();
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
        $expected = str_replace("\r\n", "\n", $expected);
        $actual = str_replace("\r\n", "\n", $actual);
        $this->assertEquals($expected, $actual, $message);
    }

    public function jsonDataProvider(): array
    {
        $var = new stdClass();
        $var->name = 'Dmitry';
        $binaryString = pack('H*', md5('binary string'));

        $var2 = new stdClass();
        $var2->a = fn () => 1;

        return [
            [
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
        ];
    }
}
