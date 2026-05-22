<?php

namespace Drupal\menu_link_content;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Storage handler for menu_link_content entities.
 */
class MenuLinkContentStorage extends SqlContentEntityStorage implements MenuLinkContentStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkIdsWithPendingRevisions() {
    $table_mapping = $this->getTableMapping();
    $id_field = $table_mapping->getColumnNames($this->entityType->getKey('id'))['value'];
    $revision_field = $table_mapping->getColumnNames($this->entityType->getKey('revision'))['value'];
    $rta_field = $table_mapping->getColumnNames($this->entityType->getKey('revision_translation_affected'))['value'];
    $langcode_field = $table_mapping->getColumnNames($this->entityType->getKey('langcode'))['value'];
    $revision_default_field = $table_mapping->getColumnNames($this->entityType->getRevisionMetadataKey('revision_default'))['value'];

    if ($this->database->driver() == 'mongodb') {
      // @todo Fix this query for MongoDB.
      // See: https://git.drupalcode.org/project/drupal/-/commit/fbdccdc952c53fce12a81ac6640514c52e5fc3af
      return [];
    }
    else {
      $query = $this->database->select($this->getRevisionDataTable(), 'mlfr');
      $query->fields('mlfr', [$id_field]);
      $query->addExpressionMax("mlfr.$revision_field", $revision_field);

      $query->join($this->getRevisionTable(), 'mlr',
        $query->joinCondition()
          ->compare("mlfr.$revision_field", "mlr.$revision_field")
          ->condition("mlr.$revision_default_field", 0)
      );

      $inner_select = $this->database->select($this->getRevisionDataTable(), 't');
      $inner_select->condition("t.$rta_field", '1');
      $inner_select->fields('t', [$id_field, $langcode_field]);
      $inner_select->addExpressionMax("t.$revision_field", $revision_field);
      $inner_select
        ->groupBy("t.$id_field")
        ->groupBy("t.$langcode_field");

      $query->join($inner_select, 'mr',
        $query->joinCondition()
          ->compare("mlfr.$revision_field", "mr.$revision_field")
          ->compare("mlfr.$langcode_field", "mr.$langcode_field")
      );

      $query->groupBy("mlfr.$id_field");

      return $query->execute()->fetchAllKeyed(1, 0);
    }
  }

}
