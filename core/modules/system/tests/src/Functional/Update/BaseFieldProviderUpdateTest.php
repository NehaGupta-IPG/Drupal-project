<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for the provider of installed base fields.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class BaseFieldProviderUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/base-field-provider.php',
    ];
  }

  /**
   * Tests that a base field's provider is updated correctly.
   */
  public function testRunUpdates(): void {
    $definitions = \Drupal::service('entity.last_installed_schema.repository')
      ->getLastInstalledFieldStorageDefinitions('entity_test_update');

    $this->assertSame('entity_test_update', $definitions['test_base_field']->getProvider());
    $this->assertSame('entity_test_update', $definitions['name']->getProvider());

    $this->runUpdates();

    $definitions = \Drupal::service('entity.last_installed_schema.repository')
      ->getLastInstalledFieldStorageDefinitions('entity_test_update');

    $this->assertSame('entity_base_field_test', $definitions['test_base_field']->getProvider());
    $this->assertSame('entity_test_update', $definitions['name']->getProvider());
  }

}
