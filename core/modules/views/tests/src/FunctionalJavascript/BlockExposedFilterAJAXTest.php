<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the exposed filter ajax functionality in a block.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class BlockExposedFilterAJAXTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'block', 'views_test_config'];

  /**
   * The views to use for testing.
   *
   * @var string[]
   */
  public static $testViews = [
    'test_block_exposed_ajax',
    'test_block_exposed_ajax_with_page',
    'test_argument_exposed_filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ViewTestData::createTestViews(self::class, ['views_test_config']);
    $this->createContentType(['type' => 'page']);
    $this->createContentType(['type' => 'article']);
    $this->createNode(['title' => 'Page A']);
    $this->createNode(['title' => 'Page B']);
    $this->createNode(['title' => 'Article A', 'type' => 'article']);

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
    ]));
  }

  /**
   * Tests if exposed filtering and reset works with a views block and ajax.
   */
  public function testExposedFilteringAndReset(): void {
    $node = $this->createNode();
    $block = $this->drupalPlaceBlock('views_block:test_block_exposed_ajax-block_1');
    $this->drupalGet($node->toUrl());

    $page = $this->getSession()->getPage();

    // Ensure that the Content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page A', $html);
    $this->assertStringContainsString('Page B', $html);
    $this->assertStringContainsString('Article A', $html);

    // Filter by page type.
    $this->submitForm(['type' => 'page'], 'Apply');
    $this->assertSession()->waitForElementRemoved('xpath', '//*[text()="Article A"]');

    // Verify that only the page nodes are present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page A', $html);
    $this->assertStringContainsString('Page B', $html);
    $this->assertStringNotContainsString('Article A', $html);

    // Reset the form.
    $this->submitForm([], 'Reset');
    // Assert we are still on the node page.
    $html = $page->getHtml();
    // Repeat the original tests.
    $this->assertStringContainsString('Page A', $html);
    $this->assertStringContainsString('Page B', $html);
    $this->assertStringContainsString('Article A', $html);
    $this->assertSession()->addressEquals('node/' . $node->id());

    $block->delete();
    // Do the same test with a block that has a page display to test the user
    // is redirected to the page display.
    $this->drupalPlaceBlock('views_block:test_block_exposed_ajax_with_page-block_1');
    $this->drupalGet($node->toUrl());
    $this->submitForm(['type' => 'page'], 'Apply');
    $this->assertSession()->waitForElementRemoved('xpath', '//*[text()="Article A"]');
    $this->submitForm([], 'Reset');
    $this->assertSession()->addressEquals('some-path');
  }

  /**
   * Tests that when block's exposed filter is set, "More" links adopts that.
   */
  public function testMoreLinkQueryParameters(): void {
    $article = $this->createNode(['type' => 'article']);
    $basic_page = $this->createNode();
    $this->drupalPlaceBlock('views_block:test_argument_exposed_filter-block_1');
    $this->drupalGet('<front>');

    $assert = $this->assertSession();
    $assert->elementTextContains('css', '.test-argument-exposed-filter-block', $article->getTitle());
    $assert->linkByHrefExists('/test-argument-exposed-filter');

    $this->submitForm(['type' => 'article'], 'Apply');
    $assert->waitForElementRemoved('xpath', "//*[text()='{$basic_page->getTitle()}']");

    // Verify that only the article nodes are present.
    $html = $this->getSession()->getPage()->getHtml();
    $this->assertStringContainsString($article->getTitle(), $html);
    $assert->linkByHrefExists('test-argument-exposed-filter?type=article');

    // Place another block where "more" link points to custom path.
    $this->drupalPlaceBlock('views_block:test_argument_exposed_filter-block_2');
    $this->drupalGet('<front>');

    // Assert that custom "more" link URL is present on the page.
    $assert->elementTextContains('css', '.test-argument-exposed-filter-block-custom-url', $article->getTitle());
    $assert->linkByHrefExists('/test-example-url');

    $this->submitForm(['type' => 'article'], 'Apply');
    $assert->waitForElementRemoved('xpath', "//*[text()='{$basic_page->getTitle()}']");

    $html = $this->getSession()->getPage()->getHtml();
    $this->assertStringContainsString($article->getTitle(), $html);
    $assert->linkByHrefExists('test-example-url?type=article');
  }

}
