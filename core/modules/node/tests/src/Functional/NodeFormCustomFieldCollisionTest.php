<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that custom fields sharing a NodeForm structural key still render.
 *
 * NodeForm::form() builds the "Status", "Authoring information" and
 * "Promotion options" containers under the hardcoded keys meta, author and
 * options. A custom field with one of those machine names must not be silently
 * overwritten by the structural container.
 *
 * @see https://www.drupal.org/project/drupal/issues/3590935
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodeFormCustomFieldCollisionTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The machine names that collide with NodeForm structural containers.
   *
   * @var string[]
   */
  protected array $fieldNames = ['author', 'meta', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $display_repository = \Drupal::service('entity_display.repository');
    $form_display = $display_repository->getFormDisplay('node', 'article');
    foreach ($this->fieldNames as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => ucfirst($field_name) . ' field',
      ])->save();
      $form_display->setComponent($field_name, ['type' => 'string_textfield']);
    }
    // Promote and sticky are hidden by default; enable them so the "Promotion
    // options" container has content and its fallback grouping is exercised.
    $form_display->setComponent('promote', ['type' => 'boolean_checkbox']);
    $form_display->setComponent('sticky', ['type' => 'boolean_checkbox']);
    $form_display->save();

    $this->drupalLogin($this->drupalCreateUser([
      'bypass node access',
      'administer nodes',
    ]));
  }

  /**
   * Tests that the colliding custom fields render and persist their values.
   */
  public function testCustomFieldsCollidingWithStructuralKeys(): void {
    $this->drupalGet('node/add/article');

    $edit = ['title[0][value]' => $this->randomString()];
    $values = [];
    foreach ($this->fieldNames as $field_name) {
      $input_name = "{$field_name}[0][value]";
      $this->assertSession()->fieldExists($input_name);
      $values[$field_name] = $this->randomMachineName();
      $edit[$input_name] = $values[$field_name];
    }

    // The structural containers must keep their grouping for the base fields,
    // using a fallback key when a custom field claimed the original one.
    $this->assertSession()->pageTextContains('Authoring information');
    $this->assertSession()->pageTextContains('Promotion options');

    $this->submitForm($edit, 'Save');

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'title' => $edit['title[0][value]'],
    ]);
    $node = reset($nodes);
    $this->assertNotEmpty($node, 'The node was saved despite the field name collision.');
    foreach ($this->fieldNames as $field_name) {
      $this->assertSame($values[$field_name], $node->get($field_name)->value);
    }
  }

}
