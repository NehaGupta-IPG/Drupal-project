<?php

namespace Drupal\Core\Archiver;

/**
 * @deprecated in drupal:11.x and is removed from drupal:12.0.0.
 *   Use \PharData instead.
 * @see https://www.drupal.org/project/drupal/issues/3571185
 */
class ArchiveTar {

  /**
   * @deprecated in drupal:11.x and is removed from drupal:12.0.0.
   *   Use \PharData instead.
   */
  public function __construct() {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.x and is removed from drupal:12.0.0. Use \PharData instead. See https://www.drupal.org/project/drupal/issues/3571185', E_USER_DEPRECATED);
    throw new \RuntimeException('ArchiveTar is removed. Use \PharData instead.');
  }

}
