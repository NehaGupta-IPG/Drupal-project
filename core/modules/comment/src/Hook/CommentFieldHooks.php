<?php

declare(strict_types=1);

namespace Drupal\comment\Hook;

use Drupal\Core\Field\FieldTypeCategoryManagerInterface;
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
   * Implements hook_field_type_category_info_alter().
   */
  #[Hook('field_type_category_info_alter')]
  public function fieldTypeCategoryInfoAlter(array &$definitions): void {
    // The `comment` field type belongs in the `general` category, so the
    // libraries need to be attached using an alter hook.
    $definitions[FieldTypeCategoryManagerInterface::FALLBACK_CATEGORY]['libraries'][] = 'comment/drupal.comment-icon';
  }

}
