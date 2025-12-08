<?php

declare(strict_types=1);

use Rector\TypeDeclaration\Rector\ClassMethod;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromCreateMockAssignRector;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromDocblockSetUpDefinedRector;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromJMSSerializerAttributeTypeRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;

return Rector\Config\RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/core/tests',
  ])
  // First level before param and property types are affected.
  ->withPreparedSets(typeDeclarations: true)
  ->withSkip([
    TypedPropertyFromCreateMockAssignRector::class,
    TypedPropertyFromDocblockSetUpDefinedRector::class,
    TypedPropertyFromJMSSerializerAttributeTypeRector::class,
    TypedPropertyFromAssignsRector::class,
    TypedPropertyFromStrictConstructorRector::class,
    TypedPropertyFromStrictSetUpRector::class,
  ])
  ->withImportNames(
    importDocBlockNames: true,
    importShortClasses: false,
    removeUnusedImports: false,
  );
