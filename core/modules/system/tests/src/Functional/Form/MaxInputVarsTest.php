<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests max input variable on forms.
 *
 * This test requires manual configuration to work properly because PHP's
 * max_input_vars cannot be changed at runtime and the PHPUnit process may
 * have a different value than the web server handling requests.
 *
 * To run this test manually:
 * 1. Configure your web server's PHP to have a low max_input_vars (e.g., 100)
 * 2. Run: vendor/bin/phpunit --group manual
 *
 * @group manual
 */
class MaxInputVarsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'max_input_vars_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests PHP Max Input Vars error.
   */
  public function testMaxInputVars(): void {
    $max_vars = (int) ini_get('max_input_vars');

    // Skip if max_input_vars is too high to reasonably test.
    if ($max_vars > 1000) {
      $this->markTestSkipped(
        'This test requires max_input_vars to be set to a lower value. ' .
        'Current value: ' . $max_vars . '. ' .
        'Configure your web server PHP to use a lower max_input_vars.'
      );
    }

    $this->drupalGet(Url::fromRoute('max_input_vars_test'));
    $this->assertSession()->titleEquals('Max Input Vars Test | Drupal');
    $values = [];
    for ($i = 0; $i < $max_vars + 1; $i++) {
      $values["box-$i"] = 1;
    }
    $this->submitForm($values, 'Submit');
    $this->assertSession()->statusMessageNotContains('This should not happen', 'error');
    $this->assertSession()->statusMessageContains('Input variables exceeded', 'error');
  }

}
