<?php

namespace Drupal\Core\File;

/**
 * Enum representing different file types.
 *
 * This enum defines the possible types of files that can be handled by the
 * Drupal file system, including regular files, directories, and symbolic links.
 */
enum FileType: string {
  case File = 'file';
  case Directory = 'dir';
  case Link = 'link';
}
