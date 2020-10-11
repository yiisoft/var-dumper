<?php

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use StdClass;
use Yiisoft\VarDumper\VarDumper;

/**
 * @group helpers
 */
class VarDumperTest extends TestCase
{
    public function testDumpIncompleteObject(): void
    {
        $serializedObj = 'O:16:"nonExistingClass":0:{}';
        $incompleteObj = unserialize($serializedObj);
        $dumpResult = VarDumper::create($incompleteObj)->asString();
        $this->assertStringContainsString("__PHP_Incomplete_Class#1\n(", $dumpResult);
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
        $obj = new StdClass();
        $this->assertEquals("stdClass#2\n(\n)", VarDumper::create($obj)->asString());

        $obj = new StdClass();
        $obj->name = 'test-name';
        $obj->price = 19;
        $dumpResult = VarDumper::create($obj)->asString();

        $this->assertStringContainsString("stdClass#3\n(", $dumpResult);
        $this->assertStringContainsString("[name] => 'test-name'", $dumpResult);
        $this->assertStringContainsString('[price] => 19', $dumpResult);
    }

    /**
     * Data provider for [[testExport()]].
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

        $var = new StdClass();
        $var->testField = 'Test Value';
        $expectedResult = "unserialize('" . serialize($var) . "')";
        $data[] = [$var, $expectedResult];

        // @formatter:off
        $var = static function () {return 2;};
        // @formatter:on
        $expectedResult = 'function () {return 2;}';
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
        //$this->assertEquals($var, eval('return ' . $exportResult . ';'));
    }

    /**
     * @depends testExport
     */
    public function testExportObjectFallback(): void
    {
        $var = new StdClass();
        $var->testFunction = static function () {
            return 2;
        };
        $exportResult = VarDumper::create($var)->export();
        $this->assertNotEmpty($exportResult);

        $master = new StdClass();
        $slave = new StdClass();
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

    public function testAsJson(): void
    {
        $var = new StdClass();
        $var->name = 'Dmitry';

        $output = VarDumper::create($var)->asJson(50);
        $this->assertEqualsWithoutLE('{"stdClass":{"public::name":"Dmitry"}}', $output);
    }

    /**
     * Asserting two strings equality ignoring line endings.
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
}
