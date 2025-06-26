<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field_hidden_widget_test\Entity\TestEntity;
use Drupal\Core\Form\FormState;

/**
 * Tests that 'hidden' widget type correctly skips rendering in form display.
 *
 * @group field
 */
class HiddenWidgetFormDisplayTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'field_ui',
    'field_hidden_widget_test',
  ];

  /**
   * Tests hidden widget in form display.
   */
  public function testHiddenWidgetNotRendered(): void {
    // Install schema for test_entity.
    $this->installEntitySchema('test_entity');

    // Create a test entity.
    $entity = TestEntity::create([
      'name' => 'Test',
    ]);

    // Create fresh runtime form display (DO NOT load + save).
    $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')
      ->create([
        'targetEntityType' => 'test_entity',
        'bundle' => 'test_entity',
        'mode' => 'default',
        'status' => TRUE,
      ]);

    // Set the 'test_field' widget to 'hidden'.
    $form_display->setComponent('test_field', [
      'type' => 'hidden',
      'weight' => 5,
    ]);

    // Build the form display.
    $built_form = [];
    $form_state = new FormState();
    $form_display->buildForm($entity, $built_form, $form_state);

    // Assert that the field is not present in the form.
    $this->assertArrayNotHasKey('test_field', $built_form, 'Hidden widget field is not rendered in form.');
  }

}
