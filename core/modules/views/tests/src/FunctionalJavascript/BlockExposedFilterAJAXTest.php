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
  protected static $modules = ['node', 'user', 'views', 'block', 'views_test_config'];

  /**
   * The views to use for testing.
   *
   * @var string[]
   */
  public static $testViews = [
    'test_block_exposed_ajax',
    'test_block_exposed_ajax2',
    'test_block_exposed_ajax_with_page',
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
      'administer site configuration',
      'access content overview',
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
   * Tests that clicking filter doesn't unset sort.
   */
  public function testSortPersistence(): void {
    $this->drupalPlaceBlock('views_exposed_filter_block:block_exposed_ajax2-page_1');
    $this->drupalGet('/test-exposed-block');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    // Enable sort by title.
    $page->clickLink('sort by Title');
    $assert_session->waitForElement('css', '#view-title-table-column.is-active');
    $this->assertStringContainsString('sort=asc', $this->getUrl());
    $assert_session->elementsCount('css', 'td.views-field-title', 2);
    $page->selectFieldOption('Items per page', '3');
    $page->pressButton('Filter');
    // Wait for the filter to apply and three rows of nodes to be visible.
    $has_three = $page->waitFor(10, function () use ($page) {
      $title_rows = $page->findAll('css', 'td.views-field-title');
      return count($title_rows) === 3;
    });
    $this->assertTrue($has_three);
    // Assert that sort by title is still present.
    $assert_session->elementExists('css', '#view-title-table-column.is-active');
    $this->assertStringContainsString('sort=asc', $this->getUrl());
  }

}
