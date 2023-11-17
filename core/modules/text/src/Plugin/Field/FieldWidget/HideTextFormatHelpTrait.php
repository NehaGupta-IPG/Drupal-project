<?php

declare(strict_types=1);

namespace Drupal\text\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;

/**
 * Wrapper to allow to hide text format help in text based widgets.
 */
trait HideTextFormatHelpTrait {

  /**
   * Defines the hide text format help options.
   *
   * @return array
   *   The hide text format help specific widget settings.
   */
  protected static function hideOptionsDefaultSettings(): array {
    return [
      'hide_help' => FALSE,
      'hide_guidelines' => FALSE,
    ];
  }

  /**
   * Returns the hide text format help form elements.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form definition for the hide text format help settings.
   */
  protected function hideOptionsSettingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];
    $element['hide_help'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the help link <em>About text formats</em>.'),
      '#default_value' => $this->getSetting('hide_help'),
    ];
    $element['hide_guidelines'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide text format guidelines.'),
      '#default_value' => $this->getSetting('hide_guidelines'),
    ];

    return $element;
  }

  /**
   * Returns a short summary for the text format help widget settings.
   *
   * @return array
   *   A short summary of the text format help settings.
   */
  protected function hideOptionsSettingsSummary(): array {
    $summary = [];

    if ($this->getSetting('hide_help')) {
      $summary[] = $this->t('Hide the help link <em>About text formats</em>.');
    }

    if ($this->getSetting('hide_guidelines')) {
      $summary[] = $this->t('Hide text format guidelines.');
    }

    return $summary;
  }

  /**
   * Returns the text format help form specific element properties.
   *
   * @return array
   *   The text format help specific form element options.
   */
  protected function hideOptionsFormElement(): array {
    $element = [];

    $element['#hide_help'] = $this->getSetting('hide_help');
    $element['#hide_guidelines'] = $this->getSetting('hide_guidelines');

    return $element;
  }

}
