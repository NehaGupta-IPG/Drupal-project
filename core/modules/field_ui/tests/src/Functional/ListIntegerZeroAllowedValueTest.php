<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Tests list_integer field with 0 key and editing allowed values via UI.
 *
 * @group field_ui
 */
class ListIntegerZeroAllowedValueTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'field_ui',
    'field',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // 1. Create content type.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    // 2. Create list_integer field with allowed values: 0 => Zero, 1 => One.
    FieldStorageConfig::create([
      'field_name' => 'field_test_list',
      'entity_type' => 'node',
      'type' => 'list_integer',
      'settings' => [
        'allowed_values' => [
          0 => 'Zero',
          1 => 'One',
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_test_list',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Test List Integer',
    ])->save();

    // 3. Enable widget on form display.
    $form_display = EntityFormDisplay::load('node.article.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $form_display->setComponent('field_test_list', ['type' => 'options_select'])->save();

    // 4. Create and log in admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit any article content',
      'administer node fields',
      'administer content types',
      'administer node form display',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests updating allowed values via UI after content exists with value 0.
   */
  public function testListIntegerZeroFieldUpdateViaUi(): void {
    // Create a node with value 0.
    Node::create([
      'type' => 'article',
      'title' => 'Test node',
      'field_test_list' => 0,
    ])->save();

    // Go to the field config UI page and saving.
    $this->drupalGet('/admin/structure/types/manage/article/fields/node.article.field_test_list');
    $this->submitForm([], 'Save settings');

    // Ensure it saved successfully.
    // Ensure the config saved (no error box should appear).
    $this->assertSession()->pageTextContains('Saved Test List Integer configuration.');
    $this->assertSession()->elementNotExists('css', '.messages--error');

  }

}
