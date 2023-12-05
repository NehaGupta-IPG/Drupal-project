<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if string is a valid stream wrapper URI.
 *
 * @Constraint(
 *   id = "StreamWrapperUri",
 *   label = @Translation("Stream wrapper URI constraint", context = "Validation"),
 * )
 */
class StreamWrapperUriConstraint extends Constraint {

  /**
   * {@inheritdoc}
   */
  public $message = '"%value" is not a valid stream wrapper URI.';

}
