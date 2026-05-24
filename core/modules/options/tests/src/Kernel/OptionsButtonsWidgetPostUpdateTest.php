<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests options_post_update_options_buttons_check_all_setting().
 */
#[Group('options')]
#[RunTestsInSeparateProcesses]
class OptionsButtonsWidgetPostUpdateTest extends OptionsFieldUnitTestBase {

  /**
   * Tests that check_all_enabled is added to existing widget instances.
   */
  public function testAddCheckAllSetting(): void {
    // The base setUp() creates a form display with options_buttons. Simulate an
    // existing installation by removing check_all_enabled from stored config.
    $config_name = 'core.entity_form_display.entity_test.entity_test.default';
    $config = \Drupal::configFactory()->getEditable($config_name);
    $content = $config->get('content');
    unset($content[$this->fieldName]['settings']['check_all_enabled']);
    $config->set('content', $content)->save(TRUE);

    // Confirm the setting is absent.
    $component = \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->getComponent($this->fieldName);
    $this->assertArrayNotHasKey('check_all_enabled', $component['settings'] ?? []);

    // Run the post-update hook.
    require_once \Drupal::root() . '/core/modules/options/options.post_update.php';
    options_post_update_options_buttons_check_all_setting();

    // Verify the setting is now FALSE.
    $component = \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->getComponent($this->fieldName);
    $this->assertFalse($component['settings']['check_all_enabled']);
  }

}
