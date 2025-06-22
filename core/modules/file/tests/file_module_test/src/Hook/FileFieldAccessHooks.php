<?php

declare(strict_types=1);

namespace Drupal\file_module_test\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Hooks implementation to test file field validations.
 */
class FileFieldAccessHooks {

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if (\Drupal::state()->get('file_module_test.enable_field_access', FALSE) !== TRUE) {
      return AccessResult::neutral();
    }
    if (\Drupal::state()->get('file_module_test.forbidden_field', '') === '') {
      return AccessResult::neutral();
    }

    if ($field_definition->getName() === \Drupal::state()->get('file_module_test.forbidden_field')) {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

}
