<?php

namespace Drupal\Core\Installer\Exception;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for exceptions thrown by installer.
 */
class InstallerException extends \RuntimeException {
  use StringTranslationTrait;

  /**
   * The page title to output.
   *
   * @var string
   */
  protected $title;

  public function __construct($message, $title = 'Error', $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->title = $title;
  }

  /**
   * Returns the exception page title.
   *
   * @return string
   *   The page title.
   */
  public function getTitle() {
    return $this->title;
  }

}
