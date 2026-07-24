<?php

declare(strict_types=1);

namespace Drupal\Tests\big_pipe\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the redirect destination is not cached across requests.
 *
 * BigPipe adds a no-JS detection <noscript> meta refresh whose URL carries the
 * current request's destination.
 *
 * @see \Drupal\big_pipe\Hook\BigPipeHooks::pageAttachments()
 * @see \Drupal\big_pipe\Controller\BigPipeController::setNoJsCookie()
 */
#[Group('big_pipe')]
#[RunTestsInSeparateProcesses]
class NoJsDestinationCacheTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'big_pipe',
    'dynamic_page_cache',
    'big_pipe_regression_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the no-JS detection destination follows the current request.
   */
  public function testNoJsDetectionDestinationIsNotCached(): void {
    // A route whose page shell is plainly cacheable and bubbles no url cache
    // context, so Dynamic Page Cache stores a single shell shared across
    // requests with differing query arguments.
    $path = '/big_pipe_regression_test/2678662';

    // Prevent the test browser from auto-following the no-JS meta refresh. That
    // would visit big_pipe/no-js, set the big_pipe_nojs cookie, and suppress the
    // meta refresh on later requests. This must be set before drupalLogin(),
    // which follows redirects.
    $this->maximumMetaRefreshCount = 0;

    // BigPipe only performs no-JS detection when a session exists.
    $this->drupalLogin($this->drupalCreateUser());

    // First request warms the Dynamic Page Cache entry for the page shell and
    // emits a no-JS meta refresh that points to this request's destination.
    $this->drupalGet($path, ['query' => ['destination' => '/first-destination']]);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'MISS');
    $this->assertSession()->responseContains('<noscript><meta http-equiv="Refresh" content="0; URL=' . base_path() . 'big_pipe/no-js?destination=/first-destination" />');

    // Second request to the same route with a different destination. The page
    // shell is served from the Dynamic Page Cache, proving both requests share
    // one cache entry.
    $this->drupalGet($path, ['query' => ['destination' => '/second-destination']]);
    $this->assertSession()->responseHeaderEquals('X-Drupal-Dynamic-Cache', 'HIT');

    // The no-JS meta refresh must carry the second request's destination.
    $this->assertStringContainsString(
      '<noscript><meta http-equiv="Refresh" content="0; URL=' . base_path() . 'big_pipe/no-js?destination=/second-destination" />',
      $this->getSession()->getPage()->getContent(),
      "The no-JS detection meta refresh must point to the current request's destination (/second-destination)."
    );
  }

}
