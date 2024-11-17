<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Functional;

use Drupal\user\EntityOwnerInterface;

/**
 * Provides methods to assert content translation meta data fields.
 *
 * Can be used by test classes that extend \Drupal\Tests\BrowserTestBase.
 */
trait ContentTranslationMetadataTrait {

  /**
   * Gets the metadata field value.
   *
   * @param string $field_name
   *   The field name.
   * @param mixed $field_value
   *   The field value.
   *
   * @return array
   *   The edit array.
   */
  protected function getMetadataValueAndAssertNoField(string $field_name, $field_value): array {
    $edit = [];
    if (!$this->hasMetadataField($field_name)) {
      $edit["content_translation[$field_name]"] = $field_value;
      if ($field_name === 'created') {
        $edit['content_translation[created]'] = $field_value;
        if (is_numeric($field_value)) {
          $edit["content_translation[created]"] = \Drupal::service('date.formatter')->format($field_value, 'custom', 'Y-m-d H:i:s O');
        }
        // Assert there is no native meta data field.
        $this->assertSession()->fieldNotExists('created[0][value][date]');
      }
      elseif ($this->entityTypeId !== 'user') {
        $this->assertSession()->fieldNotExists($field_name);
      }
    }
    else {
      if ($field_name === 'created') {
        $edit['created[0][value][date]'] = $field_value;
        if (is_numeric($field_value)) {
          $edit['created[0][value][date]'] = \Drupal::service('date.formatter')->format($field_value, 'custom', 'Y-m-d');
          $edit['created[0][value][time]'] = \Drupal::service('date.formatter')->format($field_value, 'custom', 'H:i:s');
        }
      }
      else {
        // Try a few different field name patterns.
        if ($this->getSession()->getPage()->findField($field_name . '[value]')) {
          $edit[$field_name . '[value]'] = $field_value;
        }
        elseif ($this->getSession()->getPage()->findField($field_name . '[0][target_id]')) {
          $edit[$field_name . '[0][target_id]'] = $field_value;
        }
        else {
          $edit[$field_name] = $field_value;
        }
      }

      // Assert there is no content translation meta data field.
      $this->assertSession()->fieldNotExists("content_translation[$field_name]");
    }

    return $edit;
  }

  /**
   * Returns whether a given field name exists in the field storage definitions.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if the field has metadata field, FALSE otherwise.
   *
   * @see \Drupal\content_translation\ContentTranslationHandler::checkFieldStorageDefinitionTranslatability
   */
  protected function hasMetadataField(string $field_name): bool {
    $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($this->entityTypeId);
    $has_metadata_field = array_key_exists($field_name, $field_storage_definitions) && $field_storage_definitions[$field_name]->isTranslatable();
    // Check if the entity type implements EntityOwnerInterface for uid field.
    if ($field_name === 'uid') {
      return $has_metadata_field && $this->container->get('entity_type.manager')->getDefinition($this->entityTypeId)->entityClassImplements(EntityOwnerInterface::class);
    }

    return $has_metadata_field;
  }

}
