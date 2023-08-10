<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yiisoft\VarDumper\UseStatementParser;

use function chmod;
use function fwrite;
use function stream_get_meta_data;
use function tmpfile;

final class UseStatementParserTest extends TestCase
{
    public function incorrectFileProvider(): array
    {
        return [
            'non-exists-file' => ['non-exists-file'],
            'directory' => [__DIR__],
        ];
    }

    /**
     * @dataProvider incorrectFileProvider
     */
    public function testIncorrectFile(string $file): void
    {
        $parser = new UseStatementParser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("File \"{$file}\" does not exist.");
        $parser->fromFile($file);
    }

    public function testNotReadable(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Skip on OS Windows');
        }

        $parser = new UseStatementParser();
        $file = tmpfile();
        $filename = stream_get_meta_data($file)['uri'];
        chmod($filename, 0333);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("File \"{$filename}\" is not readable.");
        $parser->fromFile($filename);
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

    /**
     * @dataProvider usesProvider
     */
    public function testFromFile(string $file, array $expectedUses): void
    {
        $parser = new UseStatementParser();

        $actualUses = $parser->fromFile($file);

        $this->assertEquals($expectedUses, $actualUses);
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
