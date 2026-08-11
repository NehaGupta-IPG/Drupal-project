<?php

namespace Drupal\path\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides the path admin overview filter form.
 *
 * @internal
 */
class PathFilterForm extends FormBase implements WorkspaceSafeFormInterface, TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'path_admin_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $query = []) {
    $form['#attributes'] = ['class' => ['search-form']];
    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter aliases'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['details-content-inline']],
    ];
    $form['basic']['alias'] = [
      '#type' => 'search',
      '#title' => $this->t('Alias'),
      '#attributes' => [
        'placeholder' => $this->t('eg. /my-custom-path/my-custom-alias'),
      ],
      '#default_value' => $query['alias'] ?? NULL,
      '#maxlength' => 128,
      '#size' => 25,
    ];
    $form['basic']['path'] = [
      '#type' => 'search',
      '#title' => $this->t('System path'),
      '#attributes' => [
        'placeholder' => $this->t('eg. /node/1234'),
      ],
      '#default_value' => $query['path'] ?? NULL,
      '#maxlength' => 128,
      '#size' => 25,
    ];
    $form['basic']['langcode'] = [
      '#type' => 'language_select',
      '#languages' => LanguageInterface::STATE_ALL,
      '#empty_option' => $this->t('- Any -'),
      '#title' => $this->t('Language'),
      '#default_value' => $query['langcode'],
      '#pre_render' => [
        static::class . '::filterLanguageOptions',
      ],
    ];
    $form['basic']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    if (!empty(array_filter($query))) {
      $form['basic']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
      ];
    }
    return $form;
  }

  /**
   * Removes the 'Not applicable' option in the language selector element.
   *
   * @param array $element
   *   The language selector element.
   *
   * @return array
   *   The element with its options filtered.
   */
  public function filterLanguageOptions(array $element): array {
    unset($element['#options'][LanguageInterface::LANGCODE_NOT_APPLICABLE]);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.path_alias.collection', [], [
      'query' => [
        'alias' => trim($form_state->getValue('alias')),
        'path' => trim($form_state->getValue('path')),
        'langcode' => trim($form_state->getValue('langcode')),
      ],
    ]);
  }

  /**
   * Resets the filter selections.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.path_alias.collection');
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['filterLanguageOptions'];
  }

}
