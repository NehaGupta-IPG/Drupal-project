<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests that fields removed from the form display are not rendered.
 *
 * @group field
 */
class HiddenWidgetFormDisplayTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_ui',
    'entity_test',
  ];

  /**
   * Tests hidden widget behavior by removing the field component.
   */
  public function testHiddenWidgetFieldNotRendered(): void {
    // Create a test field on entity_test entity type.
    FieldStorageConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Test field',
    ])->save();

    // Get a form display for entity_test and remove the test field component.
    $form_display = $this->container->get('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test');
    $form_display->removeComponent('test_field')->save();

    $entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->create();

    $built_form = [];
    $form_state = new FormState();
    $form_display->buildForm($entity, $built_form, $form_state);

    $this->assertArrayNotHasKey('test_field', $built_form);
  }

}
