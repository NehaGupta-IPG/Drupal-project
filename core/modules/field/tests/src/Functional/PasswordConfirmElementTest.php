<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

/**
 * Tests the password confirm form element.
 *
 * @group field
 */
class PasswordConfirmElementTest extends FieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @var array
   */
  protected array $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $web_user = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($web_user);
  }

  /**
   * Tests password confirm element behavior.
   */
  public function testPasswordConfirmForm(): void {
    // Display the form containing the password confirm element.
    $this->drupalGet('test-element/password-confirm');

    // Verify that the class name exists for password element.
    $this->assertSession()->elementExists('css', 'input[name="pass[pass1]"].test-password-class');
    $this->assertSession()->elementExists('css', 'input[name="pass[pass2]"].test-password-confirm-class');

    // Submit the form and check for the error message.
    $edit = [
      'pass[pass1]' => 'password',
      'pass[pass2]' => 'nope',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("The specified passwords do not match.");
    // Check the error class on the confirm element.
    $error_field = $this->assertSession()->fieldExists('pass[pass1]');
    $this->assertTrue($error_field->hasClass('error'));

    // Submit the form and check for the success message.
    $edit = [
      'pass[pass1]' => 'password',
      'pass[pass2]' => 'password',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("Your password has been confirmed.");
    // Check that the error class no longer exists.
    $error_field = $this->assertSession()->fieldExists('pass[pass1]');
    $this->assertFalse($error_field->hasClass('error'));
  }

}
