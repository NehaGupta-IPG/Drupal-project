<?php

declare(strict_types=1);

namespace Drupal\Tests\options\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Options widgets.
 *
 * @group options
 */
class OptionsWidgetsJsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'options',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser([
      'bypass node access',
      'administer node fields',
      'administer node display',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests the 'options_buttons' widget for multiple select.
   */
  public function testCheckboxes(): void {
    $this->createContentType(['type' => 'article', 'name' => 'Article']);
    // Create field.
    FieldStorageConfig::create([
      'field_name' => 'field_fences_test',
      'entity_type' => 'node',
      'type' => 'text',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    // Create field instance.
    FieldConfig::create([
      'label' => 'Field Fences Test',
      'field_name' => 'field_fences_test',
      'entity_type' => 'node',
      'bundle' => 'article',
      'settings' => [],
    ])->save();
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    // Enable form display and set check_all_enabled to TRUE.
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent('field_fences_test', [
        'type' => 'text_textfield',
        'settings' => [
          'check_all_enabled' => TRUE,
        ],
      ])
      ->save();
    // Go to the Create Article page and check that the button is there.
    $this->drupalGet('node/add/article');
    $this->getSession()->getPage()->hasButton('Check all / none');
    // Change the setting to FALSE.
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent('field_fences_test', [
        'type' => 'text_textfield',
        'settings' => [
          'check_all_enabled' => FALSE,
        ],
      ])
      ->save();
    // Reload the create Article page and confirm there is no 'check all' button.
    $this->drupalGet('node/add/article');
    $this->assertFalse($this->getSession()->getPage()->hasButton('Check all / none'));
  }

}
