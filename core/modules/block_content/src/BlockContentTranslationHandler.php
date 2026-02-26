<?php

namespace Drupal\block_content;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the translation handler for content blocks.
 */
class BlockContentTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  protected function entityFormTitle(EntityInterface $entity) {
    $block_type = BlockContentType::load($entity->bundle());
    return $this->t('<em>Edit @type</em> @title', ['@type' => $block_type->label(), '@title' => $entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);
    $form['content_translation']['status']['#access'] = !isset($form['content_translation']);
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state) {
    if ($form_state->hasValue('content_translation')) {
      $translation = &$form_state->getValue('content_translation');
      $translation['status'] = $entity->isPublished();
    }
    parent::entityFormEntityBuild($entity_type, $entity, $form, $form_state);
  }

}
