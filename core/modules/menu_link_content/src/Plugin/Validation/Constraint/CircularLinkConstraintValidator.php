<?php

namespace Drupal\menu_link_content\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for preventing circular links.
 */
class CircularLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    $url_object = $entity->getUrlObject();
    $entity_url = $entity->toUrl()->getInternalPath();

    if ($url_object->isRouted() && $url_object->getInternalPath() === $entity_url) {
      $this->context->buildViolation($constraint->message)
        ->atPath('url')
        ->addViolation();
    }
  }

}
