<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\VarDumper\FailedReadFileException;
use Yiisoft\VarDumper\UseStatementParser;

final class UseStatementParserWithZeroErrorReportingTest extends TestCase
{
    private int $errorLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorLevel = error_reporting();
        error_reporting(0);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorLevel);

        parent::tearDown();
    }

    public function testIncorrectFile(): void
    {
        $parser = new UseStatementParser();

        $this->expectException(FailedReadFileException::class);
        $this->expectExceptionMessage('Failed read file "non-exists-file".');
        $parser->fromFile('non-exists-file');
    }
}
