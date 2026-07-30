<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\ClosureExporter;
use Yiisoft\VarDumper as V;
use Yiisoft\Yii\Debug as D;

final class ClosureExporterTest extends TestCase
{
    public function testRegular(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(function (int $test): int {
            return 42 + $test;
        });

        $this->assertEquals(
            <<<PHP
            function (int \$test): int {
                return 42 + \$test;
            }
            PHP,
            $output
        );
    }

    public function testStatic(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(static function (int $test): int {
            return 42 + $test;
        });

        $this->assertEquals(
            <<<PHP
            static function (int \$test): int {
                return 42 + \$test;
            }
            PHP,
            $output
        );
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

    public function testShortWithImport(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(fn (V\VarDumper $date) => new DateTimeZone(''));
        $this->assertSame("fn (\Yiisoft\VarDumper\VarDumper \$date) => new \DateTimeZone('')", $output);
    }

    public function testShortWithImportNotFoundClass(): void
    {
        $exporter = new ClosureExporter();

        $output = $exporter->export(static fn (D\Dumper $date) => new DateTimeZone(''));
        $this->assertSame("static fn (\Yiisoft\Yii\Debug\Dumper \$date) => new \DateTimeZone('')", $output);

        $output = $exporter->export(fn (D\A\B\C $date) => new DateTimeZone(''));
        $this->assertSame("fn (\Yiisoft\Yii\Debug\A\B\C \$date) => new \DateTimeZone('')", $output);

        $output = $exporter->export(fn (\E\F\G\H\I\J\K\L\M\N $date) => new DateTimeZone(''));
        $this->assertSame("fn (\E\F\G\H\I\J\K\L\M\N \$date) => new \DateTimeZone('')", $output);
    }

    public function testLongWithExistingImport(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(fn (ClosureExporter $date) => new DateTimeZone(''));
        $this->assertSame("fn (\Yiisoft\VarDumper\ClosureExporter \$date) => new \DateTimeZone('')", $output);
    }

    public function testStaticMethodCallImport(): void
    {
        $exporter = new ClosureExporter();
        $output = $exporter->export(fn (DateTimeZone $date) => DateTimeZone::listAbbreviations());
        $this->assertSame("fn (\DateTimeZone \$date) => \DateTimeZone::listAbbreviations()", $output);
    }
}
