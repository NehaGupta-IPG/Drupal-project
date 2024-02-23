<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

/**
 * Resource test base class for config entities.
 *
 * @todo Simplify this in https://www.drupal.org/node/3423459 or https://www.drupal.org/node/3423462.
 */
abstract class ConfigEntityResourceTestBase extends ResourceTestBase {

  /**
   * A list of test methods to skip.
   *
   * @var array
   */
  const SKIP_METHODS = [
    // @todo Remove in https://www.drupal.org/node/3423462: the FieldConfigs using this FieldStorageConfig
    'testRelated',
    // @todo Remove in https://www.drupal.org/node/3423462: the `instances` (FieldConfigs) of this FieldStorageConfig
    'testRelationships',
    // @todo Remove in https://www.drupal.org/node/3423459.
    'testDeleteIndividual',
    'testRevisions',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    if (in_array($this->name(), static::SKIP_METHODS, TRUE)) {
      // Skip before installing Drupal to prevent unnecessary use of resources.
      $this->markTestSkipped("Not yet supported for config entities.");
    }
    parent::setUp();
  }

}
