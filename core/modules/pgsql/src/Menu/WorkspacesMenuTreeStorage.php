<?php

declare(strict_types=1);

namespace Drupal\pgsql\Menu;

use Drupal\workspaces\WorkspacesMenuTreeStorage as CoreWorkspacesMenuTreeStorage;

/**
 * PostgreSQL implementation of the workspaces menu tree storage.
 *
 * The workspaces module decorates the menu.tree_storage service with its own
 * subclass of the core menu tree storage, so replacing the class of the
 * menu.tree_storage service does not cover sites with the workspaces module
 * installed. This class is only loaded when the workspaces module is
 * installed.
 *
 * @see \Drupal\pgsql\Menu\TableVerifyTrait
 */
class WorkspacesMenuTreeStorage extends CoreWorkspacesMenuTreeStorage {

  use TableVerifyTrait;

}
