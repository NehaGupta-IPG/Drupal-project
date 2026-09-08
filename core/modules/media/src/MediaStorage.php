<?php

namespace Drupal\media;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

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
      // Metadata preparation must compare an historical revision with itself,
      // not with the current default revision. Restore the original afterwards
      // so that the generic storage layer can perform its normal validation.
      $restore_original = FALSE;
      if (
        $media instanceof MediaInterface
        && !$media->isDefaultRevision()
        && !$media->getOriginal()
        && $media->getLoadedRevisionId()
      ) {
        $original = $this->loadRevisionUnchanged(
          $media->getLoadedRevisionId(),
        );
        if ($original) {
          $media->setOriginal($original);
          $restore_original = TRUE;
        }
      }
      try {
        $media->prepareSave();
      }
      finally {
        if ($restore_original) {
          $media->setOriginal(NULL);
        }
      }
    }
    return parent::save($media);
  }

}
