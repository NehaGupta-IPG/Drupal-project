<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the block exposed overrides in AJAX requests.
 *
 * @group views
 */
class BlockOverridesAJAXTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'views_test_config', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test Views to enable.
   *
   * @var array
   */
  public static $testViews = ['content_block_overrides_ajax_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ViewTestData::createTestViews(self::class, ['views_test_config']);

    // Create a Content type and 21 test nodes.
    $this->createContentType(['type' => 'page']);
    for ($i = 1; $i <= 21; $i++) {
      $this->createNode(['title' => 'Node ' . $i . ' content', 'created' => $i * 1000]);
    }

    // Place the block on the page.
    $settings = [
      'region' => 'content',
      // The view has 10 items per page configured, and here we will test that
      // this override of 5 items per page works in all scenarios.
      'items_per_page' => 5,
    ];
    $this->drupalPlaceBlock('views_block:content_block_overrides_ajax_test-block_1', $settings);

    // Create a user privileged enough to view content.
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access content overview',
      'administer blocks',
      'bypass node access',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests if block overrides are persisted through AJAX requests.
   */
  public function testBlockOverridesAjax(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/1');

    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    $rows = $page->findAll('css', 'tbody tr');
    $this->assertCount(5, $rows);
    $this->assertEquals('Node 1 content', $rows[0]->getText());

    // Navigating back and forth using the pager links preserves the number of
    // items per page.
    $this->clickLink('Go to page 2');
    $assert_session->assertWaitOnAjaxRequest();
    $rows = $page->findAll('css', 'tbody tr');
    $this->assertCount(5, $rows);
    $this->assertEquals('Node 6 content', $rows[0]->getText());
    $this->clickLink('Go to page 1');
    $assert_session->assertWaitOnAjaxRequest();
    $rows = $page->findAll('css', 'tbody tr');
    $this->assertCount(5, $rows);
    $this->assertEquals('Node 1 content', $rows[0]->getText());

    // Using the exposed filter also preserves the number of items per page.
    $page->fillField('Title', 'Node');
    $page->pressButton('Apply');
    $assert_session->assertWaitOnAjaxRequest();
    $rows = $page->findAll('css', 'tbody tr');
    $this->assertCount(5, $rows);
    $this->assertEquals('Node 1 content', $rows[0]->getText());

    // After using the exposed filter and using the pager again, it still works.
    $this->clickLink('Go to page 2');
    $assert_session->assertWaitOnAjaxRequest();
    $rows = $page->findAll('css', 'tbody tr');
    $this->assertCount(5, $rows);
    $this->assertEquals('Node 6 content', $rows[0]->getText());
  }

}
