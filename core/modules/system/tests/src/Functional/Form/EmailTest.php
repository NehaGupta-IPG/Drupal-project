<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the form API email element.
 *
 * @group Form
 */
class EmailTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that #type 'email' fields are properly validated.
   */
  public function testFormEmail(): void {
    $edit = [];
    $edit['email'] = 'invalid';
    $edit['email_required'] = ' ';
    $edit['email_multiple'] = 'foo.bar@example.com,invalid-first,invalid-second';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains('The email address invalid is not valid. Use the format user@example.com.');
    $this->assertSession()->pageTextContains('Address field is required.');
    $this->assertSession()->pageTextContains('The email addresses invalid-first, invalid-second are not valid. Use the format user@example.com, and separate the addresses with a comma.');

    $edit = [];
    $edit['email_required'] = 'invalid-required';
    $edit['email_multiple'] = 'foo.bar@example.com,invalid-multiple';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains('The email address invalid-required is not valid. Use the format user@example.com.');
    $this->assertSession()->pageTextContains('The email address invalid-multiple is not valid. Use the format user@example.com, and separate the addresses with a comma.');

    $edit = [];
    $edit['email_required'] = 'example@drupal.org,example2@drupal.org';
    $edit['email_multiple'] = 'foo.bar@example.com, ,bar.bar@example.com';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains('The email address example@drupal.org,example2@drupal.org is not valid. Use the format user@example.com.');
    $this->assertSession()->pageTextContains('All email addresses must be non-empty.');

    $edit = [];
    $edit['email_required'] = '  foo.bar@example.com ';
    $edit['email_multiple'] = ' foo.bar@example.com, bar.bar@example.com  ';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $values = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertSame('', $values['email']);
    $this->assertEquals('foo.bar@example.com', $values['email_required']);
    $this->assertEquals('foo.bar@example.com,bar.bar@example.com', $values['email_multiple']);

    $edit = [];
    $edit['email'] = 'foo@example.com';
    $edit['email_required'] = 'example@drupal.org';
    $edit['email_multiple'] = 'foo.bar@example.com,bar.bar@example.com';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $values = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertEquals('foo@example.com', $values['email']);
    $this->assertEquals('example@drupal.org', $values['email_required']);
    $this->assertEquals('foo.bar@example.com,bar.bar@example.com', $values['email_multiple']);
  }

}
