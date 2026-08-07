<?php

namespace Drupal\language\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base form for language add and edit forms.
 */
abstract class LanguageFormBase extends EntityForm {

  public function __construct(
    #[Autowire(service: 'language_manager')]
    protected ConfigurableLanguageManagerInterface $languageManager,
  ) {}

  /**
   * Common elements of the language addition and editing form.
   */
  public function commonForm(array &$form) {
    /** @var \Drupal\language\ConfigurableLanguageInterface $language */
    $language = $this->entity;
    if ($language->getId()) {
      $form['langcode_view'] = [
        '#type' => 'item',
        '#title' => $this->t('Language code'),
        '#markup' => $language->id(),
      ];
    }
    else {
      $form['langcode'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Language code'),
        '#maxlength' => 12,
        '#required' => TRUE,
        '#default_value' => '',
        '#disabled' => FALSE,
        '#description' => $this->t('Use language codes as <a href=":w3ctags">defined by the W3C</a> for interoperability. <em>Examples: "en", "en-gb" and "zh-hant".</em>', [':w3ctags' => 'https://www.w3.org/International/articles/language-tags/']),
      ];
    }
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language name'),
      '#maxlength' => 64,
      '#default_value' => $language->label(),
      '#required' => TRUE,
    ];
    $form['direction'] = [
      '#type' => 'radios',
      '#title' => $this->t('Direction'),
      '#required' => TRUE,
      '#description' => $this->t('Direction that text in this language is presented.'),
      '#default_value' => $language->getDirection(),
      '#options' => [
        LanguageInterface::DIRECTION_LTR => $this->t('Left to right'),
        LanguageInterface::DIRECTION_RTL => $this->t('Right to left'),
      ],
    ];

    return $form;
  }

  /**
   * Validates the language editing element.
   */
  public function validateCommon(array $form, FormStateInterface $form_state) {
    // Ensure sane field values for langcode and name.
    if (!isset($form['langcode_view']) && !preg_match('@^' . LanguageInterface::VALID_LANGCODE_REGEX . '$@', $form_state->getValue('langcode'))) {
      $form_state->setErrorByName('langcode', $this->t('%field must be a valid language tag as <a href=":url">defined by the W3C</a>.', [
        '%field' => $form['langcode']['#title'],
        ':url' => 'https://www.w3.org/International/articles/language-tags/',
      ]));
    }
    if ($form_state->getValue('label') != Html::escape($form_state->getValue('label'))) {
      $form_state->setErrorByName('label', $this->t('%field cannot contain any markup.', ['%field' => $form['label']['#title']]));
    }
  }

}
