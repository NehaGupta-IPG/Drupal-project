<?php

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the validation of server configuration for asset aggregation.
 *
 * @group system
 */
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
  public function testAggregationValidation() {
    $this->drupalGet('admin/config/development/performance');
    $page = $this->getSession()->getPage();

    // Ensure aggregation is currently disabled to start fresh.
    $this->getSession()->executeScript("jQuery('#edit-preprocess-css').prop('checked', false).trigger('change');");
    $this->submitForm([], 'Save configuration');

    // Enable CSS aggregation.
    // This should trigger the 'change' event listener in assets.validate.js.
    $page->checkField('preprocess_css');

    // Wait for the Success Message.
    $success_message_partial = 'Your server is configured properly';

    $result = $this->assertSession()->waitForText($success_message_partial);
    $this->assertNotNull($result, 'The success message did not appear after enabling aggregation.');

    // Ensure the Failure Message is NOT present.
    $this->assertSession()->pageTextNotContains('Your server is not configured properly');
    $this->assertSession()->elementNotExists('css', '.messages--error');

    // Save the form.
    $this->submitForm([], 'Save configuration');

    // Verify the settings were saved successfully.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTrue($this->config('system.performance')->get('css.preprocess'), 'CSS aggregation was enabled.');
  }

}
