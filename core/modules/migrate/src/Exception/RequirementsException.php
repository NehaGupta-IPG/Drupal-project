<?php

namespace Drupal\migrate\Exception;

/**
 * Defines an exception thrown when a migration does not meet the requirements.
 *
 * @see \Drupal\migrate\Plugin\RequirementsInterface
 */
class RequirementsException extends \RuntimeException {

  /**
   * The missing requirements.
   *
   * @var array
   */
  protected $requirements;

  public function __construct($message = "", array $requirements = [], $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);

    $this->requirements = $requirements;
  }

  /**
   * Get an array of requirements.
   *
   * @return array
   *   The requirements.
   */
  public function getRequirements() {
    return $this->requirements;
  }

  /**
   * Get the requirements as a string.
   *
   * @return string
   *   A formatted requirements string.
   */
  public function getRequirementsString() {
    $output = '';
    foreach ($this->requirements as $requirement_type => $requirements) {
      if (!is_array($requirements)) {
        $requirements = [$requirements];
      }

      foreach ($requirements as $value) {
        $output .= "$requirement_type: $value. ";
      }
    }
    return trim($output);
  }

}
