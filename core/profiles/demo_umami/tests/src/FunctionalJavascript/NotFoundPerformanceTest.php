<?php

declare(strict_types=1);

namespace Drupal\Tests\demo_umami\FunctionalJavascript;

use Drupal\Core\Cache\Cache;
use Drupal\FunctionalJavascriptTests\PerformanceTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests demo_umami profile performance.
 */
#[Group('OpenTelemetry')]
#[Group('#slow')]
#[RequiresPhpExtension('apcu')]
#[RunTestsInSeparateProcesses]
class NotFoundPerformanceTest extends PerformanceTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'demo_umami';

  /**
   * Tests performance of 404 pages.
   */
  public function test404Page(): void {

    // Warm asset aggregates and image derivatives for the page we're going to
    // test then clear caches.
    $this->drupalGet('bar');
    sleep(2);
    $this->clearCaches();

    // Now warm a different 404 page so that any 404-specific caches are warmed.
    $this->drupalGet('foo');
    sleep(1);
    $this->drupalGet('foo');
    sleep(1);
    $this->assertSession()->pageTextContains('Not found');

    // Now visit another 404 page.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('baz');
    }, 'NotFound');
    $this->assertSession()->pageTextContains('Not found');

    $expected = [
      'QueryCount' => 5,
      'CacheGetCount' => 58,
      'CacheGetCountByBin' => [
        'page' => 1,
        'config' => 17,
        'bootstrap' => 11,
        'discovery' => 16,
        'data' => 7,
        'dynamic_page_cache' => 2,
        'entity' => 2,
        'render' => 2,
      ],
      'CacheSetCount' => 3,
      'CacheDeleteCount' => 0,
      'CacheTagInvalidationCount' => 0,
      'CacheTagLookupQueryCount' => 5,
      'CacheTagGroupedLookups' => [
        [
          'entity_types',
          'route_match',
          'access_policies',
          'routes',
          'router',
          'entity_field_info',
          'entity_bundles',
          'local_task',
          'library_info',
          'http_response',
        ],
        [
          'block_content:1',
          'block_content:2',
          'config:block_list',
          'config:configurable_language_list',
          'rendered',
        ],
        [
          'config:filter.format.basic_html',
          'config:system.menu.footer',
        ],
        [
          'config:system.menu.account',
          'config:system.site',
          'block_content_view',
          'config:image.style.medium_8_7',
          'file:37',
          'media:19',
          'media_view',
          'config:system.menu.main',
          'config:views.view.recipe_collections',
          'taxonomy_term:1',
          'taxonomy_term:10',
          'taxonomy_term:11',
          'taxonomy_term:12',
          'taxonomy_term:13',
          'taxonomy_term:14',
          'taxonomy_term:15',
          'taxonomy_term:16',
          'taxonomy_term:2',
          'taxonomy_term:3',
          'taxonomy_term:4',
          'taxonomy_term:5',
          'taxonomy_term:6',
          'taxonomy_term:7',
          'taxonomy_term:8',
          'taxonomy_term:9',
          'taxonomy_term_list',
        ],
        [
          '4xx-response',
          'config:user.role.anonymous',
        ],
      ],
      'ScriptCount' => 1,
      'ScriptBytes' => 12000,
      'StylesheetCount' => 2,
      'StylesheetBytes' => 36491,
    ];
    $this->assertMetrics($expected, $performance_data);
  }

  /**
   * Clear caches.
   */
  protected function clearCaches(): void {
    foreach (Cache::getBins() as $bin) {
      $bin->deleteAll();
    }
  }

}
