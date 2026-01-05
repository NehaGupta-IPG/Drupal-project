<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_link_content\Functional\Update;

use Drupal\Core\Database\Connection;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Core\Serialization\Yaml;

/**
 * Tests the upgrade path for setting menu_name length state variable.
 *
 * @see system_update_10101()
 *
 * @group Update
 * @group legacy
 */
class MenuLinkContentLengthUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['test_menu_tree_storage_override'];

  /**
   * @var \Drupal\Core\Database\Connection
   *   The database service.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->database = $this->container->get('database');
    $this->installModulesFromClassProperty($this->container);
  }

  /**
   * Tests the upgrade path for menu_link_content_menu_name_max_length variable.
   */
  public function testMenuLinkContentLengthStateVariable() {
    $length = \Drupal::state()->get('menu_link_content_menu_name_max_length', NULL);
    $this->assertEquals($length, NULL);

    // Take arbitrary menu name longer than 32 characters (36 char).
    // Length of menu_name is altered in module test_menu_tree_storage_override.
    $menu_name = 'p7tULsz0kp6TD4ncR1OONaROlRcyUfjL9ADB';
    $this->database->insert('config')
      ->fields([
        'collection' => '',
        'name' => "system.menu.{$menu_name}",
        'data' => serialize(Yaml::decode(file_get_contents("core/modules/menu_link_content/tests/fixtures/update/system.menu.{$menu_name}.yml"))),
      ])
      ->execute();

    // Update existing menu links name.
    $this->database->update('menu_tree')
      ->fields(['menu_name' => $menu_name])
      ->condition('id', 'contact.site_page')
      ->execute();

    $this->runUpdates();

    // Length is 36 because our module test_menu_tree_storage_override is
    // altering the length. New menu item is being set before update hook.
    $length = \Drupal::state()->get('menu_link_content_menu_name_max_length');
    $this->assertEquals($length, strlen($menu_name));
  }

}
