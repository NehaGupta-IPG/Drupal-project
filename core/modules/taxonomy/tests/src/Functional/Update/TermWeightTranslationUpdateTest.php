<?php

namespace Drupal\Tests\taxonomy\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Ensures that term weight field is made translated after update hook.
 *
 * @group taxonomy
 */
class TermWeightTranslationUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that weight field is translated as expected after update hook run.
   */
  public function testUpdate(): void {
    /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
    $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
    $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('taxonomy_term');
    $this->assertEquals($field_storage_definitions['weight']->isTranslatable(), FALSE);

    $this->runUpdates();

    $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
    $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('taxonomy_term');
    $this->assertEquals($field_storage_definitions['weight']->isTranslatable(), TRUE);
  }

}
