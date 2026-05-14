<?php

declare(strict_types=1);

namespace Drupal\Core;

/**
 * Provides the required context for Drupal applications to function.
 *
 * Drupal applications perform a bunch of operations around file loading that
 * are dependent on the filesystem. Classes implementing this interface can be
 * used to provide Drupal with that information.
 */
interface AppContextInterface {

  /**
   * Gets the app root.
   *
   * @return string
   *   The path of the application root.
   */
  public function getAppRoot() : string;

  /**
   * Gets the site path directory.
   *
   * Site path is relative to the app root directory.
   * Usually defined as "sites/default".
   *
   * By default, Drupal uses sites/default.
   *
   * @return string
   *   The current site path directory.
   */
  public function getSitePath() : string;

  /**
   * Returns the base URL of the application.
   *
   * @return string
   *   The base URL of the application.
   */
  public function getBaseUrl(): string;

  /**
   * Returns the path part of the base URL.
   *
   * @return string
   *   The path part of the base URL.
   */
  public function getBasePath(): string;

}
