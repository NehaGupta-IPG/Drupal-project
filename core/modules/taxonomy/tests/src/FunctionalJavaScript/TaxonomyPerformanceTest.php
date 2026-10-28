<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\PerformanceTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the performance of the taxonomy module.
 *
 * Stark is used as the default theme so that this test is not Olivero specific.
 *
 * @group Taxonomy
 */
class TaxonomyPerformanceTest extends PerformanceTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['taxonomy'];

  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->vocabulary = $this->createVocabulary();
  }

  /**
   * Tests the taxonomy overview page.
   */
  public function testTaxonomyOverview(): void {
    // Create enough terms for there to be several not shown on the first page
    // of the overview.
    foreach (range(0, 100) as $index) {
      $this->createTerm($this->vocabulary);
    }
    $this->rebuildAll();

    $account = $this->drupalCreateUser(['administer taxonomy']);
    $this->drupalLogin($account);

    // Warm some caches.
    $this->drupalGet('admin/structure/taxonomy');
    $this->drupalGet('admin/structure/taxonomy');

    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->get('vid') . '/overview');
    });
    // This test observes a variable number of database queries, so to avoid
    // random test failures, assert greater than equal the highest and lowest
    // number of queries observed during test runs.
    // See https://www.drupal.org/project/drupal/issues/3402610
    $this->assertLessThanOrEqual(61, $performance_data->getQueryCount());
    $this->assertGreaterThanOrEqual(60, $performance_data->getQueryCount());
    $this->assertLessThanOrEqual(61, $performance_data->getCacheGetCount());
    $this->assertGreaterThanOrEqual(57, $performance_data->getCacheGetCount());
    $this->assertLessThanOrEqual(30, $performance_data->getCacheSetCount());
    $this->assertGreaterThanOrEqual(28, $performance_data->getCacheSetCount());
    $this->assertSame(0, $performance_data->getCacheDeleteCount());
  }

  /**
   * Provides an empty implementation to prevent the resetting of caches.
   */
  protected function refreshVariables() {}

}
