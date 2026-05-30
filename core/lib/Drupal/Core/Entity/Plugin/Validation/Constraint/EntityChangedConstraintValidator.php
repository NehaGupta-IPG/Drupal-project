<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the EntityChanged constraint.
 */
class EntityChangedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    if (isset($entity)) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      if (!$entity->isNew()) {
        $saved_entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
        // Ensure that the entity translation being saved is the same as or
        // newer than the version in storage to avoid reverting concurrent
        // changes. Only the active translation is checked because:
        // - Saving a translation only writes that translation's data to
        //   storage; other translations are not affected.
        // - Other translations in the entity object may carry stale changed
        //   timestamps when the form was loaded before another user saved
        //   those translations (e.g. after an AJAX form rebuild), which would
        //   produce false "concurrent modification" violations.
        if ($saved_entity) {
          $langcode = $entity->language()->getId();
          if ($saved_entity->hasTranslation($langcode) && $saved_entity->getTranslation($langcode)->getChangedTime() > $entity->getTranslation($langcode)->getChangedTime()) {
            $this->context->addViolation($constraint->message);
          }
        }
      }
    }
  }

}
