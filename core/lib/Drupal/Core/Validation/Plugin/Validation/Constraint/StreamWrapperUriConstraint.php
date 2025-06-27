<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint;

#[Constraint(
  id: 'StreamWrapperUri',
  label: new TranslatableMarkup('Stream wrapper URI constraint', [], ['context' => 'Validation']),
)]
/**
 * Checks if string is a valid stream wrapper URI.
 */
class StreamWrapperUriConstraint extends Constraint {

  /**
   * {@inheritdoc}
   */
  public $message = '"%value" is not a valid stream wrapper URI.';

  /**
   * Message to use for invalid scheme.
   *
   * @var string
   */
  public $invalidSchemeMessage = '"%scheme" stream wrapper is not allowed to be used.';

  /**
   * A filter to restrict the types of stream wrappers to allow.
   *
   * @var int
   * @see \Drupal\Core\StreamWrapper\StreamWrapperInterface
   */
  public int $filter = StreamWrapperInterface::ALL;

}
