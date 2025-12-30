<?php

namespace Drupal\Core\FileTransfer;

/**
 * Exception thrown for file transfer errors.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
 *   replacement. Use composer to manage the code for your site.
 *
 * @see https://www.drupal.org/node/3512364
 */
class FileTransferException extends \RuntimeException {

  /**
   * Arguments to be used in this exception.
   *
   * @var array
   */
  public $arguments;

  public function __construct($message, $code = 0, $arguments = []) {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

    parent::__construct($message, $code);
    $this->arguments = $arguments;
  }

}
