<?php

namespace Drupal\Core\Archiver;

@trigger_error('\Drupal\Core\Archiver\ArchiveTar is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \PharData instead. See https://www.drupal.org/project/drupal/issues/3571185', E_USER_DEPRECATED);

/**
 * Extends Pear's Archive_Tar to use exceptions.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0.
 *   Use \PharData instead.
 *
 * @see https://www.drupal.org/project/drupal/issues/3571185
 */
class ArchiveTar extends \Archive_Tar {

  /**
   * {@inheritdoc}
   */
  public function _error($p_message) {
    throw new \Exception($p_message);
  }

  /**
   * {@inheritdoc}
   */
  public function _warning($p_message) {
    throw new \Exception($p_message);
  }

}
