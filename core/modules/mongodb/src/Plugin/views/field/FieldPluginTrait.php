<?php

namespace Drupal\mongodb\Plugin\views\field;

/**
 * Trait for overriding the FilterPluginBase class.
 */
trait FieldPluginTrait {

  /**
   * {@inheritdoc}
   */
  protected function addAdditionalFields($fields = NULL) {
    if (!isset($fields)) {
      // Notice check.
      if (empty($this->additional_fields)) {
        return;
      }
      $fields = $this->additional_fields;
    }

    $group_params = [];
    if ($this->options['group_type'] != 'group') {
      $group_params = [
        'function' => $this->options['group_type'],
      ];
    }

    if (!empty($fields) && is_array($fields)) {
      foreach ($fields as $identifier => $info) {
        if (is_array($info)) {
          if (isset($info['table'])) {
            $table_alias = $this->query->ensureTable($info['table'], $this->relationship);
          }
          else {
            $table_alias = $this->tableAlias;
          }

          if (empty($table_alias)) {
            trigger_error(sprintf(
              "Handler %s tried to add additional_field %s but %s could not be added!",
              $this->definition['id'],
              $identifier,
              $info['table']
            ), E_USER_WARNING);

            $this->aliases[$identifier] = 'broken';
            continue;
          }

          $params = [];
          if (!empty($info['params'])) {
            $params = $info['params'];
          }

          $params += $group_params;
          $this->aliases[$identifier] = $this->query->addField($table_alias, $info['field'], NULL, $params);
        }
        else {
          $real_field_last_part = '';
          if (!empty($this->realField)) {
            $real_field_parts = explode('.', $this->realField);
            $real_field_last_part = end($real_field_parts);
          }

          if (!empty($real_field_last_part) && ($real_field_last_part == $info)) {
            $alias = $this->tableAlias . '_' . str_replace('.', '_', $info);
            $this->aliases[$info] = $this->query->addField($this->tableAlias, $this->realField, $alias, $group_params);
          }
          else {
            $this->aliases[$info] = $this->query->addField($this->tableAlias, $info, NULL, $group_params);
          }
        }
      }
    }
  }

}
