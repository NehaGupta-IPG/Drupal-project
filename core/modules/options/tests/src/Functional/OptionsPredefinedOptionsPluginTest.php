<?php

namespace Drupal\Tests\options\Functional;

/**
 * Tests the predefined options plugin.
 *
 * @group options
 */
class OptionsPredefinedOptionsPluginTest extends OptionsPredefinedOptionsTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests options_allowed_values().
   *
   * @see options_test_dynamic_values_callback()
   */
  public function testOptionsAllowedValues() {
    // Test allowed values without passed $items.
    $values = options_allowed_values($this->fieldStorage);
    $this->assertEquals($values, []);

    $values = options_allowed_values($this->fieldStorage, $this->entity);

    $expected_values = $this->plugin->getAllowedValues($this->fieldStorage, $this->entity);
    $expected_values = array_combine($expected_values, $expected_values);
    $this->assertEquals($values, $expected_values);
  }

}
