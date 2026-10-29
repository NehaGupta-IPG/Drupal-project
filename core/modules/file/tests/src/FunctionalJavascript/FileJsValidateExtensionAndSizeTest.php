<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests client-side filesize validation in file.js.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
final class FileJsValidateExtensionAndSizeTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'file_module_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Ensures oversize validation shows error, clears input, and formats bytes.
   */
  public function testOversizeHidesUploadButtonClearsInputAndShowsError(): void {
    $this->drupalGet(Url::fromRoute('file_module_test.managed_test'));

    $this->assertSession()->elementExists('css', 'div.js-form-managed-file input[type="file"]');

    $upload_button_exists = (bool) $this->getSession()->evaluateScript(
      "return !!document.querySelector('.upload-button');"
    );

    $this->setFileSettingsAndAttach(
      'div.js-form-managed-file input[type="file"]',
      1024,
      '1 KB',
      'txt'
    );

    // 2048 bytes => 2.00 KB, should exceed 1 KB.
    $this->setSyntheticFileAndTriggerChange(
      'div.js-form-managed-file input[type="file"]',
      2048,
      'big.txt'
    );

    // Wait for error block.
    $this->assertSession()->waitForElementVisible('css', '.file-upload-js-error');

    // Assert the full rendered error message.
    $error_text = $this->getErrorText();
    $expected = 'The specified file big.txt could not be uploaded. The file size is 2.00 KB exceeding the maximum file size of 1 KB.';
    $this->assertSame($expected, $error_text);

    // Input should be cleared by the oversize branch.
    $this->assertSame('', $this->getFileInputValue('div.js-form-managed-file input[type="file"]'));

    // Upload button should be hidden (if the test form provides it).
    if ($upload_button_exists) {
      $is_hidden = (bool) $this->getSession()->evaluateScript(
        "return window.jQuery ? window.jQuery('.upload-button').is(':hidden') : (getComputedStyle(document.querySelector('.upload-button')).display === 'none');"
      );
      $this->assertTrue($is_hidden, 'Upload button is hidden for oversize file.');
    }
  }

  /**
   * Ensures within-limit files do not create an error.
   */
  public function testWithinLimitShowsUploadButtonAndNoError(): void {
    $this->drupalGet(Url::fromRoute('file_module_test.managed_test'));

    $this->assertSession()->elementExists('css', 'div.js-form-managed-file input[type="file"]');

    $upload_button_exists = (bool) $this->getSession()->evaluateScript(
      "return !!document.querySelector('.upload-button');"
    );

    $this->setFileSettingsAndAttach(
      'div.js-form-managed-file input[type="file"]',
      2048,
      '2 KB',
      'txt'
    );

    // 512 bytes is under 2 KB.
    $this->setSyntheticFileAndTriggerChange(
      'div.js-form-managed-file input[type="file"]',
      512,
      'small.txt'
    );

    // No oversize error should exist.
    $this->assertSession()->elementNotExists('css', '.file-upload-js-error');

    // Upload button should be visible (if present).
    if ($upload_button_exists) {
      $is_visible = (bool) $this->getSession()->evaluateScript(
        "return window.jQuery ? window.jQuery('.upload-button').is(':visible') : (getComputedStyle(document.querySelector('.upload-button')).display !== 'none');"
      );
      $this->assertTrue($is_visible, 'Upload button is visible for in-range file.');
    }
  }

  /**
   * Covers humanFileSize() formatting by asserting multiple unit outputs.
   */
  #[DataProvider('providerHumanFileSizeFormatsMultipleUnits')]
  public function testHumanFileSizeFormatsMultipleUnits(
    int $bytes,
    string $expected,
    bool $expects_error,
  ): void {
    $this->drupalGet(Url::fromRoute('file_module_test.managed_test'));

    $this->assertSession()->elementExists('css', 'div.js-form-managed-file input[type="file"]');

    // Force max size tiny so larger cases trigger the oversize message.
    $this->setFileSettingsAndAttach(
      'div.js-form-managed-file input[type="file"]',
      1,
      '1 B',
      'txt'
    );

    $this->removeErrors();

    $this->setSyntheticFileAndTriggerChange(
      'div.js-form-managed-file input[type="file"]',
      $bytes,
      "size-$bytes.txt"
    );

    if ($expects_error) {
      $this->assertSession()->waitForElementVisible('css', '.file-upload-js-error');
      $this->assertSession()->pageTextContains($expected);
    }
    else {
      $this->assertSession()->elementNotExists('css', '.file-upload-js-error');
    }
  }

  /**
   * Provides file sizes and expected formatted output strings.
   */
  public static function providerHumanFileSizeFormatsMultipleUnits(): array {
    return [
      'zero bytes' => [0, '0.00 B', FALSE],
      'one byte' => [1, '1.00 B', FALSE],
      'one kilobyte' => [1024, '1.00 KB', TRUE],
      'two kilobytes' => [2048, '2.00 KB', TRUE],
      'one megabyte' => [1024 * 1024, '1.00 MB', TRUE],
    ];
  }

  /**
   * Sets drupalSettings.file and (re)attaches behaviors so validation binds.
   */
  private function setFileSettingsAndAttach(
    string $selector,
    int $max_bytes,
    string $human_max,
    string $extensions = 'txt',
  ): void {
    $js = <<<JS
(function () {
  const input = document.querySelector('$selector');
  if (!input) {
    throw new Error('No file input found for selector: $selector');
  }

  window.drupalSettings = window.drupalSettings || {};
  drupalSettings.file = drupalSettings.file || {};

  drupalSettings.file.max_size = $max_bytes;
  drupalSettings.file.human_max_size = '$human_max';

  drupalSettings.file.elements = drupalSettings.file.elements || {};
  drupalSettings.file.elements['#' + input.id] = '$extensions';

  if (window.Drupal && typeof Drupal.attachBehaviors === 'function') {
    Drupal.attachBehaviors(document, drupalSettings);
  }
})();
JS;

    $this->getSession()->executeScript($js);
  }

  /**
   * Creates an in-memory File of $bytes, assigns the input, triggers change.
   */
  private function setSyntheticFileAndTriggerChange(
    string $selector,
    int $bytes,
    string $filename = 'test.txt',
    string $mime = 'text/plain',
  ): void {
    $js = <<<JS
(function () {
  const input = document.querySelector('$selector');
  if (!input) {
    throw new Error('No file input found for selector: $selector');
  }

  const file = new File([new Uint8Array($bytes)], '$filename', { type: '$mime' });

  const dt = new DataTransfer();
  dt.items.add(file);
  input.files = dt.files;

  if (window.jQuery) {
    // Trigger the namespaced event used by file.js.
    window.jQuery(input).trigger('change.fileValidate');
  }
  else {
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
})();
JS;

    $this->getSession()->executeScript($js);
  }

  /**
   * Returns the current value of the file input.
   */
  private function getFileInputValue(string $selector): string {
    $value = $this->getSession()->evaluateScript(<<<JS
(function () {
  const input = document.querySelector('$selector');
  if (!input) {
    throw new Error('No file input found for selector: $selector');
  }
  return input.value || '';
})();
JS);

    return (string) $value;
  }

  /**
   * Returns the rendered error text.
   */
  private function getErrorText(): string {
    $text = $this->getSession()->evaluateScript(<<<'JS'
(function () {
  const el = document.querySelector('.file-upload-js-error');
  return el ? (el.textContent || '').trim() : '';
})();
JS);

    return (string) $text;
  }

  /**
   * Removes any previous JS errors.
   */
  private function removeErrors(): void {
    $this->getSession()->executeScript(
      "if (window.jQuery) { window.jQuery('.file-upload-js-error').remove(); }"
    );
  }

}
