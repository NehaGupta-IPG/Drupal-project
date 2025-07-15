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
  public function getProjectVendorDirectory();

  /**
   * Gets Composer packages that are currently installed.
   *
   * @return string[]
   *   An associative array of the packages which were installed by Composer.
   *   Keys are package names and values are package versions. Example:
   *   ['psr/log' => '1.0.2']. Returns an empty array if no packages
   *   have been installed or the installed.json file could not be found.
   */
  public function getInstalledPackages();

  /**
   * Gets the development requirements for the Drupal installation.
   *
   * Note that since Drupal uses Wikimedia's composer-merge plugin, this method
   * will return a combination of the root package and the drupal/core package.
   *
   * This method only deals with core's dev requirements, and does not include
   * dev requirements for contrib. It's likely that core and contrib's dev
   * requirements are similar.
   *
   * @return string[]
   *   Associative array where the keys are the package names and the values are
   *   version constraints. This is similar to the composer.json file's
   *   require-dev array.
   *
   * @see https://getcomposer.org/doc/04-schema.md#require-dev
   */
  public function getDevRequirements();

}
