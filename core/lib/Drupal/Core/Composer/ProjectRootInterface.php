<?php

namespace Drupal\Core\Composer;

/**
 * Provides information about the Composer project root.
 */
interface ProjectRootInterface {

  /**
   * Gets the vendor directory for the project.
   *
   * @return string
   *   Absolute path to the vendor directory.
   */
  public function getProjectVendorDirectory(): string;

  /**
   * Gets whether Composer dev dependencies are installed.
   *
   * @return bool
   *   TRUE if dev dependencies are installed.
   */
  public function isDevInstall(): bool;

}
