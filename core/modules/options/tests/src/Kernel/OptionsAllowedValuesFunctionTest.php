<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\options\OptionsAllowedValuesInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests using a closure as an allowed values function.
 */
#[Group('options')]
#[RunTestsInSeparateProcesses]
class OptionsAllowedValuesFunctionTest extends OptionsFieldUnitTestBase {

  /**
   * Tests that a closure can be used as allowed values function.
   *
   * A closure cannot be stored in configuration, so it is set on the field
   * storage definition in memory rather than saved.
   */
  public function testClosureAllowedValuesFunction(): void {
    $closure = static function ($definition, $entity, &$cacheable) {
      $cacheable = FALSE;
      return ['a' => 'Apple', 'b' => 'Banana'];
    };
    $this->fieldStorage->setSetting('allowed_values_function', $closure);

    $allowed_values = \Drupal::service(OptionsAllowedValuesInterface::class)
      ->getAllowedValues($this->fieldStorage);
    $this->assertSame(['a' => 'Apple', 'b' => 'Banana'], $allowed_values);
  }

  /**
   * Tests the storage settings form when a closure is used.
   *
   * @see \Drupal\Tests\options\Functional\OptionsWidgetsTest::testAllowedValuesFunctionDisplay()
   */
  public function testClosureAllowedValuesFunctionWidgetDisplay(): void {
    $closure = static function () {
      return [];
    };
    // A closure cannot be persisted to configuration, so build the field
    // definition and item in memory instead of going through
    // \Drupal\field\Entity\FieldStorageConfig.
    $field_definition = BaseFieldDefinition::create('list_integer')
      ->setName($this->fieldName)
      ->setTargetEntityTypeId('entity_test')
      ->setSetting('allowed_values', [])
      ->setSetting('allowed_values_function', $closure);
    $item = \Drupal::typedDataManager()->create($field_definition->getItemDefinition());

    $form = [];
    $form_state = new FormState();
    $element = $item->storageSettingsForm($form, $form_state, FALSE);

    $this->assertTrue($element['allowed_values_function']['#access']);
    $this->assertSame('Closure', $element['allowed_values_function']['#value']);
    $this->assertStringContainsString('Closure', (string) $element['allowed_values_function']['#markup']);
  }

}
