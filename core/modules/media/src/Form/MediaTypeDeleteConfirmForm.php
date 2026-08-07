<?php

namespace Drupal\media\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for media type deletion.
 *
 * @internal
 */
class MediaTypeDeleteConfirmForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $num_entities = $this->entityTypeManager->getStorage('media')->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', $this->entity->id())
      ->count()
      ->execute();
    if ($num_entities) {
      $form['#title'] = $this->getQuestion();
      $form['description'] = [
        '#type' => 'inline_template',
        '#template' => '<p>{{ message }}</p>',
        '#context' => [
          'message' => $this->formatPlural($num_entities,
            '%type is used by @count media item on your site. You can not remove this media type until you have removed all of the %type media items.',
            '%type is used by @count media items on your site. You can not remove this media type until you have removed all of the %type media items.',
            ['%type' => $this->entity->label()]),
        ],
      ];

      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

}
