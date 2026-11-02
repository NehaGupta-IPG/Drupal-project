<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\HttpKernelFormHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form API email element.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class EmailTest extends KernelTestBase {

  use HttpKernelFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test', 'system'];

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

    // Clear the static form error flag set by the validation failure above.
    // In BrowserTestBase each request is a fresh process; in kernel tests all
    // requests share the same process, so the flag must be reset manually.
    (new FormState())->clearErrors();
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

}
