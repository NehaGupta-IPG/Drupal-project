<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form API textarea element.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class TextareaTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the textarea element #resizable property.
   */
  public function testFormTextareaResizable(): void {
    $this->drupalGet('form-test/textarea');
    $this->assertNotEmpty($this->cssSelect('#edit-textarea-resizable-vertical.resize-vertical'));
    $this->assertNotEmpty($this->cssSelect('#edit-textarea-resizable-horizontal.resize-horizontal'));
    $this->assertNotEmpty($this->cssSelect('#edit-textarea-resizable-both.resize-both'));
    $this->assertNotEmpty($this->cssSelect('#edit-textarea-resizable-none.resize-none'));
  }

  /**
   * Tests that theme overrides apply resize styles to the textarea.
   */
  public function testThemeOverrideAppliesResizeStyleToTextarea(): void {
    \Drupal::service('theme_installer')
      ->install(['starterkit_theme']);
    $this->config('system.theme')
      ->set('default', 'starterkit_theme')
      ->save();

    $this->drupalGet('form-test/textarea');
    foreach (['vertical', 'horizontal', 'both', 'none'] as $resizable) {
      $id = "#edit-textarea-resizable-$resizable";
      $this->assertNotEmpty($this->cssSelect("$id.resize-$resizable"));
      $this->assertEmpty($this->cssSelect("div.resize-$resizable > $id"));
    }
  }

}
