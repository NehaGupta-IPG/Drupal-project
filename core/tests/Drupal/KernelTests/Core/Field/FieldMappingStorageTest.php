<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Field;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test field items with custom property mapping.
 *
 * @group Field
 */
class FieldMappingStorageTest extends EntityKernelTestBase {

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityDefinitionUpdateManager = $this->container->get('entity.definition_update_manager');
  }

  public function testFieldMappingStorage(): void {
    $definitions['data_map'] = BaseFieldDefinition::create('mapped_properties_test')
      ->setLabel('Data')
      ->setRequired(TRUE);

    $this->state->set('entity_test.additional_base_field_definitions', $definitions);

    $this->entityDefinitionUpdateManager->installFieldStorageDefinition(
      'data_map',
      'entity_test',
      'entity_test',
      $definitions['data_map']
    );

    $data_map_value = [
      'key' => 'value',
      'another' => 'second value',
      'extracted_value' => 'this is stored separately',
    ];
    $entity = EntityTest::create([
      'data_map' => $data_map_value,
    ]);
    $entity->save();
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test');
    $loaded = $storage->loadUnchanged($entity->id());
    // The value is altered when it is mapped back out of storage.
    $data_map_value['extracted_value'] = 'Extracted: ' . $data_map_value['extracted_value'];
    $this->assertEqualsCanonicalizing($data_map_value, $loaded->get('data_map')->first()->getValue());
  }

}
