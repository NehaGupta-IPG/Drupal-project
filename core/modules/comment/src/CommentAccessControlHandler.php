<?php

namespace Drupal\comment;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
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
    /** @var \Drupal\comment\CommentInterface|\Drupal\user\EntityOwnerInterface $entity */

    $comment_admin = $account->hasPermission('administer comments');
    if ($operation == 'approve') {
      return AccessResult::allowedIf($comment_admin && !$entity->isPublished())
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    if ($operation === 'reply') {
      $access = $this->checkReplyThreadAccess($entity)->addCacheableDependency($entity);

      if ($access->isAllowed()) {
        $context = ['commented_entity' => $entity->getCommentedEntity()];
        return $access
          // The user must be able to view the comment.
          ->andIf($this->checkAccess($entity, 'view', $account))
          // And must be able to create comments.
          ->andIf($this->createAccess($entity->bundle(), $account, $context, TRUE))
          // And comment must be published.
          ->andIf(AccessResult::allowedIf($entity->isPublished()));
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
    $access_result = AccessResult::allowed();
    $commented_entity = $comment->getCommentedEntity();
    if (!$commented_entity instanceof EntityInterface) {
      return $access_result;
    }

    // Forbid if this reply, to a threaded comment, is about to exceed the
    // maximum thread depth.
    $field_definition = $commented_entity->getFieldDefinition($comment->getFieldName());
    \assert($field_definition instanceof FieldDefinitionInterface);
    $access_result->addCacheableDependency($field_definition->getConfig($commented_entity->bundle()));
    $field_settings = $field_definition->getSettings();

    // Only check if the depth is limited.
    if ($field_settings && $field_settings['default_mode'] === CommentManagerInterface::COMMENT_MODE_THREADED_DEPTH_LIMIT) {
      $thread_limit_settings = $field_settings['thread_limit'];
      \assert($field_settings['thread_limit']['depth'] >= 2, 'Thread depth limit should be greater than or equal to 2.');

      // Only check if the depth limitation is configured to deny replies.
      if ($thread_limit_settings['mode'] === CommentItemInterface::THREAD_DEPTH_REPLY_MODE_DENY) {
        // Prevent replying to the deepest comment.
        $comment_indent = count(explode('.', $comment->getThread()));
        $max_indent = $field_settings['thread_limit']['depth'];
        if ($comment_indent >= $max_indent) {
          return $access_result->andIf(AccessResult::forbidden('Thread limit hit'));
        }
      }
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
          $commenting_status = (int) $commented_entity->get($comment_field_name)->status;
          $access_result = $access_result
            ->andIf(AccessResult::allowedIf($commenting_status !== CommentItemInterface::CLOSED))
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
        $anonymous_contact = $commented_entity->get($entity->getFieldName())->getFieldDefinition()->getSetting('anonymous');
        $admin_access = AccessResult::allowedIfHasPermission($account, 'administer comments');
        $anonymous_access = AccessResult::allowedIf($entity->isNew() && $account->isAnonymous() && ($anonymous_contact != CommentInterface::ANONYMOUS_MAYNOT_CONTACT || $is_name) && $account->hasPermission('post comments'))
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
  protected function buildCreateAccessCid(array $context, ?string $entity_bundle): string {
    $commented_entity_id = isset($context['commented_entity']) ? $context['commented_entity']->id() : '0';
    unset($context['commented_entity']);

    $cid = parent::buildCreateAccessCid($context, $entity_bundle);
    $cid ??= "create:$entity_bundle:";

    // Vary the static access cache depending on the commented/host entity.
    return $cid . $commented_entity_id;
  }

}
