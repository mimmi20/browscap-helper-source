<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

$config
    // Adjusting scanned paths
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/vendor', isDev: false)
    ->addPathToExclude(__DIR__ . '/vendor/rector/rector')
    ->addPathToExclude(__DIR__ . '/vendor/phpstan/phpstan')
    // applies only to directory scanning, not directly listed files
    ->setFileExtensions(['php'])

    // Ignoring errors in vendor directory
    ->ignoreErrorsOnPath(__DIR__ . '/vendor', [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPath(__DIR__ . '/vendor', [ErrorType::UNKNOWN_FUNCTION])
    ->ignoreErrorsOnPath(__DIR__ . '/vendor', [ErrorType::UNKNOWN_CLASS])
    ->ignoreErrorsOnPath(__DIR__ . '/vendor', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPath(__DIR__ . '/src', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPath(__DIR__ . '/src', [ErrorType::UNKNOWN_FUNCTION])
    ->ignoreErrorsOnPath(__DIR__ . '/src', [ErrorType::UNKNOWN_CLASS])

    // do not complain about some modules
    ->ignoreErrorsOnPackage('mimmi20/coding-standard', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('phpstan/extension-installer', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('phpstan/phpstan-deprecation-rules', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('cbschuld/browser.php', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('endorphin-studio/browser-detector-tests', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('woothee/woothee-testset', [ErrorType::UNUSED_DEPENDENCY])

    // Adjust analysis
    // dev packages are often used only in CI, so this is not enabled by default
    ->enableAnalysisOfUnusedDevDependencies();

return $config;