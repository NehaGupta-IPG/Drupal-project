<?php

namespace Drupal\media;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Defines the storage handler class for media.
 *
 * The default storage is overridden to handle metadata fetching outside of the
 * database transaction.
 */
class MediaStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $media) {
    // For backwards compatibility, modules that override the Media entity
    // class, are not required to implement the prepareSave() method.
    // @todo For Drupal 8.7, consider throwing a deprecation notice if the
    //   method doesn't exist. See
    //   https://www.drupal.org/project/drupal/issues/2992426 for further
    //   discussion.
    if (method_exists($media, 'prepareSave')) {
      $media->prepareSave();
    }
    return parent::save($media);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures at the configuration storage level.
   */
  protected function doCreate(array $values) {
    if ($this->bundleKey) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo('media');
      if (!in_array($values[$this->bundleKey], array_keys($bundles))) {
        throw new EntityStorageException('The bundle ' . $values[$this->bundleKey] . ' is not a valid Media type');
      }
    }
    return parent::doCreate($values);
  }

}
