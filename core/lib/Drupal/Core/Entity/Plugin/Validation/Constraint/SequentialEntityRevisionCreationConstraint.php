<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for sequential entity revision creation.
 *
 * @Constraint(
 *   id = "SequentialEntityRevisionCreation",
 *   label = @Translation("Sequential entity revision creation", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class SequentialEntityRevisionCreationConstraint extends Constraint {

  public $message = 'A new revision of this content has been created by another user, or you have already submitted modifications. As a result, your changes cannot be saved.';

}
