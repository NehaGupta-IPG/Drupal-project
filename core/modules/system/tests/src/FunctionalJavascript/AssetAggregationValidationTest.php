<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the validation of server configuration for asset aggregation.
 */
#[Group('system')]
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
   * Tests that enabling aggregation validates the server configuration.
   */
  public function testAggregationValidation(): void {
    $this->drupalGet('admin/config/development/performance');
    $page = $this->getSession()->getPage();

    // Ensure aggregation is currently disabled to start fresh.
    // We uncheck via JS to avoid triggering the validation logic immediately if it was already on.
    $this->getSession()->executeScript("jQuery('#edit-preprocess-css').prop('checked', false).trigger('change');");
    $this->submitForm([], 'Save configuration');

    // Enable CSS aggregation.
    // This should trigger the 'change' event listener in assets.validate.js.
    $page->checkField('preprocess_css');

    // Wait for the Success Message.
    // The JS uses fetch() which is asynchronous. We must wait for the DOM to update.
    // The patch displays: "Your server is configured properly to access @name assets."
    $success_message_partial = 'Your server is configured properly';

    $result = $this->assertSession()->waitForText($success_message_partial);
    $this->assertNotNull($result, 'The success message did not appear after enabling aggregation.');

    // Ensure the Failure Message is NOT present.
    // "Your server is not configured properly..."
    $this->assertSession()->pageTextNotContains('Your server is not configured properly');
    $this->assertSession()->elementNotExists('css', '.messages--error');

    // Save the form.
    $this->submitForm([], 'Save configuration');

    // Verify the settings were saved successfully.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTrue($this->config('system.performance')->get('css.preprocess'), 'CSS aggregation was enabled.');
  }

}
