<?php

namespace Drupal\block_content;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the translation handler for content blocks.
 */
class BlockContentTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity): void {
    parent::entityFormAlter($form, $form_state, $entity);

    if (isset($form['content_translation'])) {
      // Block content entities do not expose the status field. Make the content
      // translation status field visible instead.
      // @todo This will not be necessary once this issue lands:
      //   https://www.drupal.org/project/drupal/issues/2834546
      $form['content_translation']['status']['#access'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function entityFormTitle(EntityInterface $entity) {
    $block_type = BlockContentType::load($entity->bundle());
    return $this->t('<em>Edit @type</em> @title', ['@type' => $block_type->label(), '@title' => $entity->label()]);
  }

}
