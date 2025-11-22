<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core\Form;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the password unmask functionality.
 */
#[Group('javascript')]
class PasswordUnmaskTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test the password_unmask form element functionality.
   */
  #[DataProvider('providerPasswordFieldSelector')]
  public function testPasswordRevealFormElement($label, $selector, $password_field_id, $data_selector): void {

    $this->drupalGet('/form-test/password-reveal');
    $page = $this->getSession()->getPage();

    $label = $page->find('css', $selector);
    $password_field = $page->findById($password_field_id);

    /* Initial state: before the show password link is clicked. */
    // Test that password fields type is password.
    $this->assertEquals($password_field->getAttribute('type'), 'password');

    $page->find('css', $data_selector)->setValue("TestPasswordVisible");
    // Asserts that password is not visible.
    $this->assertSession()->pageTextNotContains('TestPasswordVisible');
    // Change state: click the "show password" of password one link.
    $label->click();
    // Test that the password one field type is now text.
    $this->assertEquals($password_field->getAttribute('type'), 'text');

    $this->assertSession()->waitForElementVisible('css', $data_selector);
    // Asserts that password is visible.
    $this->assertSession()->waitForText('TestPasswordVisible');

    // Change state: click the "hide password" link.
    $label->click();

    // Test that the password field type is password.
    $this->assertEquals($password_field->getAttribute('type'), 'password');

  }

  /**
   * Data provider for testPasswordRevealFormElement().
   */
  public static function providerPasswordFieldSelector(): array {
    return [
      'password_one_field' => ['password_one_field', '.form-item-password-one button', 'edit-password-one', '[data-drupal-selector="edit-password-one"]'],
    ];
  }

}
