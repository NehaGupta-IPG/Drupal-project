<?php

namespace Drupal\comment;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the comment entity type.
 *
 * @see \Drupal\comment\Entity\Comment
 */
class CommentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\comment\CommentInterface $entity */

    $comment_admin = $account->hasPermission('administer comments');
    if ($operation == 'approve') {
      return AccessResult::allowedIf($comment_admin && !$entity->isPublished())
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    if ($operation === 'reply') {
      $access = $this->checkReplyThreadAccess($entity)->addCacheableDependency($entity);

      if (!$access->isForbidden()) {
        $context = array_filter(
          [
            'commented_entity' => $entity->getCommentedEntity(),
            'field' => $entity->getFieldName(),
            'parent' => $entity,
          ],
          static fn (mixed $val): bool => $val !== NULL,
        );
        $access = $access->orIf(
          // The user must be able to view the comment.
          $this->access($entity, 'view', $account, TRUE)
            // And must be able to create comments.
            ->andIf($this->createAccess($entity->bundle(), $account, $context, TRUE))
            // And comment must be published.
            ->andIf(AccessResult::allowedIf($entity->isPublished()))
        );
      }
      return $access;
    }

    if ($comment_admin) {
      $access = AccessResult::allowed()->cachePerPermissions();
      return ($operation != 'view') ? $access : $access->andIf($entity->getCommentedEntity()->access($operation, $account, TRUE));
    }

    switch ($operation) {
      case 'view':
        $access_result = AccessResult::allowedIf($account->hasPermission('access comments') && $entity->isPublished())->cachePerPermissions()->addCacheableDependency($entity)
          ->andIf($entity->getCommentedEntity()->access($operation, $account, TRUE));
        if (!$access_result->isAllowed()) {
          $access_result->setReason("The 'access comments' permission is required and the comment must be published.");
        }

        return $access_result;

      case 'update':
        $access_result = AccessResult::allowedIf($account->id() && $account->id() == $entity->getOwnerId() && $entity->isPublished() && $account->hasPermission('edit own comments'))
          ->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        if (!$access_result->isAllowed()) {
          $access_result->setReason("The 'edit own comments' permission is required, the user must be the comment author, and the comment must be published.");
        }
        return $access_result;

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'post comments');
  }

  /**
   * Checks if this comment accepts replies according to field config.
   */
  protected function checkReplyThreadAccess(CommentInterface $comment): AccessResultInterface {
    $access_result = AccessResult::neutral();
    $commented_entity = $comment->getCommentedEntity();
    if (!$commented_entity instanceof FieldableEntityInterface) {
      return $access_result;
    }

    // Forbid if this reply is about to exceed the maximum thread depth.
    $field_definition = $commented_entity->getFieldDefinition($comment->getFieldName());
    \assert($field_definition instanceof FieldDefinitionInterface);
    $access_result->addCacheableDependency($field_definition->getConfig($commented_entity->bundle()));
    $reply_depth = $field_definition->getSetting('thread_reply_depth');

    // Display mode is really only about how comments and their replies are
    // displayed. So, we check thread depth regardless of which display mode is
    // set. A NULL depth means reply depth is unlimited.
    if ($reply_depth !== NULL) {
      \assert(is_int($reply_depth) && $reply_depth >= 0, 'Thread reply depth should be greater than or equal to 0.');
      // Prevent replying to comments on the deepest level.
      $comment_indent = count(explode('.', $comment->getThread()));
      $access_result = $access_result->andIf(AccessResult::forbiddenIf($comment_indent > $reply_depth, 'Thread limit hit'));
    }

    return $access_result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL) {
    if ($operation == 'edit') {
      // Only users with the "administer comments" permission can edit
      // administrative fields.
      $administrative_fields = [
        'uid',
        'status',
        'created',
        'date',
      ];
      if (in_array($field_definition->getName(), $administrative_fields, TRUE)) {
        return AccessResult::allowedIfHasPermission($account, 'administer comments');
      }

      // No user can change read-only fields.
      $read_only_fields = [
        'hostname',
        'changed',
        'cid',
        'thread',
      ];
      // These fields can be edited during comment creation.
      $create_only_fields = [
        'comment_type',
        'uuid',
        'entity_id',
        'entity_type',
        'field_name',
        'pid',
      ];
      /** @var \Drupal\comment\CommentInterface|null $entity */
      $entity = $items ? $items->getEntity() : NULL;
      $commented_entity = $entity ? $entity->getCommentedEntity() : NULL;
      if ($entity && $entity->isNew() && in_array($field_definition->getName(), $create_only_fields, TRUE)) {
        $access_result = AccessResult::allowedIfHasPermission($account, 'post comments')
          ->addCacheableDependency($entity);
        $comment_field_name = $entity->get('field_name')->value;
        if ($commented_entity && $comment_field_name) {
          // We are creating a new comment, user can edit create only fields if
          // commenting is open.
          $commenting_status = CommentingStatus::tryFrom((int) $commented_entity->get($comment_field_name)->status);
          $access_result = $access_result
            ->andIf(AccessResult::allowedIf($commenting_status !== CommentingStatus::Closed))
            ->addCacheableDependency($commented_entity);
        }
        return $access_result;
      }
      // We are editing an existing comment - create only fields are now read
      // only.
      $read_only_fields = array_merge($read_only_fields, $create_only_fields);
      if (in_array($field_definition->getName(), $read_only_fields, TRUE)) {
        return AccessResult::forbidden();
      }

      // If the field is configured to accept anonymous contact details - admins
      // can edit name, homepage and mail. Anonymous users can also fill in the
      // fields on comment creation.
      if (in_array($field_definition->getName(), ['name', 'mail', 'homepage'], TRUE)) {
        if (!$items) {
          // We cannot make a decision about access to edit these fields if we
          // don't have any items and therefore cannot determine the Comment
          // entity. In this case we err on the side of caution and prevent edit
          // access.
          return AccessResult::forbidden();
        }
        $is_name = $field_definition->getName() === 'name';
        $anonymous_contact = AnonymousContact::tryFrom($commented_entity->get($entity->getFieldName())->getFieldDefinition()->getSetting('anonymous'));
        $admin_access = AccessResult::allowedIfHasPermission($account, 'administer comments');
        $anonymous_access = AccessResult::allowedIf($entity->isNew() && $account->isAnonymous() && ($anonymous_contact != AnonymousContact::Forbidden || $is_name) && $account->hasPermission('post comments'))
          ->cachePerPermissions()
          ->addCacheableDependency($entity)
          ->addCacheableDependency($field_definition->getConfig($commented_entity->bundle()))
          ->addCacheableDependency($commented_entity);
        return $admin_access->orIf($anonymous_access);
      }
    }

    if ($operation == 'view') {
      // Nobody has access to the hostname.
      if ($field_definition->getName() == 'hostname') {
        return AccessResult::forbidden();
      }
      // The mail field is hidden from non-admins.
      if ($field_definition->getName() == 'mail') {
        return AccessResult::allowedIfHasPermission($account, 'administer comments');
      }
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildCreateAccessCid(array $context, ?string $entity_bundle): ?string {
    $defaults = ['commented_entity' => NULL, 'field' => NULL, 'parent' => NULL];
    [
      'commented_entity' => $commented_entity,
      'field' => $field,
      'parent' => $parent,
    ] = $context += $defaults;

    // Without a specific commented entity, or comment field, the CID would be
    // ambiguous across entities or fields and could cause stale cache hits for
    // the wrong entity context.
    if (!$commented_entity instanceof EntityInterface || !$field) {
      return NULL;
    }

    // Don't cache in case of a new, unsaved commented entity or parent.
    if (
      $commented_entity->isNew() ||
      ($parent instanceof CommentInterface && $parent->isNew())
    ) {
      return NULL;
    }

    $parent_context = array_diff_key($context, $defaults);
    $cid = parent::buildCreateAccessCid($parent_context, $entity_bundle);
    if ($cid === NULL) {
      return NULL;
    }

    // Vary by commented entity type, ID, comment field, and parent comment ID.
    return implode(':', [
      $cid,
      $commented_entity->getEntityTypeId(),
      $commented_entity->id(),
      $field,
      $parent?->id(),
    ]);
  }

}
