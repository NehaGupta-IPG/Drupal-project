<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\Entity\RevisionableInterface;
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
        // Ensure that all the entity translations are the same as or newer
        // than their current version in the storage in order to avoid
        // reverting other changes. In fact the entity object that is being
        // saved might contain an older entity translation when different
        // translations are being concurrently edited.
        if ($saved_entity) {
          $common_translation_languages = array_intersect_key($entity->getTranslationLanguages(), $saved_entity->getTranslationLanguages());
          foreach (array_keys($common_translation_languages) as $langcode) {
            // Merely comparing the latest changed timestamps across all
            // translations is not sufficient since other translations may have
            // been edited and saved in the meanwhile. Therefore, compare the
            // changed timestamps of each entity translation individually.
            if ($saved_entity->getTranslation($langcode)->getChangedTime() > $entity->getTranslation($langcode)->getChangedTime()) {
              // Further compare the revision IDs to solve cases where users
              // were incorrectly blocked from editing content with content
              // moderation enabled. Skip this check for new revisions since
              // the loaded revision ID may have been preserved from form
              // preparation and doesn't represent actual concurrent editing.
              // @see \Drupal\content_moderation\EntityTypeInfo::entityPrepareForm()
              $is_new_revision = FALSE;
              if ($entity instanceof RevisionableInterface) {
                // Check if explicitly marked as new revision.
                $is_new_revision = $entity->isNewRevision();

                // If not explicitly marked, check if content moderation will
                // create a new revision by comparing moderation states.
                if (!$is_new_revision && $entity->hasField('moderation_state') && $saved_entity->hasField('moderation_state')) {
                  $moderation_state = $entity->getTranslation($langcode)->get('moderation_state')->value;
                  $saved_moderation_state = $saved_entity->getTranslation($langcode)->get('moderation_state')->value;
                  // State changed, so content moderation will create a new
                  // revision.
                  if ($moderation_state && $saved_moderation_state && $moderation_state !== $saved_moderation_state) {
                    $is_new_revision = TRUE;
                  }
                }
              }

              // Only add violation if NOT a new revision and IDs match.
              if (!$is_new_revision && $saved_entity->getLoadedRevisionId() === $entity->getLoadedRevisionId()) {
                $this->context->addViolation($constraint->message);
                break;
              }
            }
          }
        }
      }
    }
  }

}
