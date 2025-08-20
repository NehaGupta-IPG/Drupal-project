<?php

/**
 * @file
 * Hooks related to content translation and its plugins.
 */

use Drupal\Core\Access\AccessResult;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Control entity translation access.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity to check access to.
 * @param string $operation
 *   The operation that is to be performed on $entity.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The account trying to access the entity.
 *
 * @return \Drupal\Core\Access\AccessResultInterface
 *   The access result. The final result is calculated by using
 *   \Drupal\Core\Access\AccessResultInterface::orIf() on the result of every
 *   hook_entity_translation_access() implementation, and the result of the
 *   permissions checks in getTranslationAccess() method in the content
 *   translation handler.
 *
 * @see \Drupal\content_translation\ContentTranslationHandler
 *
 * @ingroup content_translation
 */
function hook_entity_translation_access(\Drupal\Core\Entity\EntityInterface $entity, $operation, \Drupal\Core\Session\AccountInterface $account) {
  // No opinion.
  return AccessResult::neutral();
}

/**
 * @} End of "addtogroup hooks".
 */
