<?php

namespace Drupal\options\Hook;

use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for options.
 */
class OptionsViewsHooks {

  /**
   * Implements hook_field_views_data().
   *
   * Views integration for list fields. Have a different filter handler and
   * argument handlers for list fields. This should allow to select values of
   * the list.
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field): array {
    $data = \Drupal::service('views.field_data_provider')->defaultFieldImplementation($field);
    foreach ($data as $table_name => $table_data) {
      foreach ($table_data as $field_name => $field_data) {
        if (isset($field_data['filter']) && $field_name != 'delta') {
          $data[$table_name][$field_name]['filter']['id'] = 'list_field';

          // Add a numeric filter for numeric list fields.
          if ($field->getType() != 'list_string') {
            $data[$table_name][$field_name . '_numeric'] = [
              'group' => $data[$table_name][$field_name]['group'],
              'title' => $data[$table_name][$field_name]['title'] . ' ' . t('(numeric)'),
              'title short' => $data[$table_name][$field_name]['title short'] . ' ' . t('(numeric)'),
              'help' => t('Numeric filter for %label field.', ['%label' => $data[$table_name][$field_name]['title']]) . ' ' . $data[$table_name][$field_name]['help'],
              'filter' => ['id' => 'numeric'] + $data[$table_name][$field_name]['filter'],
            ];
          }
        }
        if (isset($field_data['argument']) && $field_name != 'delta') {
          if ($field->getType() == 'list_string') {
            $data[$table_name][$field_name]['argument']['id'] = 'string_list_field';
          }
          else {
            $data[$table_name][$field_name]['argument']['id'] = 'number_list_field';
          }
        }
      }
    }
    return $data;
  }

}
