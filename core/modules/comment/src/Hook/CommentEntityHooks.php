<?php

declare(strict_types=1);

namespace Drupal\comment\Hook;

use Drupal\comment\CommentingStatus;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\CommentStatisticsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\RoleInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field\FieldConfigInterface;

/**
 * Entity hook implementations for comment.
 */
class CommentEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly StateInterface $state,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CommentManagerInterface $commentManager,
    private readonly CommentStatisticsInterface $commentStatistics,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $return = [];
    foreach (CommentType::loadMultiple() as $comment_type) {
      $return['comment'][$comment_type->id()] = [
        'form' => [
          'author' => [
            'label' => $this->t('Author'),
            'description' => $this->t('Author textfield'),
            'weight' => -2,
          ],
        ],
      ];
      $return['comment'][$comment_type->id()]['display']['links'] = [
        'label' => $this->t('Links'),
        'description' => $this->t('Comment operation links'),
        'weight' => 100,
        'visible' => TRUE,
      ];
    }
    return $return;
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity instanceof FieldableEntityInterface && $view_mode == 'rss' && $display->getComponent('links')) {
      $fields = $this->commentManager->getFields($entity->getEntityTypeId());
      foreach ($fields as $field_name => $detail) {
        if ($entity->hasField($field_name) && $entity->get($field_name)->status != CommentingStatus::Hidden->value) {
          // Add a comments RSS element which is a URL to the comments of this
          // entity.
          $options = ['fragment' => 'comments', 'absolute' => TRUE];
          $entity->rss_elements[] = [
            'key' => 'comments',
            'value' => $entity->toUrl('canonical', $options)->toString(),
          ];
        }
      }
    }
  }

  /**
   * Implements hook_entity_storage_load().
   *
   * @see \Drupal\comment\Plugin\Field\FieldType\CommentItem::propertyDefinitions()
   */
  #[Hook('entity_storage_load')]
  public function entityStorageLoad(array $entities, string $entity_type): void {
    // Comments can only be attached to content entities, so skip others.
    if (!$this->entityTypeManager->getDefinition($entity_type)->entityClassImplements(FieldableEntityInterface::class)) {
      return;
    }
    if (!$this->commentManager->getFields($entity_type)) {
      // Do not query database when entity has no comment fields.
      return;
    }
    // Load comment information from the database and update the entity's
    // comment statistics properties, which are defined on each CommentItem
    // field.
    $result = $this->commentStatistics->read($entities, $entity_type);
    foreach ($result as $record) {
      // Skip fields that entity does not have.
      if (!$entities[$record->entity_id]->hasField($record->field_name)) {
        continue;
      }
      $comment_statistics = $entities[$record->entity_id]->get($record->field_name);
      $comment_statistics->cid = $record->cid;
      $comment_statistics->last_comment_timestamp = $record->last_comment_timestamp;
      $comment_statistics->last_comment_name = $record->last_comment_name;
      $comment_statistics->last_comment_uid = $record->last_comment_uid;
      $comment_statistics->comment_count = $record->comment_count;
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    // Allow bulk updates and inserts to temporarily disable the
    // maintenance of the {comment_entity_statistics} table.
    if ($this->state->get('comment.maintain_entity_statistics') &&
      ($fields = $this->commentManager->getFields($entity->getEntityTypeId()))
    ) {
      $this->commentStatistics->create($entity, $fields);
    }
  }

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    // Entities can have non-numeric IDs, but {comment} and
    // {comment_entity_statistics} tables have integer columns for entity ID,
    // and PostgreSQL throws exceptions if you attempt query conditions with
    // mismatched types. So, we need to verify that the ID is numeric (even for
    // an entity type that has an integer ID, $entity->id() might be a string
    // containing a number), and then cast it to an integer when querying.
    if ($entity instanceof FieldableEntityInterface && is_numeric($entity->id())) {
      $entity_query = $this->entityTypeManager
        ->getStorage('comment')
        ->getQuery()
        ->accessCheck(FALSE);
      $entity_query->condition('entity_id', (int) $entity->id());
      $entity_query->condition('entity_type', $entity->getEntityTypeId());
      $cids = $entity_query->execute();
      $comment_storage = $this->entityTypeManager->getStorage('comment');
      $comments = $comment_storage->loadMultiple($cids);
      $comment_storage->delete($comments);
      $this->commentStatistics->delete($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for entity_view_display entities.
   */
  #[Hook('entity_view_display_presave')]
  public function entityViewDisplayPresave(EntityViewDisplayInterface $display): void {
    // Act only on comment view displays being disabled.
    if ($display->isNew() || $display->getTargetEntityTypeId() !== 'comment' || $display->status()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    if (!$storage->loadUnchanged($display->getOriginalId())->status()) {
      return;
    }
    // Disable the comment field formatter when the used view display is
    // disabled.
    foreach ($storage->loadMultiple() as $view_display) {
      $changed = FALSE;
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
      foreach ($view_display->getComponents() as $field => $component) {
        if (isset($component['type']) && $component['type'] === 'comment_default') {
          if ($component['settings']['view_mode'] === $display->getMode()) {
            $view_display->removeComponent($field);
            /** @var \Drupal\Core\Entity\EntityViewModeInterface $mode */
            $mode = EntityViewMode::load($display->getTargetEntityTypeId() . '.' . $display->getMode());
            if ($mode) {
              $arguments = [
                '@id' => $view_display->id(),
                '@name' => $field,
                '@display' => $mode->label(),
                '@mode' => $display->getMode(),
              ];
              $this->loggerFactory->get('system')->warning("View display '@id': Comment field formatter '@name' was disabled because it is using the comment view display '@display' (@mode) that was just disabled.", $arguments);
              $changed = TRUE;
            }
          }
        }
      }
      if ($changed) {
        $view_display->save();
      }
    }
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel($edit, UserInterface $account, $method): void {
    switch ($method) {
      case 'user_cancel_block_unpublish':
        $comments = $this->entityTypeManager->getStorage('comment')->loadByProperties(['uid' => $account->id()]);
        foreach ($comments as $comment) {
          $comment->setUnpublished();
          $comment->save();
        }
        break;

      case 'user_cancel_reassign':
        /** @var \Drupal\comment\CommentInterface[] $comments */
        $comments = $this->entityTypeManager->getStorage('comment')->loadByProperties(['uid' => $account->id()]);
        foreach ($comments as $comment) {
          $langcodes = array_keys($comment->getTranslationLanguages());
          // For efficiency manually set the original comment before applying
          // any changes.
          $comment->setOriginal(clone $comment);
          foreach ($langcodes as $langcode) {
            $comment_translated = $comment->getTranslation($langcode);
            $comment_translated->setOwnerId(0);
            $comment_translated->setAuthorName($this->configFactory->get('user.settings')->get('anonymous'));
          }
          $comment->save();
        }
        break;
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete() for user entities.
   */
  #[Hook('user_predelete')]
  public function userPredelete(UserInterface $account): void {
    $entity_query = $this->entityTypeManager
      ->getStorage('comment')
      ->getQuery()
      ->accessCheck(FALSE);
    $entity_query->condition('uid', $account->id());
    $cids = $entity_query->execute();
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $comments = $comment_storage->loadMultiple($cids);
    $comment_storage->delete($comments);
  }

  /**
   * Implements hook_node_update_index().
   */
  #[Hook('node_update_index')]
  public function nodeUpdateIndex(EntityInterface $node): string {
    $index_comments =& drupal_static('comment_node_update_index');
    if ($index_comments === NULL) {
      // Do not index in the following three cases:
      // 1. 'Authenticated user' can search content but can't access comments.
      // 2. 'Anonymous user' can search content but can't access comments.
      // 3. Any role can search content but can't access comments and access
      // comments is not granted by the 'authenticated user' role. In this case
      // all users might have both permissions from various roles but it is also
      // possible to set up a user to have only search content and so a user
      // edit could change the security situation so it is not safe to index the
      // comments.
      $index_comments = TRUE;
      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
      $authenticated_can_access = $roles[RoleInterface::AUTHENTICATED_ID]->hasPermission('access comments');
      foreach ($roles as $rid => $role) {
        if ($role->hasPermission('search content') && !$role->hasPermission('access comments')) {
          if ($rid == RoleInterface::AUTHENTICATED_ID || $rid == RoleInterface::ANONYMOUS_ID || !$authenticated_can_access) {
            $index_comments = FALSE;
            break;
          }
        }
      }
    }
    $build = [];
    if ($index_comments) {
      foreach ($this->commentManager->getFields('node') as $field_name => $info) {
        // Skip fields that entity does not have.
        if (!$node->hasField($field_name)) {
          continue;
        }
        $field_definition = $node->getFieldDefinition($field_name);
        $mode = $field_definition->getSetting('default_mode');
        $comments_per_page = $field_definition->getSetting('per_page');
        if ($node->get($field_name)->status) {
          $comments = $this->entityTypeManager->getStorage('comment')->loadThread($node, $field_name, $mode, $comments_per_page);
          if ($comments) {
            $build[] = $this->entityTypeManager->getViewBuilder('comment')->viewMultiple($comments);
          }
        }
      }
    }
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Implements hook_node_search_result().
   *
   * Formats a comment count string and returns it, for display with search
   * results.
   */
  #[Hook('node_search_result')]
  public function nodeSearchResult(EntityInterface $node): array {
    $comment_fields = $this->commentManager->getFields('node');
    $comments = 0;
    $open = FALSE;
    foreach ($comment_fields as $field_name => $info) {
      // Skip fields that entity does not have.
      if (!$node->hasField($field_name)) {
        continue;
      }
      // Do not make a string if comments are hidden.
      $status = CommentingStatus::tryFrom((int) $node->get($field_name)->status);
      if ($this->currentUser->hasPermission('access comments') && $status != CommentingStatus::Hidden) {
        if ($status == CommentingStatus::Open) {
          // At least one comment field is open.
          $open = TRUE;
        }
        $comments += $node->get($field_name)->comment_count;
      }
    }
    // Do not make a string if there are no comment fields, or no comments exist
    // or all comment fields are hidden.
    if ($comments > 0 || $open) {
      return [
        'comment' => $this->stringTranslation->formatPlural(
          $comments,
          '1 comment',
          '@count comments'
        ),
      ];
    }
    return [];
  }

  /**
   * Implements hook_node_search_ranking().
   */
  #[Hook('node_search_ranking')]
  public function ranking(): array {
    return $this->commentStatistics->getRankingInfo();
  }

  /**
   * Implements hook_ENTITY_TYPE_create() for 'field_config'.
   */
  #[Hook('field_config_create')]
  public function fieldConfigCreate(FieldConfigInterface $field): void {
    if ($field->getType() == 'comment' && !$field->isSyncing()) {
      // Assign default values for the field.
      $default_value = $field->getDefaultValueLiteral();
      $default_value += [[]];
      $default_value[0] += [
        'status' => CommentingStatus::Open->value,
        'cid' => 0,
        'last_comment_timestamp' => 0,
        'last_comment_name' => '',
        'last_comment_uid' => 0,
        'comment_count' => 0,
      ];
      $field->setDefaultValue($default_value);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_config'.
   */
  #[Hook('field_config_update')]
  public function fieldConfigUpdate(FieldConfigInterface $field): void {
    if ($field->getType() == 'comment') {
      // Comment field settings also affects the rendering of *comment*
      // entities, not only the *commented* entities.
      $this->entityTypeManager->getViewBuilder('comment')->resetCache();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'field_storage_config'.
   */
  #[Hook('field_storage_config_insert')]
  public function fieldStorageConfigInsert(FieldStorageConfigInterface $field_storage): void {
    if ($field_storage->getType() == 'comment') {
      // Check that the target entity type uses an integer ID.
      $entity_type = $this->entityTypeManager->getDefinition($field_storage->getTargetEntityTypeId());
      if (!$entity_type->hasIntegerId()) {
        throw new \UnexpectedValueException('You cannot attach a comment field to an entity with a non-integer ID field');
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for 'field_config'.
   */
  #[Hook('field_config_delete')]
  public function fieldConfigDelete(FieldConfigInterface $field): void {
    if ($field->getType() == 'comment') {
      // Delete all comments that used by the entity bundle.
      $entity_query = $this->entityTypeManager
        ->getStorage('comment')
        ->getQuery()
        ->accessCheck(FALSE);
      $entity_query->condition('entity_type', $field->getEntityTypeId());
      $entity_query->condition('field_name', $field->getName());
      $cids = $entity_query->execute();
      $comment_storage = $this->entityTypeManager->getStorage('comment');
      $comments = $comment_storage->loadMultiple($cids);
      $comment_storage->delete($comments);
    }
  }

}
