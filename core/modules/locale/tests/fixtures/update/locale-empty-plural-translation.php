<?php

/**
 * @file
 * Adds a Spanish plural translation without text, and one with text.
 *
 * @see \Drupal\Tests\locale\Functional\Update\LocaleEmptyPluralTranslationUpdateTest
 */

use Drupal\Component\Gettext\PoItem;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();
$translations = [
  '@count apple' => '',
  '@count pear' => '@count pear ES',
];
foreach ($translations as $singular => $translation) {
  $lid = $connection->insert('locales_source')
    ->fields([
      'source' => $singular . PoItem::DELIMITER . $singular . 's',
      'context' => '',
      'version' => '11.3.0',
    ])
    ->execute();
  $connection->insert('locales_target')
    ->fields([
      'lid' => $lid,
      'translation' => $translation . PoItem::DELIMITER . $translation,
      'language' => 'es',
      'customized' => 0,
    ])
    ->execute();
}
