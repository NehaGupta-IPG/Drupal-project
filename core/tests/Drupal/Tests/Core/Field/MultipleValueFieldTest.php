<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Field;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the behavior of a multi-value required.
 *
 * @group Field
 */
class MultipleValueFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'field'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets up the test environment.
   *
   * Creates a 'page' content type and a multi-value text field.
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the 'page' content type.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Create the multi-value text field.
    $this->createMultiValueField();
  }

  /**
   * Tests the required behavior of a multi-value field.
   *
   * This test verifies that the required field validation works correctly
   * when editing a node with a multi-value text field.
   */
  public function testRequiredMultiValueField() {
    $web_user = $this->drupalCreateUser([
      'administer nodes',
      'create page content',
      'edit any page content',
    ]);
    $this->drupalLogin($web_user);

    // Ensure the content type exists.
    $this->assertNotNull(NodeType::load('page'), 'The page content type should exist.');

    // Step 1: Create a node with initial values.
    $node = $this->createNodeWithValues(['foo', 'bar', 'baz']);
    // Step 2: Assert initial field values.
    $this->assertFieldValues($node->id(), ['foo', 'bar', 'baz']);

    // Step 3: Edit the node, removing the first value 'foo'.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([
      'field_test_text[0][value]' => '',
      'field_test_text[1][value]' => 'bar',
      'field_test_text[2][value]' => 'baz',
    ], 'Save');

    // Get the page text to check for validation errors.
    $form_errors = $this->getSession()->getPage()->getText();

    // Step 4: Assert that a 'field is required' error is displayed.
    $this->assertStringContainsString('Test Text (value 1) field is required.', $form_errors);
  }

  /**
   * Creates a multi-value text field for the 'page' content type.
   */
  protected function createMultiValueField() {

    // Create the multi-value text field for the page content type.
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_test_text',
      'type' => 'text',
      'settings' => [
        'max_length' => 255,
      ],
      'cardinality' => -1,
      'translatable' => FALSE,
    ]);
    $field_storage->save();

    // Attach the field to the content type with the required setting.
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_test_text',
      'bundle' => 'page',
      'label' => 'Test Text',
      'required' => TRUE,
      'settings' => [
        'max_length' => 255,
      ],
    ])->save();

    // Set the form display for the field.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'page')
      ->setComponent('field_test_text', [
        'type' => 'text_textfield',
        'weight' => 0,
      ])
      ->save();
  }

  /**
   * Creates a node with specified multi-value field values.
   *
   * @param array $values
   *   An array of values to set for the multi-value field.
   *
   * @return \Drupal\node\Entity\Node
   *   The created node.
   */
  protected function createNodeWithValues(array $values) {
    // Create a node of type 'page' with the specified multi-value field values.
    $node = $this->drupalCreateNode(['type' => 'page']);
    $node->set('field_test_text', $values);
    $node->save();
    return $node;
  }

  /**
   * Asserts that the field values of a node match the expected values.
   *
   * @param int $node_id
   *   The ID of the node to check.
   * @param array $expected_values
   *   The expected field values.
   */
  protected function assertFieldValues($node_id, array $expected_values) {
    // Load the node and check the field values.
    $node = Node::load($node_id);
    $actual_values = array_map(function ($value) {
        return $value['value'];
    }, $node->get('field_test_text')->getValue());

    $this->assertEquals($expected_values, $actual_values, 'Field values are correct.');
  }

}
