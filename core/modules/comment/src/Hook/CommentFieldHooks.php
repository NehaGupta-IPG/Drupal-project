<?php

namespace Drupal\comment\Hook;

use Drupal\Core\Field\FieldTypeCategoryManagerInterface;
use Drupal\comment\CommentingStatus;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Field hook implementations for comment.
 */
class CommentFieldHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_field_info_entity_type_ui_definitions_alter().
   */
  #[Hook('field_info_entity_type_ui_definitions_alter')]
  public function fieldInfoEntityTypeUiDefinitionsAlter(array &$ui_definitions, string $entity_type_id): void {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$entity_type->hasIntegerId()) {
      unset($ui_definitions['comment']);
    }
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

  /**
   * Implements hook_field_type_category_info_alter().
   */
  #[Hook('field_type_category_info_alter')]
  public function fieldTypeCategoryInfoAlter(array &$definitions): void {
    // The `comment` field type belongs in the `general` category, so the
    // libraries need to be attached using an alter hook.
    $definitions[FieldTypeCategoryManagerInterface::FALLBACK_CATEGORY]['libraries'][] = 'comment/drupal.comment-icon';
  }

}
