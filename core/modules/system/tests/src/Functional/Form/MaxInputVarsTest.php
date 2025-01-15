<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests max input variable on forms.
 *
 * @group Form
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
    $this->drupalGet(Url::fromRoute('max_input_vars_test'));
    $this->assertSession()->titleEquals('Max Input Vars Test | Drupal');

    $max_vars = ini_get('max_input_vars');
    $values = [];
    for ($i = 0; $i < $max_vars + 1; $i++) {
      $values["box-$i"] = 1;
    }
    $this->submitForm($values, 'Submit');
    $this->assertSession()->statusMessageNotContains('This should not happen', 'error');
    $this->assertSession()->statusMessageContains('Input variables exceeded', 'error');
  }

}
