<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests add_more config update for multivalued fields.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class ShowAddMoreButtonConfigTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'entity_test',
    'field_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The field used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'administer content types',
    ]));
  }

  /**
   * Tests that the add_more config gets updated for the multivalued fields.
   */
  public function testAddMoreConfig(): void {
    $field_name = $this->randomMachineName();
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'field_test',
      'cardinality' => 3,
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
    ]);
    $this->field->save();
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Create a form display for the default form mode.
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, [
        'type' => 'test_field_widget_multiple_single_value',
      ])
      ->save();
    // Verify add more config not available in form display config.
    $initial_component = $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->getComponent($field_name);
    $this->assertArrayNotHasKey('add_more', $initial_component['settings'], 'Show add more not configured for field ' . $field_name);
    // Run the field update hook.
    \Drupal::moduleHandler()->loadInclude('field', 'install');
    $sandbox = [];
    do {
      field_update_9001($sandbox);
    } while ($sandbox['#finished'] < 1);
    // Verify that add more config is updated as FALSE in form display config.
    $updated_component = $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->getComponent($field_name);
    $this->assertFalse($updated_component['settings']['add_more'], 'Show add more configured for field ' . $field_name);
  }

}
