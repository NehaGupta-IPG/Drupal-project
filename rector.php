<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return Rector\Config\RectorConfig::configure()
  ->withBootstrapFiles([
     'vendor/palantirnet/drupal-rector/config/drupal-phpunit-bootstrap-file.php',
  ])
  ->withConfiguredRule(
    ClassPropertyAssignToConstructorPromotionRector::class, [
      ClassPropertyAssignToConstructorPromotionRector::INLINE_PUBLIC => TRUE,
    ]
  )
  ->withParallel(300, 4)
  ->withPaths([
    'composer',
    'core',
  ])
  ->withSkip([
    '*/*ixture*/*',
    '*/Annotation/Doctrine/*',
  ])
  ->withImportNames(
    importDocBlockNames: false,
    importShortClasses: false,
    removeUnusedImports: false,
  );
