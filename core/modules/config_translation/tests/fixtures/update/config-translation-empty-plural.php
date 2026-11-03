<?php

/**
 * @file
 * Adds Spanish plural configuration translations with delimiters only.
 *
 * @see \Drupal\Tests\config_translation\Functional\Update\ConfigTranslationEmptyPluralUpdateTest
 */

use Drupal\Component\Gettext\PoItem;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();
$plural = ['display', 'default', 'display_options', 'fields', 'count', 'format_plural_string'];

// The Files view has other Spanish translations.
$data = unserialize($connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', 'language.es')
  ->condition('name', 'views.view.files')
  ->execute()
  ->fetchField(), ['allowed_classes' => FALSE]);
NestedArray::setValue($data, $plural, PoItem::DELIMITER);
$connection->update('config')
  ->fields(['data' => serialize($data)])
  ->condition('collection', 'language.es')
  ->condition('name', 'views.view.files')
  ->execute();

// The Watchdog view has no other Spanish translation.
$data = [];
NestedArray::setValue($data, $plural, PoItem::DELIMITER . PoItem::DELIMITER);
$connection->insert('config')
  ->fields([
    'collection' => 'language.es',
    'name' => 'views.view.watchdog',
    'data' => serialize($data),
  ])
  ->execute();
