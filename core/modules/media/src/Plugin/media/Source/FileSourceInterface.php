<?php

declare(strict_types=1);

namespace Drupal\media\Plugin\media\Source;

use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;

/**
 * Defines additional functionality interface for file-based media sources.
 */
interface FileSourceInterface extends MediaSourceInterface {

  /**
   * Returns the file associated with a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to get the file from.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity associated with the media, or NULL if the media has no
   *   file associated with it.
   */
  public function getFile(MediaInterface $media): ?FileInterface;

}
