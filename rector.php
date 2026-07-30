<?php

declare(strict_types=1);

use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\TypeDeclaration\Rector\ClassMethod;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromCreateMockAssignRector;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromDocblockSetUpDefinedRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;

return Rector\Config\RectorConfig::configure()
  ->withBootstrapFiles([
     'vendor/palantirnet/drupal-rector/config/drupal-phpunit-bootstrap-file.php',
  ])
  ->withPaths([
    'core/tests',
    'core/modules/**/tests/src',
  ])
  ->withSkip([
    '*/*ixture*/*',
    '*/Annotation/Doctrine/*',
  ])
#  ->withSets([
#    PHPUnitSetList::PHPUNIT_100,
#    PHPUnitSetList::PHPUNIT_110,
#    PHPUnitSetList::PHPUNIT_120,
#    PHPUnitSetList::PHPUNIT_CODE_QUALITY,
#  ])
  ->withPreparedSets(
    deadCode: true,
    typeDeclarations: true,
  )
#  ->withSkip([
#    TypedPropertyFromCreateMockAssignRector::class,
#    TypedPropertyFromDocblockSetUpDefinedRector::class,
#    TypedPropertyFromAssignsRector::class,
#    TypedPropertyFromStrictConstructorRector::class,
#    TypedPropertyFromStrictSetUpRector::class,
#  ])
  ->withImportNames(
    importDocBlockNames: false,
    importShortClasses: false,
    removeUnusedImports: false,
  );
