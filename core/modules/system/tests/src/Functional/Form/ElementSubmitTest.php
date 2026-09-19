<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests element level submitting.
 *
 * @see \Drupal\form_test\Form\FormTestElementSubmitForm
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class ElementSubmitTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * Tests full and limited element submission, including callback order.
   */
  public function testElementSubmit(): void {
    // Verify that #element_submit handlers are executed.
    $this->drupalGet('form-test/element-submit');
    $edit = [
      'name' => 'element_submit',
      'group_1[name_1]' => 'element_submit_1',
      'group_1[name_2]' => 'element_submit_2',
      'group_2[name_3]' => 'element_submit_3',
    ];
    $this->submitForm($edit, 'Save');

    $assert = $this->assertSession();
    // Make sure the execution order is also right.
    $assert->pageTextMatches('/name triggered.*group_1:name_1 triggered.*group_1:name_2 triggered.*group_1 triggered.*group_2:name_3 triggered.*Executed Drupal\\\form_test\\\Form\\\FormTestElementSubmitForm::submitForm\./s');
    $assert->pageTextNotContains('Executed Drupal\form_test\Form\FormTestElementSubmitForm::submitForm1.');

    $edit = [
      'group_1[name_1]' => 'element_submit_1',
      'group_1[name_2]' => 'element_submit_2',
    ];
    $this->submitForm($edit, 'Save group 1');

    $assert->pageTextNotContains('name triggered');
    $assert->pageTextNotContains('group_2:name_3 triggered');
    $assert->pageTextMatches('/group_1:name_1.*group_1:name_2.*Executed Drupal\\\form_test\\\Form\\\FormTestElementSubmitForm::submitForm1\./s');
    $assert->pageTextNotContains('Executed Drupal\form_test\Form\FormTestElementSubmitForm::submitForm.');
  }

  /**
   * Tests that validation errors prevent element and form submission.
   */
  public function testValidationError(): void {
    $this->drupalGet('form-test/element-submit');
    $this->submitForm(['name' => 'invalid'], 'Save');
    $this->assertSession()->pageTextContains('Invalid name.');
    $this->assertSession()->pageTextNotContains('triggered');
    $this->assertSession()->pageTextNotContains('Executed');
  }

}
