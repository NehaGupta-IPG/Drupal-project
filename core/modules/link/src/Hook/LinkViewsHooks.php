<?php

declare(strict_types=1);

namespace Drupal\link\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Views hook implementations for the link module.
 */
class LinkViewsHooks {

  /**
   * Implements hook_field_views_data_alter().
   *
   * Set Link fields as "click sortable" in Views.
   *
   * @see FieldViewsDataProvider::defaultFieldImplementation()
   */
  #[Hook('field_views_data_alter')]
  public function fieldViewsDataAlter(array &$data, FieldStorageConfigInterface $field_storage) {
    if ($field_storage->getType() == 'link') {
      foreach ($data as $table_name => $table_data) {
        foreach ($table_data as $field_name => $field_data) {
          if (isset($data[$table_name][$field_name]['field']['click sortable'])) {
            $data[$table_name][$field_name]['field']['click sortable'] = TRUE;
          }
        }
      }
    }
  }

}
