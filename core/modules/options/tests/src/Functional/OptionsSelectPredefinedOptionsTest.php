<?php

namespace Drupal\Tests\options\Functional;

/**
 * Tests an options select with values from a predefined options plugin.
 *
 * @group options
 */
class OptionsSelectPredefinedOptionsTest extends OptionsPredefinedOptionsTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the 'options_select' widget (single select).
   */
  public function testSelectPredefinedOptions() {
    // Create an entity.
    $this->entity->save();

    // Create a web user.
    $web_user = $this->drupalCreateUser(['view test entity', 'administer entity_test content']);
    $this->drupalLogin($web_user);

    // Display form.
    $this->drupalGet('entity_test_rev/manage/' . $this->entity->id() . '/edit');
    $options = $this->xpath('//select[@id="edit-test-options"]/option');
    $values = $this->plugin->getAllowedValues($this->fieldStorage, $this->entity);
    $this->assertCount(count($values) + 1, $options);
    foreach ($options as $option) {
      $value = $option->getValue();
      if ($value !== '_none') {
        self::assertNotEmpty(array_search($value, $values, TRUE));
      }
    }
  }

}
