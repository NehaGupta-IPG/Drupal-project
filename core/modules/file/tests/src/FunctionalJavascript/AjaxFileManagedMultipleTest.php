<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ajax upload to managed files.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class AjaxFileManagedMultipleTest extends WebDriverTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file_test', 'file', 'file_module_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests if managed file form element works well with multiple files upload.
   */
  public function testMultipleFilesUpload(): void {
    $file_system = \Drupal::service('file_system');
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $page = $this->getSession()->getPage();

    $this->drupalGet(Url::fromRoute('file_module_test.managed_test', ['multiple' => TRUE]));

    $paths = [];
    foreach (array_slice($this->drupalGetTestFiles('image'), 0, 2) as $image) {
      $paths[] = $image->filename;
      $page->attachFileToField('files[nested_file][]', $file_system->realpath($image->uri));
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    // Save entire form.
    $page->pressButton('Save');

    $this->assertSession()->pageTextContains('The file ids are 1,2.');
    $this->assertCount(2, $file_storage->loadByProperties(['filename' => $paths]));
  }

  /**
   * Tests anonymous file upload via AJAX with unauthorized file ID rejection.
   */
  public function testAnonymousFileUploadValidation(): void {
    $file_system = \Drupal::service('file_system');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    \Drupal::state()->set('file_test_private_file_forbidden', TRUE);

    // 1. First anonymous user visits form with upload private://unauthorized.
    $this->drupalGet('/file-test/private-unauthorized');

    // Upload a private file as an anonymous user via AJAX.
    $paths = [];
    foreach (array_slice($this->drupalGetTestFiles('image'), 0, 2) as $image) {
      $paths[] = $image->filename;
      $page->attachFileToField('files[test_file][]', $file_system->realpath($image->uri));
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    // The anonymous user can see their uploaded file in the AJAX response.
    foreach ($paths as $filename) {
      $this->assertNotEmpty($assert_session->waitForText($filename));
    }
    $remove_button = $assert_session->waitForButton('Remove selected');
    $this->assertNotNull($remove_button);

    $fids_input = $page->find('css', 'input[name="test_file[fids]"]');
    $this->assertNotNull($fids_input);
    $uploaded_fids = explode(' ', $fids_input->getValue());
    $this->assertNotEmpty($uploaded_fids);
    $this->assertCount(2, $uploaded_fids);

    // Save the form as the first anonymous user.
    $page->pressButton('Save');
    $this->assertNotNull($assert_session->waitForElement('css', 'div[data-drupal-messages]'));
    $assert_session->pageTextContains('The file ids are ' . implode(',', $uploaded_fids) . '.');

    // Verify that first anonymous user can't access their own file.
    $first_files = File::loadMultiple($uploaded_fids);
    foreach ($first_files as $first_file) {
      $this->assertFalse($first_file->access('download'));
    }

    // 2. Start a new session to simulate another anonymous user.
    $this->getSession()->reset();

    // Verify other anonymous user cannot access the first user's file.
    $first_files = File::loadMultiple($uploaded_fids);
    foreach ($first_files as $first_file) {
      $this->assertFalse($first_file->access('download'));
    }

    // Second anonymous user visits the form with the first user's file ID.
    $this->drupalGet('/file-test/private-unauthorized/' . implode(',', $uploaded_fids));

    // The second anonymous user uploads their own file via AJAX.
    $second_paths = [];
    foreach (array_slice($this->drupalGetTestFiles('image'), 2, 2) as $image) {
      $second_paths[] = $image->filename;
      $page->attachFileToField('files[test_file][]', $file_system->realpath($image->uri));
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    // First user's file is not accessible and not rendered in AJAX response.
    foreach ($paths as $filename) {
      $assert_session->pageTextNotContains($filename);
    }

    // The second user's uploaded file IS rendered in the AJAX response.
    foreach ($second_paths as $filename) {
      $this->assertNotEmpty($assert_session->waitForText($filename));
    }

    $fids_input2 = $page->find('css', 'input[name="test_file[fids]"]');
    $this->assertNotNull($fids_input2);
    $uploaded_fids2 = explode(' ', $fids_input2->getValue());

    // Save the form as the second anonymous user.
    $page->pressButton('Save');
    $this->assertNotNull($assert_session->waitForElement('css', 'div[data-drupal-messages]'));

    // Verify only second user's file ID was saved, not first user's file.
    $assert_session->pageTextContains('The file ids are ' . implode(',', $uploaded_fids2) . '.');
    $assert_session->pageTextNotContains('The file ids are ' . implode(',', $uploaded_fids) . '.');
    \Drupal::state()->set('file_test_private_file_forbidden', FALSE);
  }

}
