<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript\Form;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests form validation which requires browser HTML5 validation support.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class ValidationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests #pattern validation.
   */
  public function testPatternValidation(): void {
    $textfield_error = 'One digit followed by lowercase letters field is not in the right format.';
    $tel_error = 'Everything except numbers field is not in the right format.';
    $password_error = 'Password field is not in the right format.';

    // Invalid textfield, valid tel.
    $edit = [
      'textfield' => 'invalid',
      'tel' => 'valid',
    ];
    $this->drupalGet('form-test/pattern');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains($textfield_error);
    $this->assertSession()->pageTextNotContains($tel_error);
    $this->assertSession()->pageTextNotContains($password_error);

    // Valid textfield, invalid tel, valid password.
    $edit = [
      'textfield' => '7seven',
      'tel' => '818937',
      'password' => '0100110',
    ];
    $this->drupalGet('form-test/pattern');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextNotContains($textfield_error);
    $this->assertSession()->pageTextContains($tel_error);
    $this->assertSession()->pageTextNotContains($password_error);

    // Non required fields are not validated if empty.
    $edit = [
      'textfield' => '',
      'tel' => '',
    ];
    $this->drupalGet('form-test/pattern');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextNotContains($textfield_error);
    $this->assertSession()->pageTextNotContains($tel_error);
    $this->assertSession()->pageTextNotContains($password_error);

    // Invalid password.
    $edit = [
      'password' => $this->randomMachineName(),
    ];
    $this->drupalGet('form-test/pattern');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextNotContains($textfield_error);
    $this->assertSession()->pageTextNotContains($tel_error);
    $this->assertSession()->pageTextContains($password_error);

    // The pattern attribute overrides #pattern and is not validated on the
    // server side.
    $edit = [
      'textfield' => '',
      'tel' => '',
      'url' => 'http://www.example.com/',
    ];
    $this->drupalGet('form-test/pattern');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextNotContains('Client side validation field is not in the right format.');
  }

}
