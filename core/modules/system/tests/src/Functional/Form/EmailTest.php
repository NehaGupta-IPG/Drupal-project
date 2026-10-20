<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\EmailTestDataTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form API email element.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class EmailTest extends BrowserTestBase {

  use EmailTestDataTrait;

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
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains("The email address invalid is not valid.");
    $this->assertSession()->pageTextContains("Address field is required.");

    $edit = [];
    $edit['email_required'] = '  foo.bar@example.com ';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $values = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertSame('', $values['email']);
    $this->assertEquals('foo.bar@example.com', $values['email_required']);

    $edit = [];
    $edit['email'] = 'foo@example.com';
    $edit['email_required'] = 'example@drupal.org';
    $this->drupalGet('form-test/email');
    $this->submitForm($edit, 'Submit');
    $values = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertEquals('foo@example.com', $values['email']);
    $this->assertEquals('example@drupal.org', $values['email_required']);
  }

  /**
   * Tests supported SMTP mailbox syntax through form submission.
   */
  public function testMailboxSyntax(): void {
    foreach (self::internationalizedMailboxProvider() as [$mailbox]) {
      $this->drupalGet('form-test/email');
      $this->submitForm(['email' => $mailbox, 'email_required' => $mailbox], 'Submit');
      $values = Json::decode($this->getSession()->getPage()->getContent());
      $this->assertSame($mailbox, $values['email']);
      $this->assertSame($mailbox, $values['email_required']);
    }
  }

}
