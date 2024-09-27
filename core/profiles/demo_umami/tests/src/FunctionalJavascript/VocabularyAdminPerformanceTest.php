<?php

declare(strict_types=1);

namespace Drupal\Tests\demo_umami\FunctionalJavascript;

use Drupal\block\Entity\Block;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'administer taxonomy',
    ]));
    // Remove the block showing tags terms on the footer, to be able to not
    // load terms there by default on every page directly.
    $block = $this->container->get('entity_type.manager')
      ->getStorage('block')
      ->load('umami_views_block__recipe_collections_block');
    $this->assertTrue($block instanceof Block);
    $block->delete();
  }

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
    // Umami lists all tags at a block at the bottom of the page, so use a more
    // specific query to find the text.
    $this->assertSession()->elementTextContains('xpath', '//table[@id="taxonomy"]', 'Baked');
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
    $this->assertSame($performance_data->getQueryCount(), 9);
    $this->assertSame($performance_data->getCacheGetCount(), 116);
    $this->assertSame($performance_data->getCacheSetCount(), 0);
    $this->assertSame($performance_data->getCacheDeleteCount(), 0);
    $this->assertSame(0, $performance_data->getCacheTagChecksumCount());
    $this->assertSame(72, $performance_data->getCacheTagIsValidCount());
  }

}
