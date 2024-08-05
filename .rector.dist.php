<?php

declare(strict_types=1);

use Cambis\SilverstripeRector\Set\ValueObject\SilverstripeLevelSetList;
use Cambis\SilverstripeRector\Set\ValueObject\SilverstripeSetList;

/**
 * Example commands:
 * add -n for --dry-run
 * use -h for help docs
 * Standard: ./vendor/bin/rector process -c .rector.dist.php vendor/vendorname/module
 * With no diff: ./vendor/bin/rector process --no-diffs -c .rector.dist.php vendor/vendorname/module
 */

// @var \Rector\Configuration\RectorConfigBuilder $builder
$builder = \Rector\Config\RectorConfig::configure();
return $builder

    ->withPaths([
        'src/',
        'tests/'
    ])

    // skip rules example
    ->withSkip([
        // Example: skip {$string} to sprintf() rector
        // \Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector::class

        // Avoid applying this rule, due to the protected class method -> public subclass method -> protected sub-subclass method issue
        \Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class
    ])

    // register a single rule example
    ->withRules([
        // add specific rules here
    ])

    ->withSets([
        // SilverstripeLevelSetList::UP_TO_SILVERSTRIPE_52,
        // SilverstripeLevelSetList::UP_TO_SILVERSTRIPE_413,
        SilverstripeSetList::CODE_QUALITY
    ])

    // define sets of rules
    ->withPreparedSets(
        //carbon: false,
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        earlyReturn: false,
        instanceOf: true,
        typeDeclarations: true,
        naming: false,
        phpunit: false,
        strictBooleans: true
    )
    ->withPhpSets(
        php83: true
    );
