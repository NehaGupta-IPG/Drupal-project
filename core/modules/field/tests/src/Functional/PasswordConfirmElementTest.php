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
  }

}
