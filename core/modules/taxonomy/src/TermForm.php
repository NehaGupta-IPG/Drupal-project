<?php

namespace Drupal\taxonomy;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base for handler for taxonomy term edit forms.
 *
 * @internal
 */
class TermForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $term = $this->entity;
    $vocab_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $vocabulary = $vocab_storage->load($term->bundle());

    $parent = $this->getParentIds($term);
    $form_state->set(['taxonomy', 'parent'], $parent);
    $form_state->set(['taxonomy', 'vocabulary'], $vocabulary);

    $form['vid'] = [
      '#type' => 'value',
      '#value' => $vocabulary->id(),
    ];

    $form['tid'] = [
      '#type' => 'value',
      '#value' => $term->id(),
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    if (!$this->getRequest()->query->has('destination')) {
      $element['overview'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save and go to list'),
        '#weight' => 20,
        '#submit' => array_merge($element['submit']['#submit'], ['::overview']),
        '#access' => $this->currentUser()->hasPermission('access taxonomy overview'),
      ];
    }

    return $element;
  }

  /**
   * Form submission handler for the 'overview' action.
   *
   * @param array[] $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function overview(array $form, FormStateInterface $form_state): void {
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')
      ->load($form_state->getValue('vid'));
    $form_state->setRedirectUrl($vocabulary->toUrl('overview-form'));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Ensure numeric values.
    if ($form_state->hasValue('weight') && !is_numeric($form_state->getValue('weight')[0]['value'])) {
      $form_state->setErrorByName('weight', $this->t('Weight value must be numeric.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $term = parent::buildEntity($form, $form_state);

    // Prevent leading and trailing spaces in term names.
    $term->setName(trim($term->getName()));

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $term = $this->entity;

    $result = $term->save();

    $edit_link = $term->toLink($this->t('Edit'), 'edit-form')->toString();
    $view_link = $term->toLink()->toString();
    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created new term %term.', ['%term' => $view_link]));
        $this->logger('taxonomy')->info('Created new term %term.', ['%term' => $term->getName(), 'link' => $edit_link]);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('Updated term %term.', ['%term' => $view_link]));
        $this->logger('taxonomy')->info('Updated term %term.', ['%term' => $term->getName(), 'link' => $edit_link]);

        // Redirect to term view page if user has access, otherwise the form
        // will be displayed again.
        $canonicalUrl = $term->toUrl();
        if ($canonicalUrl->access()) {
          $form_state->setRedirectUrl($canonicalUrl);
        }
        break;
    }

    $form_state->setValue('tid', $term->id());
    $form_state->set('tid', $term->id());
  }

  /**
   * Returns term parent IDs, including the root.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term entity.
   *
   * @return array
   *   A list if parent term IDs.
   */
  protected function getParentIds(TermInterface $term): array {
    $parent = [];
    // Get the parent directly from the term as
    // \Drupal\taxonomy\TermStorageInterface::loadParents() excludes the root.
    foreach ($term->get('parent') as $item) {
      $parent[] = (int) $item->target_id;
    }
    return $parent;
  }

}
