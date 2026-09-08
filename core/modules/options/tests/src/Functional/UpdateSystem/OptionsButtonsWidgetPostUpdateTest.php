<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Functional\UpdateSystem;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UpdatePathTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests options_post_update_options_buttons_check_all_setting().
 */
#[Group('options')]
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class OptionsButtonsWidgetPostUpdateTest extends BrowserTestBase {

  use UpdatePathTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The field name used in the test.
   *
   * @var string
   */
  protected $fieldName = 'test_options';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'type' => 'list_integer',
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => [1 => 'One', 2 => 'Two', 3 => 'Three'],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($this->fieldName, [
        'type' => 'options_buttons',
      ])
      ->save();

    // Remove check_all_enabled from stored config to simulate an existing
    // site that predates the setting.
    $config_name = 'core.entity_form_display.entity_test.entity_test.default';
    $config = \Drupal::configFactory()->getEditable($config_name);
    $content = $config->get('content');
    unset($content[$this->fieldName]['settings']['check_all_enabled']);
    $config->set('content', $content)->save();

    // Ensure options_post_update_options_buttons_check_all_setting() runs.
    $existing_updates = \Drupal::keyValue('post_update')->get('existing_updates', []);
    $index = array_search('options_post_update_options_buttons_check_all_setting', $existing_updates);
    unset($existing_updates[$index]);
    \Drupal::keyValue('post_update')->set('existing_updates', $existing_updates);
  }

  /**
   * Tests that check_all_enabled is added to existing widget instances.
   */
  public function testAddCheckAllSetting(): void {
    // Confirm the setting is absent.
    $component = \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->getComponent($this->fieldName);
    $this->assertArrayNotHasKey('check_all_enabled', $component['settings'] ?? []);

    $this->runUpdates();

    // Verify the setting is now FALSE.
    $component = \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->getComponent($this->fieldName);
    $this->assertFalse($component['settings']['check_all_enabled']);
  }

}
