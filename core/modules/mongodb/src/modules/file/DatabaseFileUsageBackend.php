<?php

namespace Drupal\mongodb\modules\file;

use Drupal\file\FileInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend as CoreDatabaseFileUsageBackend;
use Drupal\file\FileUsage\FileUsageBase;

/**
 * MongoDB implementation of \Drupal\file\FileUsage\DatabaseFileUsageBackend.
 */
class DatabaseFileUsageBackend extends CoreDatabaseFileUsageBackend {

  /**
   * {@inheritdoc}
   */
  public function add(FileInterface $file, $module, $type, $id, $count = 1) {
    $prefixed_table = $this->connection->getPrefix() . $this->tableName;
    $this->connection->getConnection()->selectCollection($prefixed_table)->updateMany(
      [
        'fid' => (int) $file->id(),
        'module' => $module,
        'type' => $type,
        'id' => (string) $id,
      ],
      [
        '$inc' => ['count' => $count],
      ],
      [
        'upsert' => TRUE,
        'session' => $this->connection->getMongodbSession(),
      ]
    );

    FileUsageBase::add($file, $module, $type, $id, $count);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(FileInterface $file, $module, $type = NULL, $id = NULL, $count = 1) {
    // Delete rows that have a exact or less value to prevent empty rows.
    $query = $this->connection->delete($this->tableName)
      ->condition('module', (string) $module)
      ->condition('fid', (int) $file->id());
    if ($type && $id) {
      $query
        ->condition('type', (string) $type)
        ->condition('id', (string) $id);
    }
    if ($count) {
      $query->condition('count', $count, '<=');
    }
    $result = $query->execute();

    // If the row has more than the specified count decrement it by that number.
    if (!$result && $count > 0) {
      $conditions = [
        'module' => (string) $module,
        'fid' => (int) $file->id(),
      ];
      if ($type && $id) {
        $conditions['type'] = (string) $type;
        $conditions['id'] = (string) $id;
      }
      $prefixed_table = $this->connection->getPrefix() . $this->tableName;
      $this->connection->getConnection()->selectCollection($prefixed_table)->updateMany(
        $conditions,
        [
          '$inc' => ['count' => ($count * -1)],
        ],
        ['session' => $this->connection->getMongodbSession()],
      );
    }

    FileUsageBase::delete($file, $module, $type, $id, $count);
  }

  /**
   * {@inheritdoc}
   */
  public function listUsage(FileInterface $file) {
    $result = $this->connection->select($this->tableName, 'f')
      ->fields('f', ['module', 'type', 'id', 'count'])
      ->condition('fid', (int) $file->id())
      ->condition('count', 0, '>')
      ->execute();
    $references = [];
    foreach ($result as $usage) {
      $references[$usage->module][$usage->type][$usage->id] = $usage->count;
    }
    return $references;
  }

}
