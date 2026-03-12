<?php

namespace Drupal\Core\Archiver;

/**
 * Extends Pear's Archive_Tar to use exceptions.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0.
 *   Use \PharData instead.
 * @see https://www.drupal.org/project/drupal/issues/3571185
 */
class ArchiveTar extends \Archive_Tar {

  /**
   * Constructs an ArchiveTar object.
   *
   * @param string $p_path
   *   The path to the archive.
   * @param string|null $p_compress
   *   The compression type ('gz', 'bz2', or NULL).
   * @param int $p_buffer_length
   *   Length of the read buffer in bytes.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0.
   *   Use \PharData instead.
   * @see https://www.drupal.org/project/drupal/issues/3571185
   */
  public function __construct($p_path, $p_compress = NULL, $p_buffer_length = 512) {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \PharData instead. See https://www.drupal.org/project/drupal/issues/3571185', E_USER_DEPRECATED);
    parent::__construct($p_path, $p_compress, $p_buffer_length);
  }

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
