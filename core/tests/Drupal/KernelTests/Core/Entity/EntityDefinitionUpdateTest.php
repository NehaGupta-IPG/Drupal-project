<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\EntityDefinitionUpdateManager;
use Drupal\Tests\system\Functional\Entity\Traits\EntityDefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests EntityDefinitionUpdateManager functionality.
 */
#[CoversClass(EntityDefinitionUpdateManager::class)]
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class EntityDefinitionUpdateTest extends EntityKernelTestBase {

  use EntityDefinitionTestTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test_update', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityDefinitionUpdateManager = $this->container->get('entity.definition_update_manager');
    $this->database = $this->container->get('database');
  }

  /**
   * Tests that new entity type definitions are correctly handled.
   */
  public function testNewEntityType(): void {
    $entity_type_id = 'entity_test_new';
    $schema = $this->database->schema();

    // Check that the "entity_test_new" is not defined.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $this->assertFalse(isset($entity_types[$entity_type_id]), 'The "entity_test_new" entity type does not exist.');
    $this->assertFalse($schema->tableExists($entity_type_id), 'Schema for the "entity_test_new" entity type does not exist.');

    // Check that the "entity_test_new" is now defined and the related schema
    // has been created.
    $this->enableNewEntityType();
    $entity_types = $this->entityTypeManager->getDefinitions();
    $this->assertTrue(isset($entity_types[$entity_type_id]), 'The "entity_test_new" entity type exists.');
    $this->assertTrue($schema->tableExists($entity_type_id), 'Schema for the "entity_test_new" entity type has been created.');
  }

  /**
   * Tests installing an additional base field while installing an entity type.
   *
   * @legacy-covers ::installFieldableEntityType
   */
  public function testInstallAdditionalBaseFieldDuringFieldableEntityTypeInstallation(): void {
    $entity_type = clone $this->entityTypeManager->getDefinition('entity_test_update');
    $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('entity_test_update');

    // Enable the creation of a new base field during the installation of a
    // fieldable entity type.
    $this->state->set('entity_test_update.install_new_base_field_during_create', TRUE);

    // Install the entity type and check that the additional base field was also
    // installed.
    $this->entityDefinitionUpdateManager->installFieldableEntityType($entity_type, $field_storage_definitions);

    // Check whether the 'new_base_field' field has been installed correctly.
    $field_storage_definition = $this->entityDefinitionUpdateManager->getFieldStorageDefinition('new_base_field', 'entity_test_update');
    $this->assertNotNull($field_storage_definition);
  }

  /**
   * Tests get entity types.
   */
  public function testGetEntityTypes(): void {
    $entity_type_definitions = $this->entityDefinitionUpdateManager->getEntityTypes();

    // Ensure that we have at least one entity type to check below.
    $this->assertGreaterThanOrEqual(1, count($entity_type_definitions));

    foreach ($entity_type_definitions as $entity_type_id => $entity_type) {
      $this->assertEquals($this->entityDefinitionUpdateManager->getEntityType($entity_type_id), $entity_type);
    }
  }

  /**
   * Tests purging a field with a broken state.
   */
  public function testBundleFieldPurgeWithBrokenState(): void {
    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('entity_test_update');
    $schema_handler = $this->database->schema();

    // Add the bundle field and run the update.
    $this->addBundleField();
    $this->applyEntityUpdates();

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping();
    $storage_definition = \Drupal::service('entity.last_installed_schema.repository')->getLastInstalledFieldStorageDefinitions('entity_test_update')['new_bundle_field'];

    // Save an entity with the bundle field populated.
    EntityTestHelper::createBundle('custom');
    $entity = $storage->create(['type' => 'test_bundle', 'new_bundle_field' => 'foo']);
    $entity->save();

    // Remove the bundle field and apply updates.
    $this->removeBundleField();
    $this->applyEntityUpdates();

    $dedicated_deleted_table_name = $table_mapping->getDedicatedDataTableName($storage_definition, TRUE);

    $this->assertTrue($schema_handler->dropTable($dedicated_deleted_table_name), 'Found and dropped the _deleted_ table.');
    field_purge_batch(10);
  }

}
