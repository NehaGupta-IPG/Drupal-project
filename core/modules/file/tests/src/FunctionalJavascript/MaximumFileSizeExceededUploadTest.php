<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\Component\Utility\Bytes;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests uploading a file that exceeds the maximum POST size.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class MaximumFileSizeExceededUploadTest extends WebDriverTestBase {

  use FileFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'file'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The original value of the 'display_errors' PHP configuration option.
   *
   * @var string
   *
   * @todo Remove this when issue #2905597 is fixed.
   * @see https://www.drupal.org/node/2905597
   */
  protected $originalDisplayErrorsValue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->container->get('file_system');

    // Create the Article node type.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Attach a file field to the node type.
    $field_settings = ['file_extensions' => 'bin txt'];
    $this->createFileField('field_file', 'node', 'article', [], $field_settings);

    // Log in as a content author who can create Articles.
    $this->user = $this->drupalCreateUser([
      'access content',
      'create article content',
    ]);
    $this->drupalLogin($this->user);

    // Disable the displaying of errors, so that the AJAX responses are not
    // contaminated with error messages about exceeding the maximum POST size.
    // @todo Remove this when issue #2905597 is fixed.
    // @see https://www.drupal.org/node/2905597
    $this->originalDisplayErrorsValue = ini_set('display_errors', '0');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Restore the displaying of errors to the original value.
    // @todo Remove this when issue #2905597 is fixed.
    // @see https://www.drupal.org/node/2905597
    ini_set('display_errors', $this->originalDisplayErrorsValue);

    parent::tearDown();
  }

  /**
   * Tests that uploading files exceeding post_max_size are handled correctly.
   */
  public function testUploadFileExceedingMaximumFileSize(): void {
    $session = $this->getSession();
    $page = $session->getPage();

    // Create a test file that exceeds the maximum POST size with 1 kilobyte.
    $post_max_size = (int) Bytes::toNumber(ini_get('post_max_size'));
    $invalid_file = 'public://exceeding_post_max_size.bin';
    $handle = fopen($invalid_file, 'wb');
    fseek($handle, $post_max_size + 1024);
    fwrite($handle, '0');
    fclose($handle);

    // Go to the node creation form and try to upload the test file.
    $this->drupalGet('node/add/article');

    // Attach the file to the managed file input. Do NOT trigger
    // change.fileValidate here: when the upload is rejected due to POST size,
    // browsers may not populate input.files, and client-side validators that
    // assume files[0] exists can throw.
    $page->attachFileToField("files[field_file_0]", $this->fileSystem->realpath($invalid_file));

    // Wait for some error UI to appear. Depending on how the AJAX upload fails,
    // Drupal may show:
    // - a global error message (.messages--error),
    // - an inline file.js error (.file-upload-js-error),
    // - or an error message inside the widget
    // (.js-form-managed-file .messages--error).
    $this->assertSession()->waitForElementVisible('css', '.messages--error, .file-upload-js-error');

    $this->assertSession()->pageTextMatches('/(maximum file size|exceed|could not be uploaded|unrecoverable error|upload)/i');

    // Ensure the upload did not succeed: the remove button should not appear.
    $this->assertSession()->elementNotExists('named', ['id_or_name', 'field_file_0_remove_button']);

    // Now upload a valid file and check that the error state can be recovered.
    $valid_file = $this->generateFile('not_exceeding_post_max_size', 8, 8);
    $page->attachFileToField("files[field_file_0]", $this->fileSystem->realpath($valid_file));

    // The remove button should appear when the upload succeeds.
    $this->assertSession()->waitForElement('named', ['id_or_name', 'field_file_0_remove_button']);

    // Any prior error UI from the failed attempt should be gone
    // (or at least the inline file.js error should be gone).
    $this->assertSession()->elementNotExists('css', '.file-upload-js-error');
  }

}
