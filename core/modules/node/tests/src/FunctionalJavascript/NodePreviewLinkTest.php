<?php

declare(strict_types=1);

namespace Drupal\Tests\node\FunctionalJavascript;

use Drupal\Core\Url;
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
  protected static $modules = ['node', 'filter', 'node_preview_embed_test'];

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
   * Tests that link protection only covers the embedded preview.
   */
  public function testEmbeddedPreviewLinks(): void {
    $assertSession = $this->assertSession();
    $host_url = Url::fromRoute('node_preview_embed_test.host', [
      'node_preview' => $this->createPreview(),
    ]);

    // Links outside the embedded preview are left alone.
    $this->drupalGet($host_url);
    $this->embedPreview();
    $this->clickLink('Outside link');
    $assertSession->pageTextNotContains('Leave preview?');
    $this->assertStringEndsWith('/outside', $this->getUrl());

    // Links inside it still prompt before navigating away.
    $this->drupalGet($host_url);
    $this->embedPreview();
    $this->clickLink('Inside link');
    $assertSession->pageTextContains('Leave preview?');
    $this->click('button:contains("Leave preview")');
    $this->assertStringEndsWith('/inside', $this->getUrl());
  }

  /**
   * Previews an unsaved node and returns the UUID it was stored under.
   *
   * @return string
   *   The UUID of the previewed node.
   */
  protected function createPreview(): string {
    $this->drupalGet('node/add/test');
    $this->submitForm([
      'title[0][value]' => 'Test embedded preview',
      'body[0][value]' => '<a href="/inside">Inside link</a>',
    ], 'Preview');
    $this->assertSession()->addressMatches('#/node/preview/[^/]+/full$#');
    return basename(dirname($this->getUrl()));
  }

  /**
   * Loads the preview into the host page over AJAX.
   */
  protected function embedPreview(): void {
    $this->clickLink('Embed preview');
    $this->assertNotNull($this->assertSession()->waitForElementVisible(
      'css',
      '#node-preview-embed-wrapper .node-preview-container',
    ));
  }

}
