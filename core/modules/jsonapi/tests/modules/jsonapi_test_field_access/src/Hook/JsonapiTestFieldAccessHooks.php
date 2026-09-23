<?php

declare(strict_types=1);

namespace Drupal\jsonapi_test_field_access\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Hook implementations for jsonapi_test_field_access.
 */
class JsonapiTestFieldAccessHooks {

  /**
   * Implements hook_entity_field_access().
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    // @see \Drupal\Tests\jsonapi\Functional\ResourceTestBase::testRelationships().
    if ($field_definition->getName() === 'field_jsonapi_test_entity_ref') {
      // Forbid access in all cases.
      $permission = "field_jsonapi_test_entity_ref {$operation} access";
      $access_result = $account->hasPermission($permission) ? AccessResult::allowed() : AccessResult::forbidden("The '{$permission}' permission is required.");
      return $access_result->addCacheContexts(['user.permissions']);
    }
    // Deny edit access to field_test_file when $items is present.
    // @see \Drupal\Tests\jsonapi\Kernel\Controller\FileUploadTest
    if ($operation === 'edit' && $field_definition->getName() === 'field_test_file' && $items !== NULL) {
      return AccessResult::forbidden('Field edit access denied when items context is present.')
        ->cachePerPermissions();
    }
    // No opinion.
    return AccessResult::neutral();
  }

}
