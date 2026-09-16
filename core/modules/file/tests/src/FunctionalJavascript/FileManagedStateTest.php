<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests visibility of managed files.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileManagedStateTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file_test_states', 'file'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests if the managed file element is correctly hidden using states.
   */
  public function testFileStateVisible(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('file-test-states-form');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $field_initially_visible = $page->findField('files[managed_file_initially_visible]');
    $field_initially_hidden = $page->findField('files[managed_file_initially_hidden]');
    $field_initially_optional = $page->findField('files[managed_file_initially_optional]');
    $field_in_details = $page->findField('files[managed_file_in_details]');
    $textfield_in_details = $page->findField('textfield_in_details');
    $required_label = 'label.form-required[for="edit-managed-file-initially-optional-upload"]';

    // Check that the initially visible managed file is visible.
    $this->assertTrue($field_initially_visible->isVisible());

    // Check that the initially hidden managed file is hidden.
    $this->assertFalse($field_initially_hidden->isVisible());

    // Check that the initially optional managed file is optional and that its
    // label has no required marker.
    $this->assertFalse($field_initially_optional->hasAttribute('required'));
    $assert_session->elementNotExists('css', $required_label);

    // Check that a hidden managed file inside a details element does not hide
    // the details element or its other children.
    $this->assertFalse($field_in_details->isVisible());
    $this->assertTrue($textfield_in_details->isVisible());

    // Toggle the fields.
    $page->findField('toggle')->click();

    // Check that the initially visible managed file is now hidden.
    $this->assertFalse($field_initially_visible->isVisible());

    // Check that the initially hidden managed file is now visible.
    $this->assertTrue($field_initially_hidden->isVisible());

    // Check that the initially optional managed file is now required and that
    // its label has the required marker.
    $this->assertTrue($field_initially_optional->hasAttribute('required'));
    $assert_session->elementExists('css', $required_label);

    // Check that the managed file inside the details element is now visible
    // and that the details element is unchanged.
    $this->assertTrue($field_in_details->isVisible());
    $this->assertTrue($textfield_in_details->isVisible());

    // Toggle back and check the details element is still visible.
    $page->findField('toggle')->click();
    $this->assertFalse($field_in_details->isVisible());
    $this->assertTrue($textfield_in_details->isVisible());
  }

}
