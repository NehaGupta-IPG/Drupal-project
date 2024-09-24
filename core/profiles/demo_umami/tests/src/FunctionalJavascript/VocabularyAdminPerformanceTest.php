<?php

declare(strict_types=1);

namespace Drupal\Tests\demo_umami\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\PerformanceTestBase;

/**
 * Tests demo_umami profile performance on vocabulary admin page.
 *
 * @group OpenTelemetry
 * @requires extension apcu
 */
class VocabularyAdminPerformanceTest extends PerformanceTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'demo_umami';

  /**
   * Test vocabulary admin page performance with various cache permutations.
   */
  public function testPerformance(): void {
    $this->testColdCache();
    $this->testHotCache();
  }

  /**
   * Logs vocabulary admin page tracing data with a cold cache.
   */
  protected function testColdCache(): void {
    // @todo Chromedriver doesn't collect tracing performance logs for the very
    //   first request in a test, so warm it up.
    //   https://www.drupal.org/project/drupal/issues/3379750
    $this->drupalGet('user/login');
    $this->rebuildAll();
    $this->collectPerformanceData(function () {
      $this->drupalGet('admin/structure/taxonomy/manage/tags/overview');
    }, 'umamiVocabularyAdminPageColdCache');
    $this->assertSession()->pageTextContains('Baked');
  }

  /**
   * Logs vocabulary admin page tracing data with a hot cache.
   *
   * Hot here means that all possible caches are warmed.
   */
  protected function testHotCache(): void {
    // Request the page twice so that asset aggregates are definitely cached in
    // the browser cache.
    $this->drupalGet('admin/structure/taxonomy/manage/tags/overview');
    $this->drupalGet('admin/structure/taxonomy/manage/tags/overview');

    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('admin/structure/taxonomy/manage/tags/overview');
    }, 'umamiVocabularyAdminPageHotCache');
    $this->assertSession()->pageTextContains('Baked');
    $this->assertSame($performance_data->getQueryCount(), 0);
    $this->assertSame($performance_data->getCacheGetCount(), 1);
    $this->assertSame($performance_data->getCacheSetCount(), 0);
    $this->assertSame($performance_data->getCacheDeleteCount(), 0);
    $this->assertSame(0, $performance_data->getCacheTagChecksumCount());
    $this->assertSame(1, $performance_data->getCacheTagIsValidCount());
  }

}
