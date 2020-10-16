<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\UseStatementParser;

class UseStatementParserTest extends TestCase
{
    /**
     * @dataProvider usesProvider
     */
    public function testFromFile(string $file, array $expectedUses)
    {
        $parser = new UseStatementParser();

        $actualUses = $parser->fromFile($file);

        $this->assertEquals($expectedUses, $actualUses);
    }

    public function usesProvider(): array
    {
        /**
         * use Yiisoft\Arrays\ArrayHelper;
         * use Yiisoft\Arrays\ArrayHelper, Yiisoft\Arrays\ArraySorter;
         * use Yiisoft\{Arrays\ArrayHelper, Arrays\ArrayableTrait};
         * use Yiisoft\{Arrays\ArrayHelper, Arrays\ArrayableTrait}, Yiisoft\Arrays\ArraySorter;
         */
        return $this->saveExamplesToTemporaryFile([
            [
                'use Yiisoft\Arrays\ArrayHelper;',
                ['\Yiisoft\Arrays\ArrayHelper'],
            ],
            [
                'use Yiisoft\Arrays\ArrayHelper, Yiisoft\Arrays\ArraySorter;',
                [
                    '\Yiisoft\Arrays\ArrayHelper',
                    '\Yiisoft\Arrays\ArraySorter',
                ],
            ],
//            [
//                'use Yiisoft\{Arrays\ArrayHelper, Arrays\ArrayableTrait};',
//                [
//                    '\Yiisoft\Arrays\ArrayHelper',
//                    '\Yiisoft\Arrays\ArrayableTrait',
//                ],
//            ],
        ]);
    }

    public function saveExamplesToTemporaryFile(array $examples): array
    {
        static $handles = [];
        foreach ($examples as &$example) {
            $tmpFile = tmpfile();
            $handles[] = $tmpFile;
            fwrite($tmpFile, '<?php ' . $example[0]);

            $example[0] = stream_get_meta_data($tmpFile)['uri'];
        }

        return $examples;
    }
}
