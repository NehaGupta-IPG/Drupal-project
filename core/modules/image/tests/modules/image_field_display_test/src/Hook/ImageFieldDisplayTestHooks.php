<?php

declare(strict_types=1);

namespace Drupal\image_field_display_test\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Implements hook_entity_field_access().
 */
#[Hook('entity_field_access')]
class ImageFieldDisplayTestHooks {

  /**
   * Implements hook_entity_field_access().
   */
  public function __invoke(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($operation === 'view'
        && $field_definition instanceof FieldConfigInterface
        && $field_definition->getThirdPartySetting('image_field_display_test', 'access_denied', FALSE)) {
      return AccessResult::forbidden()->addCacheableDependency($field_definition);
    }
    return AccessResult::neutral();
  }

}
