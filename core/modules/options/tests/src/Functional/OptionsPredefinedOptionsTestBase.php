<?php

namespace Drupal\Tests\options\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\Tests\field\Functional\FieldTestBase;

/**
 * Base class for testing options fields using predefined options plugin.
 */
abstract class OptionsPredefinedOptionsTestBase extends FieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['options', 'entity_test', 'options_test'];

  /**
   * The created entity.
   *
   * @var \Drupal\Core\Entity\Entity
   */
  protected $entity;

  /**
   * The field storage.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  protected $fieldStorage;

  /**
   * The field instance.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * The plugin instance.
   *
   * @var \Drupal\options\Plugin\PredefinedOptionsPluginInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $field_name = 'test_options';
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test_rev',
      'type' => 'list_string',
      'cardinality' => 1,
      'settings' => [
        'predefined_options_plugin' => 'predefined_options_test',
      ],
    ]);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test_rev',
      'bundle' => 'entity_test_rev',
      'required' => TRUE,
    ])->save();
    $this->container->get('entity_display.repository')
      ->getFormDisplay('entity_test_rev', 'entity_test_rev')
      ->setComponent($field_name, [
        'type' => 'options_select',
      ])
      ->save();

    // Create an entity and prepare test data that will be used by
    // options_test_dynamic_values_callback().
    $values = [
      'user_id' => mt_rand(1, 10),
      'name' => $this->randomMachineName(),
    ];

    $this->entity = EntityTestRev::create($values);
    $this->entity->save();

    $this->plugin = \Drupal::service('plugin.manager.options.predefined_options')
      ->createInstance('predefined_options_test');
  }

}
