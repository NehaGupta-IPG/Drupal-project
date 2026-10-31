<?php

declare(strict_types=1);

namespace Drupal\entity_base_field_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds a base field to an entity type provided by another module.
 */
#[Hook('entity_base_field_info')]
final class EntityBaseFieldInfo {

  /**
   * Implements hook_entity_base_field_info().
   */
  public function __invoke(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'entity_test_update') {
      $fields['test_base_field'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('A base field added by another module'));
    }
    return $fields;
  }

}
