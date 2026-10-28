<?php

namespace Drupal\Tests\options\Functional;

/**
 * Tests the Options field predefined options plugin.
 *
 * @group options
 */
class OptionsPredefinedOptionsValidationTest extends OptionsPredefinedOptionsTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test that predefined options values gets the entity.
   */
  public function testPredefinedOptionsValues() {
    // Verify that validation passes against every value we had.
    $allowed_values = $this->plugin->getAllowedValues($this->fieldStorage, $this->entity);
    foreach ($allowed_values as $key => $value) {
      $this->entity->test_options->value = $value;
      $violations = $this->entity->test_options->validate();
      $this->assertCount(0, $violations, "$key is a valid value");
    }

    // Now verify that validation does not pass against anything else.
    foreach ($allowed_values as $key => $value) {
      $this->entity->test_options->value = is_numeric($value) ? (100 - $value) : ('X' . $value);
      $violations = $this->entity->test_options->validate();
      $this->assertCount(1, $violations, "$key is not a valid value");
    }
  }

}
