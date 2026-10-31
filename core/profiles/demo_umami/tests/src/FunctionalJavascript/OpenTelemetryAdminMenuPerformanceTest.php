<?php

declare(strict_types=1);

namespace Drupal\Tests\demo_umami\FunctionalJavascript;

use Drupal\Core\Cache\Cache;
use Drupal\FunctionalJavascriptTests\PerformanceTestBase;

/**
 * Tests demo_umami admin menu page performance.
 *
 * @group OpenTelemetry
 * @group #slow
 * @requires extension apcu
 */
class OpenTelemetryAdminMenuPerformanceTest extends PerformanceTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'demo_umami';

  protected const string ADMIN_MENU_PAGE = 'admin/structure/menu/manage/admin';

  /**
   * Setup/login user with permissions to access the admin menu.
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->drupalCreateUser(['administer menu', 'access administration pages', 'view the administration theme']);
    $this->drupalLogin($user);
  }

  /**
   * Logs admin menu tracing data with a cold cache.
   */
  public function testAdminMenuColdCache(): void {
    // Request the page twice so that asset aggregates are definitely cached
    // in the browser cache.
    $this->drupalGet(self::ADMIN_MENU_PAGE);
    $this->drupalGet(self::ADMIN_MENU_PAGE);

    // Ensure the cache is cold.
    $this->clearCaches();

    $performance_data = $this->collectPerformanceData(fn () => $this->drupalGet(self::ADMIN_MENU_PAGE), 'umamiAdminMenuColdCache');

    // Check that we are actually on the admin menu page.
    $this->assertSession()->elementExists('xpath', '//form[@class="menu-edit-form menu-form"]');

    // Assert performance data with some minor leeway.
    $this->assertCountBetween(250, 270, $performance_data->getQueryCount());
    $this->assertCountBetween(380, 400, $performance_data->getCacheGetCount());
    $this->assertCountBetween(320, 340, $performance_data->getCacheSetCount());
    $this->assertEquals(0, $performance_data->getCacheDeleteCount());
    $this->assertCountBetween(160, 180, $performance_data->getCacheTagChecksumCount());
    $this->assertCountBetween(15, 60, $performance_data->getCacheTagIsValidCount());
    $this->assertEquals(0, $performance_data->getCacheTagInvalidationCount());
    $this->assertEquals(2, $performance_data->getStylesheetCount());
    $this->assertEquals(2, $performance_data->getScriptCount());
    $this->assertCountBetween(200000, 210000, $performance_data->getStylesheetBytes());
    $this->assertCountBetween(560000, 580000, $performance_data->getScriptBytes());
  }

  /**
   * Logs admin menu tracing data with a warm cache.
   */
  public function testAdminMenuWarmCache(): void {
    // Request the page twice so that asset aggregates are definitely cached
    // in the browser cache.
    $this->drupalGet(self::ADMIN_MENU_PAGE);
    $this->drupalGet(self::ADMIN_MENU_PAGE);

    $performance_data = $this->collectPerformanceData(fn () => $this->drupalGet(self::ADMIN_MENU_PAGE), 'umamiAdminMenuWarmCache');

    // Check that we are actually on the admin menu page.
    $this->assertSession()->elementExists('xpath', '//form[@class="menu-edit-form menu-form"]');

    $expected_queries = [
      'SELECT "session" FROM "sessions" WHERE "sid" = "SESSION_ID" LIMIT 0, 1',
      'SELECT * FROM "users_field_data" "u" WHERE "u"."uid" = "10" AND "u"."default_langcode" = 1',
      'SELECT "roles_target_id" FROM "user__roles" WHERE "entity_id" = "10"',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "language.entity.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "mlfr"."id" AS "id", MAX("mlfr"."revision_id") AS "revision_id" FROM "menu_link_content_field_revision" "mlfr" INNER JOIN "menu_link_content_revision" "mlr" ' .
      'ON "mlfr"."revision_id" = "mlr"."revision_id" AND "mlr"."revision_default" = 0 ' .
      'INNER JOIN (SELECT "t"."id" AS "id", "t"."langcode" AS "langcode", MAX("t"."revision_id") AS "revision_id" FROM "menu_link_content_field_revision" "t" WHERE "t"."revision_translation_affected" = "1" ' .
      'GROUP BY "t"."id", "t"."langcode") "mr" ON "mlfr"."revision_id" = "mr"."revision_id" AND "mlfr"."langcode" = "mr"."langcode" GROUP BY "mlfr"."id"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "theme:claro" ) AND "collection" = "config.entity.key_store.block"',
    ];
    $this->assertEquals($expected_queries, $performance_data->getQueries());
    $this->assertEquals(6, $performance_data->getQueryCount());
    $this->assertEquals(70, $performance_data->getCacheGetCount());
    $this->assertEquals(0, $performance_data->getCacheSetCount());
    $this->assertEquals(0, $performance_data->getCacheDeleteCount());
    $this->assertEquals(0, $performance_data->getCacheTagChecksumCount());
    $this->assertEquals(21, $performance_data->getCacheTagIsValidCount());
    $this->assertEquals(0, $performance_data->getCacheTagInvalidationCount());
    $this->assertEquals(2, $performance_data->getStylesheetCount());
    $this->assertEquals(2, $performance_data->getScriptCount());
    // Still assert assets size with some minor leeway.
    $this->assertCountBetween(200000, 210000, $performance_data->getStylesheetBytes());
    $this->assertCountBetween(560000, 580000, $performance_data->getScriptBytes());
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
