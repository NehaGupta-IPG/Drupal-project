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
  protected static $modules = ['node', 'views', 'block', 'views_test_config', 'views_test_exposed_form'];

  /**
   * The views to use for testing.
   *
   * @var string[]
   */
  public static $testViews = [
    'test_block_exposed_ajax',
    'test_block_exposed_ajax_with_page',
    'test_page_with_ajax_exposed_form_in_block',
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
   * Tests AJAX refreshes the exposed form when it is exposed in a block.
   */
  public function testExposedFormInBlockAjaxRefresh(): void {
    $this->drupalPlaceBlock('views_exposed_filter_block:test_page_with_ajax_exposed_form_in_block-page_1');
    $this->drupalGet('test_page_with_ajax_exposed_form_in_block');
    $page = $this->getSession()->getPage();

    $html = $page->getHtml();
    $first_form_build_time = trim($page->find('xpath', '//div[contains(@class, "form-item-form-build-time")]')->getText());
    self::assertNotEmpty($first_form_build_time, 'The form build time found on the page.');
    $this->assertStringContainsString('Page A', $html);
    $this->assertStringContainsString('Page B', $html);
    $this->assertStringContainsString('Article A', $html);

    $this->submitForm(['type' => 'page'], 'Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $second_form_build_time = trim($page->find('xpath', '//div[contains(@class, "form-item-form-build-time")]')->getText());
    self::assertNotEmpty($second_form_build_time, 'The form build time found on the page.');
    self::assertNotEquals($first_form_build_time, $second_form_build_time, 'The form was successfully rebuilt.');
  }

}
