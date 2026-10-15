<?php

declare(strict_types=1);

namespace Drupal\Tests\file\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the messages of a file widget's AJAX upload in the admin theme.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileUploadAjaxMessageTest extends WebDriverTestBase {

  use FileFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'file'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * The name of the file field on the article form.
   */
  protected string $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fieldName = $this->randomMachineName();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    // A size limit the server checks. A wrong extension would be rejected by
    // file.js in the browser, without an AJAX request.
    $this->createFileField($this->fieldName, 'node', 'article', [], [
      'file_extensions' => 'txt',
      'max_filesize' => '1 KB',
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create article content',
    ]));
  }

  /**
   * Uploads a text file into the field and waits for the AJAX response.
   *
   * @param bool $too_big
   *   TRUE for a file over the 1 KB limit (rejected by the server), FALSE for
   *   a small one (accepted).
   */
  protected function uploadTestFile(bool $too_big): void {
    $uri = $too_big ? $this->generateFile('too_big', 64, 40, 'text') : $this->generateFile('small', 16, 2, 'text');
    $path = $this->container->get('file_system')->realpath($uri);
    $this->getSession()->getPage()->attachFileToField('files[' . $this->fieldName . '_0]', $path);
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Waits until the size error is shown a given number of times.
   *
   * Counts the error text, not a class: the server-side and JavaScript
   * message templates of a theme can use different classes.
   *
   * @param int $count
   *   The expected number of times the error is shown.
   *
   * @return int
   *   The number of times it is shown.
   */
  protected function waitForErrorCount(int $count): int {
    $page = $this->getSession()->getPage();
    $shown = fn() => substr_count($page->getText(), 'exceeding the maximum file size');
    $page->waitFor(10, fn() => $shown() === $count);
    return $shown();
  }

  /**
   * Tests the error's place, and that a later upload replaces or clears it.
   */
  public function testErrorIsReplaced(): void {
    $this->drupalGet('node/add/article');
    $this->uploadTestFile(TRUE);
    $this->assertSame(1, $this->waitForErrorCount(1));

    // The error is shown inside the widget, not in the page's message area.
    $widget = $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-' . str_replace('_', '-', $this->fieldName) . '-wrapper"]');
    $this->assertStringContainsString('exceeding the maximum file size', $widget->getText());

    // A second rejected upload shows one error, not two.
    $this->uploadTestFile(TRUE);
    $this->assertSame(1, $this->waitForErrorCount(1));

    // An accepted upload removes the error.
    $this->uploadTestFile(FALSE);
    $this->assertSession()->waitForElement('named', ['id_or_name', $this->fieldName . '_0_remove_button']);
    $this->assertSame(0, $this->waitForErrorCount(0));
  }

  /**
   * Tests that the error is announced to assistive technology.
   */
  public function testErrorIsAnnounced(): void {
    $this->drupalGet('node/add/article');
    $this->uploadTestFile(TRUE);
    $this->waitForErrorCount(1);

    // Drupal.announce() writes into #drupal-live-announce after a short delay.
    // The region is visually hidden, so its text is read with JavaScript.
    $script = "(document.getElementById('drupal-live-announce') || {}).textContent || ''";
    $announced = $this->getSession()->getPage()->waitFor(10, fn() => str_contains($this->getSession()->evaluateScript($script), 'exceeding the maximum file size'));
    $this->assertTrue($announced, 'The upload error is announced through Drupal.announce().');
  }

  /**
   * Tests that the theme's message markup is not filtered out.
   *
   * The theme renders a dismiss button in each message. Messages placed in
   * the replaced markup lose it to the admin XSS filter.
   */
  public function testErrorKeepsThemeMarkup(): void {
    $this->drupalGet('node/add/article');
    $this->uploadTestFile(TRUE);
    $this->assertSame(1, $this->waitForErrorCount(1));
    $error = $this->getSession()->getPage()->find('css', '.messages--error');
    $this->assertNotNull($error);
    $this->assertNotNull($error->find('css', 'button.js-message-button-hide'), 'The error keeps its dismiss button.');
  }

  /**
   * Tests that the file input keeps its error state after a rejected upload.
   *
   * The message moves out of the replaced markup, but the field itself must
   * still be marked invalid and described, for assistive technology.
   */
  public function testFieldKeepsErrorState(): void {
    $this->drupalGet('node/add/article');
    $this->uploadTestFile(TRUE);
    $this->assertSame(1, $this->waitForErrorCount(1));
    $input = $this->assertSession()->elementExists('css', 'input[name="files[' . $this->fieldName . '_0]"]');
    $this->assertSame('true', $input->getAttribute('aria-invalid'), 'The file input is still marked invalid.');
    $this->assertNotEmpty($input->getAttribute('aria-describedby'), 'The file input is still described by its help text.');
  }

}
