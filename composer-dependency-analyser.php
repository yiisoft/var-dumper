<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    // ext-sockets is only suggested, not required; used optionally by StreamHandler.
    ->ignoreErrorsOnExtension('ext-sockets', [ErrorType::SHADOW_DEPENDENCY])
    // Deliberately non-existent classes used to test rendering of unresolvable type hints.
    ->ignoreErrorsOnPath(__DIR__ . '/tests/ClosureExporterTest.php', [ErrorType::UNKNOWN_CLASS]);
