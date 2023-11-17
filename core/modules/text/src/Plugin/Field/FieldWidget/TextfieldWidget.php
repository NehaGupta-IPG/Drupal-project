<?php

namespace Drupal\text\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'text_textfield' widget.
 */
#[FieldWidget(
  id: 'text_textfield',
  label: new TranslatableMarkup('Text field'),
  field_types: ['text'],
)]
class TextfieldWidget extends StringTextfieldWidget {

  use HideTextFormatHelpTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return static::hideOptionsDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return array_merge(parent::settingsForm($form, $form_state), $this->hideOptionsSettingsForm($form, $form_state));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return array_merge(parent::settingsSummary(), $this->hideOptionsSettingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $main_widget = parent::formElement($items, $delta, $element, $form, $form_state);
    $allowed_formats = $this->getFieldSetting('allowed_formats');

    $element = $main_widget['value'];
    $element['#type'] = 'text_format';
    $element['#format'] = $items[$delta]->format ?? NULL;
    $element['#base_type'] = $main_widget['value']['#type'];

    if ($allowed_formats && !$this->isDefaultValueWidget($form_state)) {
      $element['#allowed_formats'] = $allowed_formats;
    }

    $element += $this->hideOptionsFormElement();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    if (isset($element['format']['#access']) && !$element['format']['#access'] && preg_match('/^[0-9]*\.format$/', $violation->getPropertyPath())) {
      // Ignore validation errors for formats that may not be changed,
      // such as when existing formats become invalid.
      // See \Drupal\filter\Element\TextFormat::processFormat().
      return FALSE;
    }
    return $element;
  }

}
