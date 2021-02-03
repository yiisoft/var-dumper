<?php

declare(strict_types=1);


namespace Yiisoft\VarDumper\Tests;


use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\ClosureExporter;
use Yiisoft\VarDumper\VarDumper;


class ExportTest extends TestCase
{
    public function testExportClosureWithArguments(): void
    {
        $source = [
            ClosureExporter::class => static fn () => new ClosureExporter(),
        ];

        $output = VarDumper::create($source)->export();

        $this->assertStringContainsString('use', $output, 'No imports found. Expected ClosureExporter to be imported.');
    }
}
