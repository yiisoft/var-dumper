<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\ClosureExporter;

final class ClosureExporterTest extends TestCase
{
    public function testRegular(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(function (int $test): int {
            return 42 + $test;
        });

        $this->assertEquals('function (int $test): int {
           return 42 + $test;
        })', $output);
    }

    public function testStatic(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(static function (int $test): int {
            return 42 + $test;
        });

        $this->assertEquals('static function (int $test): int {
            return 42 + $test;
        })', $output);
    }

    public function testShort(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(fn (int $test): int => 42 + $test);

        $this->assertEquals('fn (int $test): int => 42 + $test', $output);
    }

    public function testShortReference(): void
    {
        $exporter = new ClosureExporter();
        $fn = fn (int $test): int => 42 + $test;
        $output = $exporter->export($fn);

        $this->assertEquals('fn (int $test): int => 42 + $test', $output);
    }

    public function testShortStatic(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(static fn (int $test): int => 42 + $test);

        $this->assertEquals('static fn (int $test): int => 42 + $test', $output);
    }
}
