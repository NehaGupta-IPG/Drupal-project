<?php

declare(strict_types=1);

namespace Drupal\pgsql\Menu;

use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;

/**
 * PostgreSQL implementation of the menu tree storage.
 *
 * @see \Drupal\pgsql\Menu\TableVerifyTrait
 */
class MenuTreeStorage extends CoreMenuTreeStorage {

  use TableVerifyTrait;

}
