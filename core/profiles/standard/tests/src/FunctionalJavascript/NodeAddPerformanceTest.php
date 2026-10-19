<?php

declare(strict_types=1);

namespace Drupal\Tests\standard\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\PerformanceTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests the performance of basic functionality in the standard profile.
 *
 * Stark is used as the default theme so that this test is not Olivero specific.
 *
 * @group Common
 * @group #slow
 * @requires extension apcu
 */
class NodeAddPerformanceTest extends PerformanceTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'create article content' => TRUE,
      'access content' => TRUE,
    ]);
    user_role_change_permissions(RoleInterface::AUTHENTICATED_ID, [
      'create article content' => TRUE,
      'access content' => TRUE,
    ]);
  }

  /**
   * Tests performance of the standard profile.
   */
  public function testPerformance(): void {
    $this->testColdCache();
    $this->testHotCache();
    $this->testWarmCache();
  }

  /**
   * Logs node add page tracing data with a cold cache.
   */
  protected function testColdCache(): void {
    // @todo Chromedriver doesn't collect tracing performance logs for the very
    //   first request in a test, so warm it up.
    //   https://www.drupal.org/project/drupal/issues/3379750
    $this->drupalGet('user/login');
    $this->rebuildAll();
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('node/add/article');
    }, 'standardNodeAddPageColdCache');
    $this->assertSession()->pageTextContains('Create Article');

    // Only count queries.
    // We cannot compare queries as they are not deterministic.
    $recorded_queries = $performance_data->getQueries();

    $expected_queries = [
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.date" )',
      'SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."alias" LIKE "/node/add/article" ESCAPE \'\\\\\') AND ("base_table"."langcode" IN ("en", "und")) ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC',
      'SELECT "name", "route", "fit" FROM "router" WHERE "pattern_outline" IN ( "/node/add/article", "/node/add/%", "/node/%/article", "/node/%/%", "/node/add", "/node/%", "/node" ) AND "number_parts" >= 3',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "user.role.anonymous" )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.maintenance_mode" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "routing.non_admin_routes" ) AND "collection" = "state"',
      'SELECT "name", "route" FROM "router" WHERE "name" IN ( "big_pipe.nojs", "block.category_autocomplete", "block_content.add_page", "block_content.add_form", "ckeditor5.media_entity_metadata", "entity.comment.edit_form", "comment.approve", "entity.comment.canonical", "entity.comment.delete_form", "comment.reply", "block.category_autocomplete"0, "block.category_autocomplete"1, "block.category_autocomplete"2, "block.category_autocomplete"3, "block.category_autocomplete"4, "block.category_autocomplete"5, "block.category_autocomplete"6, "block.category_autocomplete"7, "block.category_autocomplete"8, "block.category_autocomplete"9, "block_content.add_page"0, "block_content.add_page"1, "block_content.add_page"2, "block_content.add_page"3, "block_content.add_page"4, "block_content.add_page"5, "block_content.add_page"6, "block_content.add_page"7, "block_content.add_page"8, "block_content.add_page"9, "block_content.add_form"0, "block_content.add_form"1, "block_content.add_form"2, "block_content.add_form"3, "block_content.add_form"4, "block_content.add_form"5, "block_content.add_form"6, "block_content.add_form"7, "block_content.add_form"8, "block_content.add_form"9, "ckeditor5.media_entity_metadata"0, "ckeditor5.media_entity_metadata"1, "ckeditor5.media_entity_metadata"2, "ckeditor5.media_entity_metadata"3, "ckeditor5.media_entity_metadata"4, "ckeditor5.media_entity_metadata"5, "ckeditor5.media_entity_metadata"6, "ckeditor5.media_entity_metadata"7, "ckeditor5.media_entity_metadata"8, "ckeditor5.media_entity_metadata"9, "entity.comment.edit_form"0, "entity.comment.edit_form"1, "entity.comment.edit_form"2, "entity.comment.edit_form"3, "entity.comment.edit_form"4, "entity.comment.edit_form"5, "entity.comment.edit_form"6, "entity.comment.edit_form"7, "entity.comment.edit_form"8, "entity.comment.edit_form"9, "comment.approve"0, "comment.approve"1, "comment.approve"2, "comment.approve"3, "comment.approve"4, "comment.approve"5, "comment.approve"6, "comment.approve"7, "comment.approve"8, "comment.approve"9, "entity.comment.canonical"0, "entity.comment.canonical"1, "entity.comment.canonical"2, "entity.comment.canonical"3, "entity.comment.canonical"4, "entity.comment.canonical"5, "entity.comment.canonical"6, "entity.comment.canonical"7, "entity.comment.canonical"8, "entity.comment.canonical"9, "entity.comment.delete_form"0, "entity.comment.delete_form"1, "entity.comment.delete_form"2, "entity.comment.delete_form"3, "entity.comment.delete_form"4, "entity.comment.delete_form"5, "entity.comment.delete_form"6, "entity.comment.delete_form"7, "entity.comment.delete_form"8, "entity.comment.delete_form"9, "comment.reply"0, "comment.reply"1 )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "node.field_storage_definitions" ) AND "collection" = "entity.definitions.installed"',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.base_field_override.node.article.nid", "core.base_field_override.node.article.uuid", "core.base_field_override.node.article.vid", "core.base_field_override.node.article.langcode", "core.base_field_override.node.article.type", "core.base_field_override.node.article.revision_timestamp", "core.base_field_override.node.article.revision_uid", "core.base_field_override.node.article.revision_log", "core.base_field_override.node.article.status", "core.base_field_override.node.article.uid", "core.base_field_override.node.article.uuid"0, "core.base_field_override.node.article.uuid"1, "core.base_field_override.node.article.uuid"2, "core.base_field_override.node.article.uuid"3, "core.base_field_override.node.article.uuid"4, "core.base_field_override.node.article.uuid"5, "core.base_field_override.node.article.uuid"6, "core.base_field_override.node.article.uuid"7, "core.base_field_override.node.article.uuid"8 )',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "field.field.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.field.node.article.body", "field.field.node.article.comment", "field.field.node.article.field_image", "field.field.node.article.field_tags" )',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "field.storage.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.storage.node.body", "field.storage.node.comment", "field.storage.node.field_image", "field.storage.node.field_tags" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.file" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.entity_form_display.node.article.default" )',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "comment.type.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "node.type.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.image" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.date_format.html_date" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.date_format.html_time" )',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "system.action.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.action.comment_delete_action", "system.action.comment_publish_action", "system.action.comment_save_action", "system.action.comment_unpublish_action", "system.action.node_delete_action", "system.action.node_make_sticky_action", "system.action.node_make_unsticky_action", "system.action.node_promote_action", "system.action.node_publish_action", "system.action.node_save_action", "system.action.comment_publish_action"0, "system.action.comment_publish_action"1, "system.action.comment_publish_action"2, "system.action.comment_publish_action"3, "system.action.comment_publish_action"4, "system.action.comment_publish_action"5, "system.action.comment_publish_action"6, "system.action.comment_publish_action"7, "system.action.comment_publish_action"8, "system.action.comment_publish_action"9, "system.action.comment_save_action"0 )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "block_content.field_storage_definitions" ) AND "collection" = "entity.definitions.installed"',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.storage.block_content.body" )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "comment.field_storage_definitions" ) AND "collection" = "entity.definitions.installed"',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.storage.comment.comment_body" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "user.settings" )',
      'SELECT "name", "value" FROM "key_value" WHERE "collection" = "entity.definitions.bundle_field_map"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "file.field_storage_definitions" ) AND "collection" = "entity.definitions.installed"',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "search.page.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "taxonomy_term.field_storage_definitions" ) AND "collection" = "entity.definitions.installed"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "user.field_storage_definitions" ) AND "collection" = "entity.definitions.installed"',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.storage.user.user_picture" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.field.block_content.basic.body" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.base_field_override.block_content.basic.id", "core.base_field_override.block_content.basic.uuid", "core.base_field_override.block_content.basic.revision_id", "core.base_field_override.block_content.basic.langcode", "core.base_field_override.block_content.basic.type", "core.base_field_override.block_content.basic.revision_created", "core.base_field_override.block_content.basic.revision_user", "core.base_field_override.block_content.basic.revision_log", "core.base_field_override.block_content.basic.status", "core.base_field_override.block_content.basic.info", "core.base_field_override.block_content.basic.uuid"0, "core.base_field_override.block_content.basic.uuid"1, "core.base_field_override.block_content.basic.uuid"2, "core.base_field_override.block_content.basic.uuid"3, "core.base_field_override.block_content.basic.uuid"4 )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.field.comment.comment.comment_body" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.base_field_override.comment.comment.cid", "core.base_field_override.comment.comment.uuid", "core.base_field_override.comment.comment.langcode", "core.base_field_override.comment.comment.comment_type", "core.base_field_override.comment.comment.status", "core.base_field_override.comment.comment.uid", "core.base_field_override.comment.comment.pid", "core.base_field_override.comment.comment.entity_id", "core.base_field_override.comment.comment.subject", "core.base_field_override.comment.comment.name", "core.base_field_override.comment.comment.uuid"0, "core.base_field_override.comment.comment.uuid"1, "core.base_field_override.comment.comment.uuid"2, "core.base_field_override.comment.comment.uuid"3, "core.base_field_override.comment.comment.uuid"4, "core.base_field_override.comment.comment.uuid"5, "core.base_field_override.comment.comment.uuid"6, "core.base_field_override.comment.comment.uuid"7, "core.base_field_override.comment.comment.uuid"8 )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.field.node.page.body" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.base_field_override.node.page.nid", "core.base_field_override.node.page.uuid", "core.base_field_override.node.page.vid", "core.base_field_override.node.page.langcode", "core.base_field_override.node.page.type", "core.base_field_override.node.page.revision_timestamp", "core.base_field_override.node.page.revision_uid", "core.base_field_override.node.page.revision_log", "core.base_field_override.node.page.status", "core.base_field_override.node.page.uid", "core.base_field_override.node.page.uuid"0, "core.base_field_override.node.page.uuid"1, "core.base_field_override.node.page.uuid"2, "core.base_field_override.node.page.uuid"3, "core.base_field_override.node.page.uuid"4, "core.base_field_override.node.page.uuid"5, "core.base_field_override.node.page.uuid"6, "core.base_field_override.node.page.uuid"7, "core.base_field_override.node.page.uuid"8 )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "field.field.user.user.user_picture" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.base_field_override.user.user.uid", "core.base_field_override.user.user.uuid", "core.base_field_override.user.user.langcode", "core.base_field_override.user.user.preferred_langcode", "core.base_field_override.user.user.preferred_admin_langcode", "core.base_field_override.user.user.name", "core.base_field_override.user.user.pass", "core.base_field_override.user.user.mail", "core.base_field_override.user.user.timezone", "core.base_field_override.user.user.status", "core.base_field_override.user.user.uuid"0, "core.base_field_override.user.user.uuid"1, "core.base_field_override.user.user.uuid"2, "core.base_field_override.user.user.uuid"3, "core.base_field_override.user.user.uuid"4, "core.base_field_override.user.user.uuid"5, "core.base_field_override.user.user.uuid"6 )',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("theme_registry:runtime:stark:Drupal\Core\Utility\ThemeRegistry", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "theme_registry:runtime:stark:Drupal\Core\Utility\ThemeRegistry") AND ("value" = "LOCK_ID")',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "twig_extension_hash_prefix" ) AND "collection" = "state"',
      'SELECT 1 AS "expression" FROM "key_value" "key_value" WHERE ("name" = "twig_extension_hash_prefix") AND ("collection" = "state")',
      'INSERT INTO "key_value" ("name", "collection", "value") VALUES ("twig_extension_hash_prefix", "state", "a:2:{s:19:"twig_extension_hash";s:19:"TWIG_EXTENSION_HASH";s:17:"twig_cache_prefix";s:17:"TWIG_CACHE_PREFIX";}")',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("state:Drupal\Core\Cache\CacheCollector", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "state:Drupal\Core\Cache\CacheCollector") AND ("value" = "LOCK_ID")',
      'SELECT "base"."uid" AS "uid", "base"."uuid" AS "uuid", "base"."langcode" AS "langcode" FROM "users" "base" WHERE "base"."uid" IN (0)',
      'SELECT "data".* FROM "users_field_data" "data" WHERE "data"."uid" IN (0) ORDER BY "data"."uid" ASC',
      'SELECT "t".* FROM "user__roles" "t" WHERE ("entity_id" IN (0)) AND ("deleted" = 0) AND ("langcode" IN ("en", "und", "zxx")) ORDER BY "delta" ASC',
      'SELECT "t".* FROM "user__user_picture" "t" WHERE ("entity_id" IN (0)) AND ("deleted" = 0) AND ("langcode" IN ("en", "und", "zxx")) ORDER BY "delta" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.menu.main" )',
      'SELECT "menu_tree".* FROM "menu_tree" "menu_tree" WHERE ("menu_name" = "main") AND ("depth" <= 8) ORDER BY "p1" ASC, "p2" ASC, "p3" ASC, "p4" ASC, "p5" ASC, "p6" ASC, "p7" ASC, "p8" ASC, "p9" ASC',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "filter.format.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "filter.format.basic_html", "filter.format.full_html", "filter.format.plain_text", "filter.format.restricted_html" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "filter.settings" )',
      'SELECT 1 AS "expression" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."path" LIKE "/node%" ESCAPE \'\\\\\') LIMIT 1 OFFSET 0',
      'SELECT 1 FROM "key_value" WHERE "collection" = "entity_autocomplete" AND "name" = "AUTOCOMPLETE_HASH"',
      'SELECT 1 AS "expression" FROM "key_value" "key_value" WHERE ("name" = "AUTOCOMPLETE_HASH") AND ("collection" = "entity_autocomplete")',
      'INSERT INTO "key_value" ("name", "collection", "value") VALUES ("AUTOCOMPLETE_HASH", "entity_autocomplete", "a:5:{s:14:"target_bundles";a:1:{s:4:"tags";s:4:"tags";}s:4:"sort";a:1:{s:5:"field";s:5:"_none";}s:11:"auto_create";b:1;s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;}")',
      'SELECT 1 AS "expression" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."path" LIKE "/entity\_reference\_autocomplete%" ESCAPE \'\\\\\') LIMIT 1 OFFSET 0',
      'SELECT 1 FROM "key_value" WHERE "collection" = "entity_autocomplete" AND "name" = "AUTOCOMPLETE_HASH"',
      'SELECT 1 AS "expression" FROM "key_value" "key_value" WHERE ("name" = "AUTOCOMPLETE_HASH") AND ("collection" = "entity_autocomplete")',
      'INSERT INTO "key_value" ("name", "collection", "value") VALUES ("AUTOCOMPLETE_HASH", "entity_autocomplete", "a:2:{s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;}")',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "editor.editor.restricted_html" )',
      'SELECT 1 AS "expression" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."path" LIKE "/filter%" ESCAPE \'\\\\\') LIMIT 1 OFFSET 0',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "theme:stark" ) AND "collection" = "config.entity.key_store.block"',
      'SELECT "base_table"."revision_id" AS "revision_id", "base_table"."id" AS "id" FROM "block_content" "base_table" INNER JOIN "block_content_field_data" "block_content_field_data" ON "block_content_field_data"."id" = "base_table"."id" WHERE ("block_content_field_data"."reusable" IN ("1")) AND ("block_content_field_data"."default_langcode" IN (1))',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "system.menu.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.menu.account", "system.menu.admin", "system.menu.footer", "system.menu.tools" )',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "views.view.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.site" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.theme.global" )',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "stark.settings" )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.private_key" ) AND "collection" = "state"',
      'SELECT 1 AS "expression" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."path" LIKE "/search%" ESCAPE \'\\\\\') LIMIT 1 OFFSET 0',
      'SELECT "menu_tree"."menu_name" AS "menu_name", "menu_tree"."route_name" AS "route_name", "menu_tree"."route_parameters" AS "route_parameters", "menu_tree"."url" AS "url", "menu_tree"."title" AS "title", "menu_tree"."description" AS "description", "menu_tree"."parent" AS "parent", "menu_tree"."weight" AS "weight", "menu_tree"."options" AS "options", "menu_tree"."expanded" AS "expanded", "menu_tree"."enabled" AS "enabled", "menu_tree"."provider" AS "provider", "menu_tree"."metadata" AS "metadata", "menu_tree"."class" AS "class", "menu_tree"."form_class" AS "form_class", "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("route_name" = "node.add") AND ("route_param_key" = "node_type=article") AND ("menu_name" = "main") ORDER BY "depth" ASC, "weight" ASC, "id" ASC',
      'SELECT "menu_tree".* FROM "menu_tree" "menu_tree" WHERE ("menu_name" = "main") AND ("depth" <= 2) ORDER BY "p1" ASC, "p2" ASC, "p3" ASC, "p4" ASC, "p5" ASC, "p6" ASC, "p7" ASC, "p8" ASC, "p9" ASC',
      'SELECT "menu_tree"."menu_name" AS "menu_name", "menu_tree"."route_name" AS "route_name", "menu_tree"."route_parameters" AS "route_parameters", "menu_tree"."url" AS "url", "menu_tree"."title" AS "title", "menu_tree"."description" AS "description", "menu_tree"."parent" AS "parent", "menu_tree"."weight" AS "weight", "menu_tree"."options" AS "options", "menu_tree"."expanded" AS "expanded", "menu_tree"."enabled" AS "enabled", "menu_tree"."provider" AS "provider", "menu_tree"."metadata" AS "metadata", "menu_tree"."class" AS "class", "menu_tree"."form_class" AS "form_class", "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("route_name" = "node.add") AND ("route_param_key" = "node_type=article") AND ("menu_name" = "account") ORDER BY "depth" ASC, "weight" ASC, "id" ASC',
      'SELECT "menu_tree"."id" AS "id" FROM "menu_tree" "menu_tree" WHERE ("menu_name" = "account") AND ("expanded" = 1) AND ("has_children" = 1) AND ("enabled" = 1) AND ("parent" IN ("")) AND ("id" NOT IN (""))',
      'SELECT "menu_tree".* FROM "menu_tree" "menu_tree" WHERE ("menu_name" = "account") AND ("parent" IN ("")) AND ("depth" <= 1) ORDER BY "p1" ASC, "p2" ASC, "p3" ASC, "p4" ASC, "p5" ASC, "p6" ASC, "p7" ASC, "p8" ASC, "p9" ASC',
      'SELECT 1 AS "expression" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."path" LIKE "/user%" ESCAPE \'\\\\\') LIMIT 1 OFFSET 0',
      'SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."alias" LIKE "/node/add" ESCAPE \'\\\\\') AND ("base_table"."langcode" IN ("en", "und")) ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC',
      'SELECT "name", "route", "fit" FROM "router" WHERE "pattern_outline" IN ( "/node/add", "/node/%", "/node" ) AND "number_parts" >= 2',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "core.entity_form_mode.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.entity_form_mode.user.register" )',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "core.entity_view_mode.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "core.entity_view_mode.block_content.full", "core.entity_view_mode.comment.full", "core.entity_view_mode.node.full", "core.entity_view_mode.node.rss", "core.entity_view_mode.node.search_index", "core.entity_view_mode.node.search_result", "core.entity_view_mode.node.teaser", "core.entity_view_mode.taxonomy_term.full", "core.entity_view_mode.user.compact", "core.entity_view_mode.user.full" )',
      'SELECT "name", "route", "fit" FROM "router" WHERE "pattern_outline" IN ( "/admin/content", "/admin/%", "/admin" ) AND "number_parts" >= 2',
      'SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."alias" LIKE "/rss.xml" ESCAPE \'\\\\\') AND ("base_table"."langcode" IN ("en", "und")) ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC',
      'SELECT "name", "route", "fit" FROM "router" WHERE "pattern_outline" IN ( "/rss.xml" ) AND "number_parts" >= 1',
      'SELECT 1 AS "expression" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."path" LIKE "/rss.xml%" ESCAPE \'\\\\\') LIMIT 1 OFFSET 0',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "system.performance" )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "asset.css_js_query_string" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "drupal.test_wait_terminate" ) AND "collection" = "state"',
      'SELECT "name", "data" FROM "config" WHERE "collection" = "" AND "name" IN ( "automated_cron.settings" )',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.cron_last" ) AND "collection" = "state"',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("state:Drupal\Core\Cache\CacheCollector", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "state:Drupal\Core\Cache\CacheCollector") AND ("value" = "LOCK_ID")',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("theme_registry:runtime:stark:Drupal\Core\Utility\ThemeRegistry", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "theme_registry:runtime:stark:Drupal\Core\Utility\ThemeRegistry") AND ("value" = "LOCK_ID")',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("library_info:stark:Drupal\Core\Cache\CacheCollector", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "library_info:stark:Drupal\Core\Cache\CacheCollector") AND ("value" = "LOCK_ID")',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("path_alias_prefix_list:Drupal\Core\Cache\CacheCollector", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "path_alias_prefix_list:Drupal\Core\Cache\CacheCollector") AND ("value" = "LOCK_ID")',
      'SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."alias" LIKE "CSS_FILE" ESCAPE \'\\\\\') AND ("base_table"."langcode" IN ("en", "und")) ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "routing.menu_masks.router" ) AND "collection" = "state"',
      'SELECT "name", "route", "fit" FROM "router" WHERE "pattern_outline" IN ( "/sites/simpletest/TEST_ID/files/css/CSS_FILE", "/sites/simpletest/TEST_ID/files/css/%", "/sites/simpletest/TEST_ID/files/%/CSS_FILE", "/sites/simpletest/%/files/%/CSS_FILE", "/sites/simpletest/%/%/%/CSS_FILE", "/sites/%/TEST_ID/%/css/%", "/sites/simpletest/TEST_ID/files/css", "/sites/simpletest/TEST_ID/files/%", "/sites/simpletest/TEST_ID/%/css", "/sites/simpletest/TEST_ID/%/%", "/sites/simpletest/TEST_ID/files/css/%"0, "/sites/simpletest/TEST_ID/files/css/%"1, "/sites/simpletest/TEST_ID/files/css/%"2, "/sites/simpletest/TEST_ID/files/css/%"3, "/sites/simpletest/TEST_ID/files/css/%"4, "/sites/simpletest/TEST_ID/files/css/%"5, "/sites/simpletest/TEST_ID/files/css/%"6, "/sites/simpletest/TEST_ID/files/css/%"7, "/sites/simpletest/TEST_ID/files/css/%"8, "/sites/simpletest/TEST_ID/files/css/%"9, "/sites/simpletest/TEST_ID/files/%/CSS_FILE"0, "/sites/simpletest/TEST_ID/files/%/CSS_FILE"1, "/sites/simpletest/TEST_ID/files/%/CSS_FILE"2, "/sites/simpletest/TEST_ID/files/%/CSS_FILE"3 ) AND "number_parts" >= 6',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.maintenance_mode" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "routing.non_admin_routes" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "drupal.test_wait_terminate" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.cron_last" ) AND "collection" = "state"',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("state:Drupal\Core\Cache\CacheCollector", "LOCK_ID", "EXPIRE")',
      'DELETE FROM "semaphore"  WHERE ("name" = "state:Drupal\Core\Cache\CacheCollector") AND ("value" = "LOCK_ID")',
      'SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."alias" LIKE "JS_FILE" ESCAPE \'\\\\\') AND ("base_table"."langcode" IN ("en", "und")) ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "routing.menu_masks.router" ) AND "collection" = "state"',
      'SELECT "name", "route", "fit" FROM "router" WHERE "pattern_outline" IN ( "/sites/simpletest/TEST_ID/files/js/JS_FILE", "/sites/simpletest/TEST_ID/files/js/%", "/sites/simpletest/TEST_ID/files/%/JS_FILE", "/sites/simpletest/%/files/%/JS_FILE", "/sites/simpletest/%/%/%/JS_FILE", "/sites/%/TEST_ID/%/js/%", "/sites/simpletest/TEST_ID/files/js", "/sites/simpletest/TEST_ID/files/%", "/sites/simpletest/TEST_ID/%/js", "/sites/simpletest/TEST_ID/%/%", "/sites/simpletest/TEST_ID/files/js/%"0, "/sites/simpletest/TEST_ID/files/js/%"1, "/sites/simpletest/TEST_ID/files/js/%"2, "/sites/simpletest/TEST_ID/files/js/%"3, "/sites/simpletest/TEST_ID/files/js/%"4, "/sites/simpletest/TEST_ID/files/js/%"5, "/sites/simpletest/TEST_ID/files/js/%"6, "/sites/simpletest/TEST_ID/files/js/%"7, "/sites/simpletest/TEST_ID/files/js/%"8, "/sites/simpletest/TEST_ID/files/js/%"9, "/sites/simpletest/TEST_ID/files/%/JS_FILE"0, "/sites/simpletest/TEST_ID/files/%/JS_FILE"1, "/sites/simpletest/TEST_ID/files/%/JS_FILE"2, "/sites/simpletest/TEST_ID/files/%/JS_FILE"3 ) AND "number_parts" >= 6',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.maintenance_mode" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "routing.non_admin_routes" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.private_key" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "drupal.test_wait_terminate" ) AND "collection" = "state"',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "system.cron_last" ) AND "collection" = "state"',
      'INSERT INTO "semaphore" ("name", "value", "expire") VALUES ("state:Drupal\Core\Cache\CacheCollector", "LOCK_ID", "EXPIRE")',

    ];

    $this->assertSame($expected_queries, $recorded_queries);
    $this->assertSame(124, $performance_data->getQueryCount());
    $this->assertSame(227, $performance_data->getCacheGetCount());
    $this->assertSame(190, $performance_data->getCacheSetCount());
    $this->assertSame(1, $performance_data->getCacheDeleteCount());
    $this->assertCountBetween(30, 127, $performance_data->getCacheTagChecksumCount());
    $this->assertCountBetween(30, 39, $performance_data->getCacheTagIsValidCount());
    $this->assertSame(0, $performance_data->getCacheTagInvalidationCount());
    $this->assertSame(1, $performance_data->getScriptCount());
    $this->assertCountBetween(212000, 213000, $performance_data->getScriptBytes());
    $this->assertSame(1, $performance_data->getStylesheetCount());
    $this->assertCountBetween(29500, 30500, $performance_data->getStylesheetBytes());
  }

  /**
   * Logs node add page tracing data with a hot cache.
   *
   * Hot here means that all possible caches are warmed.
   */
  protected function testHotCache(): void {
    // Request the page twice so that asset aggregates are definitely cached in
    // the browser cache.
    $this->drupalGet('node/add/article');
    $this->drupalGet('node/add/article');

    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('node/add/article');
    }, 'standardNodeAddPageHotCache');
    $this->assertSession()->pageTextContains('Create Article');

    $recorded_queries = $performance_data->getQueries();
    $this->assertSame([], $recorded_queries);
    $this->assertSame(0, $performance_data->getQueryCount());
    $this->assertSame(1, $performance_data->getCacheGetCount());
    $this->assertSame(0, $performance_data->getCacheSetCount());
    $this->assertSame(0, $performance_data->getCacheDeleteCount());
    $this->assertSame(0, $performance_data->getCacheTagChecksumCount());
    $this->assertSame(1, $performance_data->getCacheTagIsValidCount());
    $this->assertSame(0, $performance_data->getCacheTagInvalidationCount());
    $this->assertSame(1, $performance_data->getScriptCount());
    $this->assertSame(212244, $performance_data->getScriptBytes());
    $this->assertSame(1, $performance_data->getStylesheetCount());
    $this->assertSame(29911, $performance_data->getStylesheetBytes());
  }

  /**
   * Logs front page tracing data with an authenticated user and warm cache.
   */
  protected function testWarmCache(): void {
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);

    $this->drupalGet('node/add/article');
    $this->drupalGet('node/add/article');

    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('node/add/article');
    }, 'standardNodeAddPageWarmCache');

    $expected_queries = [
      'SELECT "session" FROM "sessions" WHERE "sid" = "SESSION_ID" LIMIT 0, 1',
      'SELECT * FROM "users_field_data" "u" WHERE "u"."uid" = "2" AND "u"."default_langcode" = 1',
      'SELECT "roles_target_id" FROM "user__roles" WHERE "entity_id" = "2"',
      'SELECT 1 FROM "key_value" WHERE "collection" = "entity_autocomplete" AND "name" = "AUTOCOMPLETE_HASH"',
      'SELECT 1 FROM "key_value" WHERE "collection" = "entity_autocomplete" AND "name" = "AUTOCOMPLETE_HASH"',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "editor.editor.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "name", "value" FROM "key_value" WHERE "name" IN ( "theme:stark" ) AND "collection" = "config.entity.key_store.block"',
    ];
    $recorded_queries = $performance_data->getQueries();

    // We need to replace queries that are not deterministic removing the random
    // part.
    $this->assertSame($expected_queries, $recorded_queries);
    $this->assertSame(7, $performance_data->getQueryCount());
    $this->assertSame(85, $performance_data->getCacheGetCount());
    $this->assertSame(0, $performance_data->getCacheSetCount());
    $this->assertSame(0, $performance_data->getCacheDeleteCount());
    $this->assertSame(0, $performance_data->getCacheTagChecksumCount());
    $this->assertSame(39, $performance_data->getCacheTagIsValidCount());
    $this->assertSame(0, $performance_data->getCacheTagInvalidationCount());
    $this->assertSame(3, $performance_data->getScriptCount());
    $this->assertCountBetween(1631000, 1632000, $performance_data->getScriptBytes());
    $this->assertSame(1, $performance_data->getStylesheetCount());
    $this->assertCountBetween(36500, 37500, $performance_data->getStylesheetBytes());
  }

}
