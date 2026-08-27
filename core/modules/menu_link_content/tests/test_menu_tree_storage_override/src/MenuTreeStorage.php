<?php

declare(strict_types=1);

namespace Drupal\test_menu_tree_storage_override;

use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;

/**
 * Class to override the core's menu tree storage and increase menu_name length.
 */
class MenuTreeStorage extends CoreMenuTreeStorage {

  /**
   * {@inheritdoc}
   */
  protected static function schemaDefinition(): array {
    $schema = parent::schemaDefinition();
    $schema['fields']['menu_name']['length'] = 128;
    return $schema;
  }

}
