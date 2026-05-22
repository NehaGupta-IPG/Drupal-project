<?php

namespace Drupal\file;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * File storage for files.
 */
class FileStorage extends SqlContentEntityStorage implements FileStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function spaceUsed($uid = NULL, $status = FileInterface::STATUS_PERMANENT) {
    $query = $this->database->select($this->entityType->getBaseTable(), 'f')
      ->condition('f.status', (bool) $status);
    if (isset($uid)) {
      $query->condition('f.uid', (int) $uid);
    }

    if ($this->database->driver() == 'mongodb') {
      $files = $query->execute()->fetchAll();

      $size = 0;
      foreach ($files as $file) {
        if (isset($file->filesize)) {
          $size += $file->filesize;
        }
      }

      return $size;
    }
    else {
      $query->addExpressionSum('f.filesize', 'filesize');
      return $query->execute()->fetchField();
    }
  }

}
