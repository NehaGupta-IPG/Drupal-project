<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the validation of server configuration for asset aggregation.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class AssetAggregationValidationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
  }

  /**
   * Tests that enabling CSS aggregation validates the server configuration.
   */
  public function testCssAggregationValidation(): void {
    $this->drupalGet('admin/config/development/performance');
    $page = $this->getSession()->getPage();

    // Ensure aggregation is currently disabled to start fresh.
    $this->getSession()->executeScript("
      const el = document.getElementById('edit-preprocess-css');
      el.checked = false;
      el.dispatchEvent(new Event('change'));
    ");
    $this->submitForm([], 'Save configuration');

    // Enable CSS aggregation. This triggers the 'change' event listener in
    // assets.validate.js, which sends a HEAD request to the CSS asset route.
    $page->checkField('preprocess_css');

    // Wait for the success message. The JS uses fetch() which is
    // asynchronous. We must wait for the DOM to update.
    $success_message_partial = 'Your server is configured properly';
    $result = $this->assertSession()->waitForText($success_message_partial);
    $this->assertNotNull($result, 'The CSS validation success message did not appear after enabling aggregation.');

    // Ensure the failure message is NOT present.
    $this->assertSession()->pageTextNotContains('Your server is not configured properly');

    // Save the form.
    $this->submitForm([], 'Save configuration');

    // Verify the settings were saved successfully.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTrue($this->config('system.performance')->get('css.preprocess'), 'CSS aggregation was enabled.');
  }

  /**
   * Tests that enabling JS aggregation validates the server configuration.
   */
  public function testJsAggregationValidation(): void {
    $this->drupalGet('admin/config/development/performance');
    $page = $this->getSession()->getPage();

    // Ensure aggregation is currently disabled to start fresh.
    $this->getSession()->executeScript("
      const el = document.getElementById('edit-preprocess-js');
      el.checked = false;
      el.dispatchEvent(new Event('change'));
    ");
    $this->submitForm([], 'Save configuration');

    // Enable JS aggregation. This triggers the 'change' event listener in
    // assets.validate.js, which sends a HEAD request to the JS asset route.
    $page->checkField('preprocess_js');

    // Wait for the success message.
    $success_message_partial = 'Your server is configured properly';
    $result = $this->assertSession()->waitForText($success_message_partial);
    $this->assertNotNull($result, 'The JS validation success message did not appear after enabling aggregation.');

    // Ensure the failure message is NOT present.
    $this->assertSession()->pageTextNotContains('Your server is not configured properly');

    // Save the form.
    $this->submitForm([], 'Save configuration');

    // Verify the settings were saved successfully.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTrue($this->config('system.performance')->get('js.preprocess'), 'JS aggregation was enabled.');
  }

}
