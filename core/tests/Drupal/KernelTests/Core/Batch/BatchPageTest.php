<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Batch;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests _batch_page() operation routing.
 *
 * @see https://www.drupal.org/project/drupal/issues/2879023
 */
#[Group('Batch')]
#[RunTestsInSeparateProcesses]
class BatchPageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once $this->root . '/core/includes/batch.inc';
    require_once $this->root . '/core/includes/form.inc';
  }

  /**
   * Prepares a batch with a single no-op operation for testing.
   *
   * Uses batch_set() and _batch_populate_queue() to build a real batch
   * structure with a working queue, then adds the process metadata that
   * batch_process() would normally supply.
   */
  protected function prepareBatch(): void {
    // Reset any previous batch state.
    $batch = &batch_get();
    $batch = NULL;

    batch_set([
      'operations' => [
        [static::class . ':noop', []],
      ],
    ]);

    $batch = &batch_get();
    $batch += [
      'current_set' => 0,
      'progressive' => TRUE,
      'running' => TRUE,
      'id' => 1,
      'url' => Url::fromRoute('system.batch_page.html'),
      'error_message' => '',
    ];

    _batch_populate_queue($batch, 0);
  }

  /**
   * Tests that a JSON request with op=do_nojs is corrected to op=do.
   *
   * Reverse proxies with query string sorting can reorder and deduplicate
   * query parameters. When batch.js appends "&op=do" to a URI that already
   * contains "op=do_nojs", the proxy may drop the duplicate,
   * leaving only op=do_nojs on what is actually an AJAX request. The server
   * should recognize that a JSON request never needs the no-JS fallback.
   */
  public function testDoNojsCorrectedForJsonRequest(): void {
    $this->prepareBatch();

    $request = Request::create('/batch', 'GET', [
      'id' => 1,
      'op' => 'do_nojs',
    ]);
    $request->setRequestFormat('json');

    $response = _batch_page($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);
    $this->assertTrue($data['status']);
    $this->assertArrayHasKey('percentage', $data);
  }

  /**
   * Tests that an HTML request with op=do_nojs is not altered.
   *
   * The do_nojs path renders an HTML progress page with a meta refresh tag
   * for browsers without JavaScript. This must remain intact.
   */
  public function testDoNojsPreservedForHtmlRequest(): void {
    $this->prepareBatch();

    $request = Request::create('/batch', 'GET', [
      'id' => 1,
      'op' => 'do_nojs',
    ]);
    $request->setRequestFormat('html');

    $response = _batch_page($request);

    $this->assertIsArray($response);
    $this->assertArrayHasKey('content', $response);
  }

  /**
   * Batch operation callback that does nothing.
   */
  public static function noop(array &$context): void {
    $context['finished'] = 1;
  }

}
