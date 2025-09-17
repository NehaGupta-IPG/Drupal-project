<?php

namespace Drupal\layout_builder\Field;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an item list class for layout translation fields.
 *
 * @internal
 *   Plugin classes are internal.
 *
 * @see \Drupal\layout_builder\Plugin\Field\FieldType\LayoutTranslationItem
 */
class LayoutTranslationItemList extends FieldItemList {

  /**
   * Overrides \Drupal\Core\Field\FieldItemListInterface::defaultAccess().
   *
   * @ingroup layout_builder_access
   */
  public function defaultAccess($operation = 'view', ?AccountInterface $account = NULL) {
    // @todo Allow access in https://www.drupal.org/node/2942975.
    return AccessResult::forbidden();
  }

}
