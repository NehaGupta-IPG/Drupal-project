<?php

namespace Drupal\content_moderation\Entity\Handler;

use Drupal\Core\Form\FormStateInterface;

/**
 * Customizations for comment entities.
 *
 * @internal
 */
class CommentModerationHandler extends ModerationHandler {

  /**
   * {@inheritdoc}
   */
  public function enforceRevisionsEntityFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $form['revision']['#disabled'] = TRUE;
    $form['revision']['#default_value'] = TRUE;
    $form['revision']['#description'] = $this->t('Revisions are required.');
  }

  /**
   * {@inheritdoc}
   */
  public function enforceRevisionsBundleFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // Force the revision checkbox on.
    $form['workflow']['options']['revision']['#value'] = 'revision';
    $form['workflow']['options']['revision']['#disabled'] = TRUE;
  }

}
