<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Range;

/**
 * PHP Integer constraint.
 *
 * Defines a PHP Integer range constraint.
 */
#[Constraint(
  id: 'PhpInteger',
  label: new TranslatableMarkup('PHP Integer', [], ['context' => 'Validation']),
  type: ['integer']
)]
class PhpIntegerConstraint extends Range {

  /**
   * {@inheritdoc}
   */
  public function __construct(...$args) {
    $this->min = constant('PHP_INT_MIN');
    $this->max = constant('PHP_INT_MAX');
    parent::__construct(...$args);
  }

}
