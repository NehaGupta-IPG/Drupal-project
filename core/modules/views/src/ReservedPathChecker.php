<?php

namespace Drupal\views;

use Drupal\Core\File\FileSystemInterface;

/**
 * Provides discovery of top-level directories in the Drupal web root.
 */
class ReservedPathChecker {
  
  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The application root (docroot).
   *
   * @var string
   */
  protected $appRoot;


  /**
   * Constructs a ReservedPathChecker object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param string $app_root
   *   The application root (docroot).
   */
  public function __construct(FileSystemInterface $file_system, $app_root) {
    $this->fileSystem = $file_system;
    $this->appRoot = $app_root;  
  }

  /**
   * Returns a sorted list of top-level directory names in the docroot.
   *
   * @return string[]
   *   Lowercased directory names present at the docroot root.
   */
  public function listReservedFirstSegments(): array {
    static $cache = null;
    if ($cache !== null) {
      return $cache;
    }

    // Scan only the top-level entries at the docroot.
    $entries = $this->fileSystem->scanDirectory($this->appRoot, '/.*/', [
      'recurse' => FALSE,
      // Do not enforce a specific key; we will iterate paths below.
    ]);

    $reserved = [];
    foreach ($entries as $path => $info) {
      // Ensure we only keep directories.
      if (is_dir($path)) {
        $basename = basename($path);
        // Skip dot-entries just in case (not usually present here).
        if ($basename === '.' || $basename === '..') {
          continue;
        }
        $reserved[] = mb_strtolower($basename);
      }
    }

    sort($reserved, SORT_STRING);
    return $cache = array_values(array_unique($reserved));
  }

}