<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\FunctionalJavascript;

use Drupal\Core\Url;
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

  /**
   * A request carries the CSRF token and the attribute's revision.
   */
  public function testRequestSendsTokenAndRevision(): void {
    \Drupal::state()->set(TreegridReorderTestController::PAGE_REVISION, 'abc');
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $result = $this->requestEcho();

    $this->assertTrue($result['ok']);
    $this->assertSame(200, $result['status']);
    $this->assertTrue($result['sent']);
    $this->assertSame('abc', $result['body']['received']['revision']);
  }

  /**
   * Without the attribute no revision header is sent.
   */
  public function testNoRevisionHeaderWithoutAttribute(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $result = $this->requestEcho();

    $this->assertTrue($result['ok']);
    $this->assertNull($result['body']['received']['revision']);
  }

  /**
   * A response's revision header replaces the attribute.
   */
  public function testResponseRevisionReplacesAttribute(): void {
    \Drupal::state()->set(TreegridReorderTestController::PAGE_REVISION, 'one');
    \Drupal::state()->set(TreegridReorderTestController::REVISION, 'one');
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $this->assertTrue($this->requestEcho()['ok']);

    \Drupal::state()->resetCache();
    $stored = \Drupal::state()->get(TreegridReorderTestController::REVISION);
    $this->assertNotSame('one', $stored);
    $this->assertSame($stored, $this->revisionAttribute());
  }

  /**
   * A busy response leaves the revision and the tree's state alone.
   */
  public function testBusyResponseLeavesRevisionAlone(): void {
    \Drupal::state()->set(TreegridReorderTestController::PAGE_REVISION, 'one');
    \Drupal::state()->set(TreegridReorderTestController::BUSY, TRUE);
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $result = $this->requestEcho();

    $this->assertFalse($result['ok']);
    $this->assertSame(409, $result['status']);
    $this->assertSame('busy', $result['code']);
    $this->assertSame('one', $this->revisionAttribute());
    $this->assertNull($this->needsReloadAttribute());
  }

  /**
   * A stale response blocks later writes but not reads.
   */
  public function testStaleResponseBlocksWritesNotReads(): void {
    \Drupal::state()->set(TreegridReorderTestController::PAGE_REVISION, 'mine');
    \Drupal::state()->set(TreegridReorderTestController::REVISION, 'theirs');
    \Drupal::state()->set(TreegridReorderTestController::ECHO_DELAY, 400);
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $echo_url = Url::fromRoute('treegrid_reorder_test.echo')->toString();
    $children_url = Url::fromRoute('treegrid_reorder_test.children')->toString();
    $this->runAsync(<<<JS
      const element = document.querySelector('drupal-treegrid');
      const reloads = [];
      element.addEventListener('treegrid-reorder:needs-reload', (event) => reloads.push(event.detail.reason));
      const first = Drupal.treegridReorder.request(element, '$echo_url', { body: {} });
      const second = Drupal.treegridReorder.request(element, '$echo_url', { body: {} });
      const results = await Promise.all([first, second]);
      const read = await Drupal.treegridReorder.request(element, '$children_url', { method: 'GET' });
      return { results, reloads, read: { ok: read.ok, sent: read.sent, status: read.status } };
    JS);

    $result = $this->getSession()->evaluateScript('return window.requestTest.result;');
    $this->assertSame(409, $result['results'][0]['status']);
    $this->assertSame('stale', $result['results'][0]['code']);
    $this->assertTrue($result['results'][0]['sent']);
    $this->assertSame('stale', $result['results'][1]['code']);
    $this->assertSame(0, $result['results'][1]['status']);
    $this->assertFalse($result['results'][1]['sent']);
    $this->assertSame(['stale'], $result['reloads']);
    $this->assertSame('stale', $this->needsReloadAttribute());
    // The children response has no ok flag, so only its delivery is checked.
    $this->assertEquals(['ok' => FALSE, 'sent' => TRUE, 'status' => 200], $result['read']);
    \Drupal::state()->resetCache();
    $this->assertSame(1, \Drupal::state()->get(TreegridReorderTestController::ECHO_HITS));
  }

  /**
   * A network error resolves with status 0 and the request marked sent.
   */
  public function testNetworkErrorResolvesSent(): void {
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();

    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      return Drupal.treegridReorder.request(element, 'http://127.0.0.1:1/unreachable', { body: {} });
    JS);

    $result = $this->getSession()->evaluateScript('return window.requestTest.result;');
    $this->assertEquals(
      ['ok' => FALSE, 'status' => 0, 'code' => NULL, 'message' => NULL, 'body' => NULL, 'sent' => TRUE],
      $result,
    );
  }

  // Helpers.

  /**
   * Sends a POST through the request helper to the echo route.
   *
   * @return array<string, mixed>
   *   The helper's result.
   */
  private function requestEcho(): array {
    $echo_url = Url::fromRoute('treegrid_reorder_test.echo')->toString();
    $this->runAsync(<<<JS
      const element = document.querySelector('drupal-treegrid');
      return Drupal.treegridReorder.request(element, '$echo_url', { body: {} });
    JS);
    return $this->getSession()->evaluateScript('return window.requestTest.result;');
  }

  /**
   * Runs a script that returns a promise and stores its value.
   *
   * The value lands in window.requestTest.result.
   *
   * @param string $script
   *   The script body; it must return a promise.
   */
  private function runAsync(string $script): void {
    $this->getSession()->executeScript(
      'window.requestTest = Object.assign(window.requestTest || {}, { done: false, result: null });'
      . '(async function () {' . $script . '})().then(function (value) {'
      . ' window.requestTest.result = value; window.requestTest.done = true; });',
    );
    $this->assertJsCondition('window.requestTest.done === true');
  }

  /**
   * The element's current revision attribute.
   */
  private function revisionAttribute(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-revision');",
    );
  }

  /**
   * The element's needs-reload attribute.
   */
  private function needsReloadAttribute(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-needs-reload');",
    );
  }

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
