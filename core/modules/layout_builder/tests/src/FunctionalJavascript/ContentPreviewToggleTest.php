<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\system\Traits\OffCanvasTestTrait;

// cspell:ignore blocknodebundle testbody testlinks

/**
 * Tests toggling of content preview.
 *
 * @group layout_builder
 */
class ContentPreviewToggleTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;
  use LayoutBuilderSortTrait;
  use OffCanvasTestTrait;

  /**
   * Placeholder label for the links field block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $linksFieldPlaceholder;

  /**
   * Placeholder label for the body field block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $bodyFieldPlaceholder;

  /**
   * The full links field block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $linksBlock;

  /**
   * The full body field block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $bodyBlock;

  /**
   * The full search block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $linksBlockContent;

  /**
   * The content of the body field block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $bodyBlockContent;

  /**
   * The title label of the links field block.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected $linksFieldBlockLabel;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'block',
    'node',
    'contextual',
    'off_canvas_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'bundle_for_this_particular_test']);
    LayoutBuilderEntityViewDisplay::load('node.bundle_for_this_particular_test.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'access contextual links',
    ]));
  }

  /**
   * Tests the content preview toggle.
   */
  public function testContentPreviewToggle(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $links_field_placeholder_label = '"Links" field';
    $body_field_placeholder_label = '"Body" field';
    $content_preview_body_text = 'I should only be visible if content preview is enabled.';

    $this->createNode([
      'type' => 'bundle_for_this_particular_test',
      'body' => [
        [
          'value' => $content_preview_body_text,
        ],
      ],
    ]);

    // Open single item layout page.
    $this->drupalGet('node/1/layout');

    $links_field_placeholder_label = '"Links" field';
    $body_field_placeholder_label = '"Body" field';

    $this->linksFieldPlaceholder = $assert_session->elementExists('css', ".layout-builder-block__content-preview-placeholder-label:contains('$links_field_placeholder_label')");
    $this->bodyFieldPlaceholder = $assert_session->elementExists('css', ".layout-builder-block__content-preview-placeholder-label:contains('$body_field_placeholder_label')");
    $this->linksBlock = $assert_session->elementExists('css', '.block-extra-field-blocknodebundle-for-this-particular-testlinks');
    $this->bodyBlock = $assert_session->elementExists('css', '.block-field-blocknodebundle-for-this-particular-testbody');
    $this->linksBlockContent = $assert_session->elementExists('css', '.block-extra-field-blocknodebundle-for-this-particular-testlinks .layout-builder-block__content-preview-show');
    $this->bodyBlockContent = $assert_session->elementExists('css', '.block-field-blocknodebundle-for-this-particular-testbody .layout-builder-block__content-preview-show');
    $this->setBlockLabel('.block-extra-field-blocknodebundle-for-this-particular-testlinks', 'A block label for links');
    $this->linksFieldBlockLabel = $assert_session->elementExists('css', '.block-extra-field-blocknodebundle-for-this-particular-testlinks > h2');

    $this->assertContentPreviewEnabled();

    // Disable content preview.
    $this->assertTrue($page->hasCheckedField('layout-builder-content-preview'));
    $page->uncheckField('layout-builder-content-preview');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.layout-builder-block__content-preview-placeholder-label'));
    $this->assertContentPreviewDisabled();

    // Confirm that block content is not on page.
    $assert_session->pageTextNotContains($content_preview_body_text);
    $this->assertContextualLinks();

    // Check that content preview is still disabled on page reload.
    $this->getSession()->reload();
    $this->assertNotEmpty($assert_session->waitForElement('css', '.layout-builder-block__content-preview-placeholder-label'));
    $this->assertContentPreviewDisabled();
    $this->assertContextualLinks();

    // Confirm repositioning blocks works with content preview disabled.
    $this->assertOrderInPage([
      $this->linksBlock,
      $this->bodyBlock,
    ]);

    $region_content = '.layout__region--content';
    $links_block = ".block-extra-field-blocknodebundle-for-this-particular-testlinks";
    $body_block = ".block-field-blocknodebundle-for-this-particular-testbody";

    $assert_session->elementExists('css', $links_block . " div");
    $assert_session->elementExists('css', $body_block . " div");

    $this->sortableAfter($links_block, $body_block, $region_content);
    $assert_session->assertWaitOnAjaxRequest();

    // Check that the drag-triggered rebuild did not trigger content preview.
    $this->assertContentPreviewDisabled();

    // Check that drag successfully repositioned blocks.
    $this->assertOrderInPage([
      $this->bodyBlock,
      $this->linksBlock,
    ]);

    // Check if block position maintained after enabling content preview.
    $this->assertTrue($page->hasUncheckedField('layout-builder-content-preview'));
    $page->checkField('layout-builder-content-preview');
    $this->assertNotEmpty($assert_session->waitForText($content_preview_body_text));
    $assert_session->pageTextContains($content_preview_body_text);
    $this->assertNotEmpty($assert_session->waitForText('Placeholder for the "Links" field'));
    $this->assertContentPreviewEnabled();
    $this->assertOrderInPage([
      $this->bodyBlock,
      $this->linksBlock,
    ]);
  }

  /**
   * Enables and sets the block label.
   */
  protected function setBlockLabel($selector, $label): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->clickContextualLink($selector, 'Configure');
    $this->assertNotEmpty($assert_session->waitForElement('css', "#drupal-off-canvas"));
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->fillField('settings[label]', $label);
    $page->checkField('settings[label_display]');
    $page->pressButton('Update');
    $this->assertSession()->assertNoElementAfterWait('css', '#drupal-off-canvas', '10000');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Checks if contextual links are working properly.
   *
   * @internal
   */
  protected function assertContextualLinks(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->clickContextualLink('.block-field-blocknodebundle-for-this-particular-testbody', 'Configure');
    $this->waitForOffCanvasArea();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($this->assertSession()->waitForButton('Close'));
    $page->pressButton('Close');
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');
  }

  /**
   * Asserts that blocks in a given order in the page.
   *
   * @param string[] $items
   *   An ordered list of strings that should appear in the blocks.
   *
   * @internal
   */
  protected function assertOrderInPage(array $items): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $blocks = $page->findAll('css', '.layout-builder-block');

    // Confirm block order by comparing expected UUIDs to those found.
    $blocks_that_match_items = array_filter($blocks, function ($block) use ($items) {
      $block_uuid = $block->getAttribute('data-layout-block-uuid');
        return in_array($block_uuid, array_map(function ($item) {
          return $item->getAttribute('data-layout-block-uuid');
        }, $items), TRUE);
    });

    $this->assertCount(count($items), $blocks_that_match_items);
  }

  /**
   * Checks if content preview is disabled.
   */
  protected function assertContentPreviewDisabled(): void {
    $this->assertTrue($this->linksFieldPlaceholder->isVisible());
    $this->assertTrue($this->bodyFieldPlaceholder->isVisible());
    $this->assertFalse($this->bodyBlockContent->isVisible());
    $this->assertFalse($this->linksBlockContent->isVisible());
    $this->assertFalse($this->linksFieldBlockLabel->isVisible());
  }

  /**
   * Checks if content preview is enabled.
   */
  protected function assertContentPreviewEnabled(): void {
    $this->assertFalse($this->linksFieldPlaceholder->isVisible());
    $this->assertFalse($this->bodyFieldPlaceholder->isVisible());
    $this->assertTrue($this->linksBlockContent->isVisible());
    $this->assertTrue($this->linksFieldBlockLabel->isVisible());
  }

}
