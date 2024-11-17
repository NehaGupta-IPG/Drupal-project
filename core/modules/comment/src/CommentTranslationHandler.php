<?php

namespace Drupal\comment;

use Drupal\Core\Entity\EntityInterface;
use Drupal\content_translation\ContentTranslationHandler;

/**
 * Defines the translation handler for comments.
 */
class CommentTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  protected function entityFormTitle(EntityInterface $entity) {
    return $this->t('Edit comment @subject', ['@subject' => $entity->label()]);
  }

}
