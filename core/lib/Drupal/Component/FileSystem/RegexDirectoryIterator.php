<?php

namespace Drupal\Component\FileSystem;

/**
 * Iterates over files whose names match a regular expression in a directory.
 */
class RegexDirectoryIterator extends \RegexIterator {

  public function __construct($path, $regex) {
    parent::__construct(new \FilesystemIterator($path), $regex);
  }

  /**
   * Implements \RegexIterator::accept().
   */
  public function accept(): bool {
    /** @var \SplFileInfo $file_info */
    $file_info = $this->getInnerIterator()->current();
    return $file_info->isFile() && preg_match($this->getRegex(), $file_info->getFilename());
  }

}
