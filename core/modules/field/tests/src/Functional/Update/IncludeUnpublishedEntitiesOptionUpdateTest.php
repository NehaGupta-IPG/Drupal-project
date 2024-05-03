<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\Update;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for entity reference selection handler plugin setting.
 *
 * @group field
 * @covers \field_post_update_add_entity_reference_selection_plugin_option
 */
class IncludeUnpublishedEntitiesOptionUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for updating "include_unpublished_entities" setting.
   *
   * This setting is added in the "Entity reference selection handler" plugins.
   */
  public function testRunUpdates(): void {
    foreach (FieldConfig::loadMultiple() as $field_config) {
      $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
      $class = $field_type_manager->getPluginClass($field_config->getType());
      if ($class === EntityReferenceItem::class || is_subclass_of($class, EntityReferenceItem::class)) {
        $settings = $field_config->getSettings();
        $this->assertArrayNotHasKey('include_unpublished_entities', $settings);
      }
    }
    $this->runUpdates();

    foreach (FieldConfig::loadMultiple() as $field_config) {
      $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
      $class = $field_type_manager->getPluginClass($field_config->getType());
      if ($class === EntityReferenceItem::class || is_subclass_of($class, EntityReferenceItem::class)) {
        $settings = $field_config->getSettings();
        $this->assertArrayHasKey('include_unpublished_entities', $settings['handler_settings']);
        $this->assertFalse($settings['handler_settings']['include_unpublished_entities']);
      }
    }
  }

}
