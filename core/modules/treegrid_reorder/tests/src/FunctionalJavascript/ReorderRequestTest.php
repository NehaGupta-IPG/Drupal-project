<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\treegrid_reorder_test\Controller\TreegridReorderTestController;
use Drupal\treegrid_reorder_test\TestTreeMoveHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the requests a reorder consumer sends: the CSRF token and the headers.
 *
 * Runs against the treegrid_reorder_test module, whose move route requires the
 * X-CSRF-Token header.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
final class ReorderRequestTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_reorder',
    'treegrid_reorder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A rendered token is used as it is, and /session/token is never requested.
   */
  public function testRenderedTokenNeedsNoFetch(): void {
    \Drupal::state()->set(TreegridReorderTestController::PAGE_CSRF, TRUE);
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $this->moveFirstItemDown();
    $this->assertTopLevelOrder(['2', '1', '3', '4']);
    $this->assertSame(0, $this->sessionTokenRequests());
  }

  /**
   * Without the attribute the token is fetched once.
   */
  public function testMissingAttributeFallsBackToOneFetch(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $this->moveFirstItemDown();
    $this->assertTopLevelOrder(['2', '1', '3', '4']);
    $this->assertSame(1, $this->sessionTokenRequests());
  }

  /**
   * An anonymous user moves without any token being requested.
   */
  public function testAnonymousUserNeedsNoToken(): void {
    foreach ([TRUE, NULL] as $render_attribute) {
      \Drupal::state()->set(TreegridReorderTestController::PAGE_CSRF, $render_attribute);
      \Drupal::state()->delete(TestTreeMoveHandler::STATE_KEY);
      $this->openPage();

      $this->moveFirstItemDown();
      $this->assertTopLevelOrder(['2', '1', '3', '4']);
      $this->assertSame(0, $this->sessionTokenRequests());
    }
  }

  /**
   * A wrong token is an ordinary failure and is not retried.
   */
  public function testWrongTokenFailsOnceWithoutRetry(): void {
    \Drupal::state()->set(TreegridReorderTestController::PAGE_CSRF, 'wrong-token');
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();
    $this->countPosts();

    $this->moveFirstItemDown();
    $this->assertJsCondition('window.requestTest.posts >= 1');
    usleep(500000);

    $this->assertSame(1, $this->getSession()->evaluateScript('return window.requestTest.posts;'));
    $this->assertSame(0, $this->sessionTokenRequests());
    $this->assertTopLevelOrder(['1', '2', '3', '4']);
  }

  /**
   * A move carries the rendered token through BigPipe.
   */
  public function testRenderedTokenThroughBigPipe(): void {
    \Drupal::service('module_installer')->install(['big_pipe']);
    \Drupal::state()->set(TreegridReorderTestController::PAGE_CSRF, TRUE);
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $this->moveFirstItemDown();
    $this->assertTopLevelOrder(['2', '1', '3', '4']);
    $this->assertSame(0, $this->sessionTokenRequests());
  }

  // Helpers.

  /**
   * Loads the test page and waits for the consumer to be wired.
   */
  private function openPage(): void {
    $this->drupalGet('/treegrid-reorder-test');
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'drupal-treegrid[data-test-reorder-wired]'));
  }

  /**
   * Sends Shift+ArrowDown to item 1, moving it below item 2.
   */
  private function moveFirstItemDown(): void {
    $this->getSession()->executeScript(<<<'JS'
      const row = document.querySelector('tr[role="row"][data-id="1"]');
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'ArrowDown', shiftKey: true, bubbles: true, cancelable: true,
      }));
    JS);
  }

  /**
   * Counts the POST requests the page sends in window.requestTest.posts.
   */
  private function countPosts(): void {
    $this->getSession()->executeScript(<<<'JS'
      window.requestTest = { posts: 0 };
      const originalFetch = window.fetch;
      window.fetch = function (url, options) {
        if (options && options.method === 'POST') {
          window.requestTest.posts += 1;
        }
        return originalFetch.apply(this, arguments);
      };
    JS);
  }

  /**
   * How many requests the page made to /session/token, since it loaded.
   */
  private function sessionTokenRequests(): int {
    return (int) $this->getSession()->evaluateScript(
      "return performance.getEntriesByType('resource').filter((entry) => entry.name.includes('/session/token')).length;",
    );
  }

  /**
   * Asserts the persisted top-level order, polling for the move POST.
   *
   * @param string[] $expected
   *   The expected top-level item IDs, in order.
   */
  private function assertTopLevelOrder(array $expected): void {
    $state = \Drupal::state();
    $handler = new TestTreeMoveHandler($state);
    for ($i = 0; $i < 30; $i++) {
      $state->resetCache();
      if ($handler->levelIds('') === $expected) {
        return;
      }
      usleep(100000);
    }
    $state->resetCache();
    $this->assertSame($expected, $handler->levelIds(''));
  }

}
