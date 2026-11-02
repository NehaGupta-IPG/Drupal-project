<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests ajax pager requests in embedded views.
 *
 * @group views
 */
class EmbeddedViewPaginationAJAXTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'views_test_config', 'views_test_ajax_subscriber'];

  /**
   * @var array
   * Test Views to enable.
   */
  public static array $testViews = ['test_content_ajax', 'test_view_area_ajax'];

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

    // Create a Content type and eleven test nodes.
    $this->createContentType(['type' => 'page']);
    for ($i = 1; $i <= 55; $i++) {
      $this->createNode(['title' => 'Node ' . $i . ' content', 'changed' => $i * 1000]);
    }

    // Create a user privileged enough to view content.
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access content overview',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Checks if embedded views send pager requests as themselves and not as enclosing view.
   *
   * @see \Drupal\views_test_ajax_subscriber\EventSubscriber\EventSubscriberViewsTestAjaxSubscriberEvent
   */
  public function testPaginationInEmbeddedAjaxView(): void {
    $this->drupalGet('test-view-area-ajax');

    $session_assert = $this->assertSession();

    /*
     * Tell the event subscriber to expect a pager request sent by the
     * test_content_ajax view.
     */
    \Drupal::state()->set('views_test_ajax_subscriber', 'test_content_ajax');
    $session_assert->waitForLink('Next ›')->click();
  }

}
