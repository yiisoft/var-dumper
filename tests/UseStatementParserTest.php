<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\UseStatementParser;

final class UseStatementParserTest extends TestCase
{
    /**
     * @dataProvider usesProvider
     */
    public function testFromFile(string $file, array $expectedUses): void
    {
        $parser = new UseStatementParser();

        $actualUses = $parser->fromFile($file);

        $this->assertEquals($expectedUses, $actualUses);
    }

    public function usesProvider(): array
    {
        return $this->saveExamplesToTemporaryFile([
            [
                'use Yiisoft\Arrays\ArrayHelper as alias;',
                ['alias' => '\Yiisoft\Arrays\ArrayHelper'],
            ],
            [
                'use Yiisoft\Arrays\ArrayHelper as Helper, Yiisoft\Arrays\ArraySorter as Sorter;',
                [
                    'Helper' => '\Yiisoft\Arrays\ArrayHelper',
                    'Sorter' => '\Yiisoft\Arrays\ArraySorter',
                ],
            ],
            [
                'use Yiisoft\Arrays\ArrayHelper;',
                ['ArrayHelper' => '\Yiisoft\Arrays\ArrayHelper'],
            ],
            [
                'use Yiisoft\Arrays\ArrayHelper, Yiisoft\Arrays\ArraySorter;',
                [
                    'ArrayHelper' => '\Yiisoft\Arrays\ArrayHelper',
                    'ArraySorter' => '\Yiisoft\Arrays\ArraySorter',
                ],
            ],
            [
                'use Yiisoft\{Arrays\ArrayHelper, Arrays\ArrayableTrait};',
                [
                    'ArrayHelper' => '\Yiisoft\Arrays\ArrayHelper',
                    'ArrayableTrait' => '\Yiisoft\Arrays\ArrayableTrait',
                ],
            ],
        ]);
    }

    private function saveExamplesToTemporaryFile(array $examples): array
    {
        // Needed for tests. usesProvider provides temporary file that contains code from provider.
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
