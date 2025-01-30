<?php

declare(strict_types=1);

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Implements hook_entity_field_access().
 */
#[Hook('entity_field_access')]
class ImageFieldDisplayTestDefaultPrivateStorageHooks {

  /**
   * Implements hook_entity_field_access().
   */
  public function __invoke($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() == 'field_default_private' && $operation == 'view') {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

}
