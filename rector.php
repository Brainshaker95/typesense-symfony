<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\PostRector\Rector\UnusedImportRemovingPostRector;
use Rector\Strict\Rector\Ternary\DisallowedShortTernaryRuleFixerRector;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

$finder = new Finder()
    ->files()
    ->in('.')
    ->name('/\.php$/')
    ->ignoreDotFiles(false)
    ->exclude([
        '.cache',
        'var',
        'vendor',
    ])
;

$paths = \array_filter(
    \array_map(
        static fn (SplFileInfo $file): string|false => $file->getRealPath(),
        [...$finder],
    ),
    is_string(...),
);

return RectorConfig::configure()
    ->withRootFiles()
    ->withPaths($paths)
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        deadCode: true,
        earlyReturn: true,
        instanceOf: true,
        naming: true,
        privatization: true,
        typeDeclarations: true,
        strictBooleans: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withSkip([
        CatchExceptionNameMatchingTypeRector::class,
        DisallowedShortTernaryRuleFixerRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,
        NewlineAfterStatementRector::class,
        NewlineBeforeNewAssignSetRector::class,
        RenameForeachValueVariableToMatchExprVariableRector::class,
        RenameParamToMatchTypeRector::class,
        RenameVariableToMatchMethodCallReturnTypeRector::class,
        RenameVariableToMatchNewTypeRector::class,
        UnusedImportRemovingPostRector::class,
    ])
    ->withPhpSets()
    ->withAttributesSets()
    ->withImportNames(
        importNames: false,
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: true,
    )
;
