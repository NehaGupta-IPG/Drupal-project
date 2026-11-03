<?php

namespace Drupal\Tests\Core\Menu;

use Drupal\Core\Url;

/**
 * Defines a mock implementation of a menu link used in tests only.
 */
class MenuLinkNoOperationLinksMock extends MenuLinkMock {

  /**
   * {@inheritdoc}
   */
  public function isDeletable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslatable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isResettable() {
    return $this->pluginDefinition['is_resettable'];
  }

  /**
   * {@inheritdoc}
   */
  public function getResetRoute(): Url|NULL {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeleteRoute(): Url|NULL {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslateRoute(): Url|NULL {
    return NULL;
  }

}
