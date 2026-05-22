<?php

namespace Drupal\mongodb\modules\views;

use Drupal\views\Entity\Render\TranslationLanguageRenderer as CoreTranslationLanguageRenderer;
use Drupal\views\Plugin\views\query\QueryPluginBase;

/**
 * MongoDB override \Drupal\views\Entity\Render\TranslationLanguageRenderer.
 */
class TranslationLanguageRenderer extends CoreTranslationLanguageRenderer {

  /**
   * {@inheritdoc}
   */
  public function query(QueryPluginBase $query, $relationship = NULL) {
    // In order to render in the translation language of the entity, we need
    // to add the language code of the entity to the query. Skip if the site
    // is not multilingual or the entity is not translatable.
    if (!$this->languageManager->isMultilingual() || !$this->entityType->hasKey('langcode')) {
      return;
    }
    $langcode_table = $this->getLangcodeTable($query, $relationship);
    if ($langcode_table) {
      /** @var \Drupal\views\Plugin\views\query\Sql $query */
      $table_alias = $query->ensureTable($langcode_table, $relationship);
      $langcode_key = $this->entityType->getKey('langcode');

      // For MongoDB the entity field data is stored in an embedded table and
      // needs to be unwound "$unwind" in the database query before it can be
      // used by Drupal.
      $storage = \Drupal::entityTypeManager()->getStorage($this->entityType->id());
      if ($this->entityType->isRevisionable()) {
        // Do this if the base table is part of a revisionable entity.
        if ($this->view->storage->get('mongodb_base_table') != $this->view->storage->get('original_base_table')) {
          if (!empty($this->view->storage->get('all_revisions_table'))) {
            $langcode_key = $storage->getJsonStorageAllRevisionsTable() . '.' . $langcode_key;
          }
          elseif (!empty($this->view->storage->get('current_revision_table'))) {
            $langcode_key = $storage->getJsonStorageCurrentRevisionTable() . '.' . $langcode_key;
          }
        }
        else {
          $langcode_key = $storage->getJsonStorageCurrentRevisionTable() . '.' . $langcode_key;
        }
      }
      elseif ($this->entityType->isTranslatable()) {
        $langcode_key = $storage->getJsonStorageTranslationsTable() . '.' . $langcode_key;
      }

      $this->langcodeAlias = $query->addField($table_alias, $langcode_key);
    }
  }

}
