<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

/**
 * The MongoDB service for translating table schema's.
 */
class TranslateSchema {

  /**
   * Translate a schema to its MongoDB equivalent.
   */
  public static function createTable($name, $schema) {
    switch ($name) {

      // Overrides for the module "comment".
      case 'comment_entity_statistics':
        if (isset($schema['fields']['last_comment_timestamp']['type'])) {
          $schema['fields']['last_comment_timestamp']['type'] = 'date';
        }
        break;

      // Overrides for the module "dblog".
      case 'watchdog':
        if (isset($schema['fields']['timestamp']['type'])) {
          $schema['fields']['timestamp']['type'] = 'date';
        }
        break;

      // Overrides for the module "history".
      case 'history':
        if (isset($schema['fields']['timestamp']['type'])) {
          $schema['fields']['timestamp']['type'] = 'date';
        }
        break;

      // Overrides for the module "locale".
      case 'locales_target':
        if (isset($schema['fields']['customized']['type'])) {
          $schema['fields']['customized']['type'] = 'bool';
        }
        if (isset($schema['fields']['customized']['default'])) {
          $schema['fields']['customized']['default'] = FALSE;
        }
        break;

      // Overrides for the module "node".
      case 'node_access':
        if (isset($schema['fields']['fallback']['type'])) {
          $schema['fields']['fallback']['type'] = 'bool';
        }
        if (isset($schema['fields']['fallback']['default'])) {
          $schema['fields']['fallback']['default'] = TRUE;
        }
        unset($schema['fields']['fallback']['unsigned']);
        unset($schema['fields']['fallback']['size']);

        if (isset($schema['fields']['grant_view']['type'])) {
          $schema['fields']['grant_view']['type'] = 'bool';
        }
        if (isset($schema['fields']['grant_view']['default'])) {
          $schema['fields']['grant_view']['default'] = TRUE;
        }
        unset($schema['fields']['grant_view']['unsigned']);
        unset($schema['fields']['grant_view']['size']);

        if (isset($schema['fields']['grant_update']['type'])) {
          $schema['fields']['grant_update']['type'] = 'bool';
        }
        if (isset($schema['fields']['grant_update']['default'])) {
          $schema['fields']['grant_update']['default'] = TRUE;
        }
        unset($schema['fields']['grant_update']['unsigned']);
        unset($schema['fields']['grant_update']['size']);

        if (isset($schema['fields']['grant_delete']['type'])) {
          $schema['fields']['grant_delete']['type'] = 'bool';
        }
        if (isset($schema['fields']['grant_delete']['default'])) {
          $schema['fields']['grant_delete']['default'] = TRUE;
        }
        unset($schema['fields']['grant_delete']['unsigned']);
        unset($schema['fields']['grant_delete']['size']);
        break;

      // Overrides for the module "system".
      case 'sessions':
        if (isset($schema['fields']['timestamp']['type'])) {
          $schema['fields']['timestamp']['type'] = 'date';
        }
        break;

      // Overrides for the module "user".
      case 'users_data':
        if (isset($schema['fields']['serialized']['type'])) {
          $schema['fields']['serialized']['type'] = 'bool';
        }
        if (isset($schema['fields']['serialized']['default'])) {
          $schema['fields']['serialized']['default'] = FALSE;
        }
        unset($schema['fields']['serialized']['unsigned']);
        unset($schema['fields']['serialized']['size']);
    }

    return $schema;
  }

}
