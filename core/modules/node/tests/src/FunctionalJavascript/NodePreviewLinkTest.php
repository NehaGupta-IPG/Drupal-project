<?php

declare(strict_types=1);

namespace Drupal\Tests\node\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the JavaScript prevention of navigation away from node previews.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodePreviewLinkTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ]);
    $filtered_html_format->save();

    $this->drupalCreateContentType(['type' => 'test']);

    $user = $this->drupalCreateUser([
      'access content',
      'edit own test content',
      'create test content',
      $filtered_html_format->getPermissionName(),
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests the behavior of clicking preview links.
   */
  public function testPreviewLinks(): void {
    $assertSession = $this->assertSession();
    $this->drupalGet('node/add/test');
    $this->submitForm([
      'title[0][value]' => 'Test anchor link',
      'body[0][value]' => '<a href="#foo">Anchor link</a>',
    ], 'Preview');
    $this->clickLink('Anchor link');
    $assertSession->pageTextNotContains('Leave preview?');
    $this->drupalGet('node/add/test');
    $this->submitForm([
      'title[0][value]' => 'Test normal link',
      'body[0][value]' => '<a href="/foo">Normal link</a>',
    ], 'Preview');
    $this->clickLink('Normal link');
    $assertSession->pageTextContains('Leave preview?');
    $this->click('button:contains("Leave preview")');
    $this->assertStringEndsWith('/foo', $this->getUrl());
    $this->drupalGet('node/add/test');
    $this->submitForm([
      'title[0][value]' => 'Test child element link',
      'body[0][value]' => '<a href="/foo" class="preview-child-element"><span>Child element link</span></a>',
    ], 'Preview');
    $this->getSession()->getPage()->find('css', '.preview-child-element span')->click();
    $assertSession->pageTextContains('Leave preview?');
    $this->click('button:contains("Leave preview")');
    $this->assertStringEndsWith('/foo', $this->getUrl());
  }

  /**
   * Tests leaving the preview when other document click handlers exist.
   *
   * Themes and modules may bind click handlers directly on the document. jQuery
   * reuses the same event object for these, so the clicked link must still be
   * followed rather than navigating to "undefined".
   */
  public function testLeavePreviewWithOtherDocumentClickHandler(): void {
    $assertSession = $this->assertSession();
    $cases = [
      '/foo' => ['<a href="/foo">Normal link</a>', 'a[href="/foo"]'],
      '/bar' => ['<a href="/bar"><span>Child element link</span></a>', 'a[href="/bar"] span'],
    ];
    foreach ($cases as $expected_path => [$body, $selector]) {
      $this->drupalGet('node/add/test');
      $this->submitForm([
        'title[0][value]' => 'Test other click handler',
        'body[0][value]' => $body,
      ], 'Preview');
      $this->getSession()->executeScript("jQuery(document).on('click', function () {});");
      $assertSession->waitForElementVisible('css', $selector)->click();
      $assertSession->waitForText('Leave preview?');
      $this->click('button:contains("Leave preview")');
      $this->assertTrue($this->getSession()->wait(5000, "window.location.pathname.endsWith('$expected_path')"));
    }
  }

}
