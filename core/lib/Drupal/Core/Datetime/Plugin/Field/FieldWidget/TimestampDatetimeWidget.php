<?php

namespace Drupal\Core\Datetime\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'datetime timestamp' widget.
 */
#[FieldWidget(
  id: 'datetime_timestamp',
  label: new TranslatableMarkup('Datetime Timestamp'),
  field_types: [
    'timestamp',
    'created',
  ],
)]
class TimestampDatetimeWidget extends WidgetBase {

  /**
   * @var string
   */
  public const TIMESTAMP_OPTION_ON_CREATE = 'on_create';

  /**
   * @var string
   */
  public const TIMESTAMP_OPTION_ON_SUBMISSION = 'on_submission';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'use_current_time' => static::TIMESTAMP_OPTION_ON_SUBMISSION,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $options = $this->getSettingOptions();
    $element['use_current_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Use current time'),
      '#options' => $options,
      '#description' => $this->t('Set the value to the current time on form submission or on create form element.'),
      '#default_value' => $this->getSetting('use_current_time'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $options = $this->getSettingOptions();

    $item = $this->getSetting('use_current_time') ?? static::TIMESTAMP_OPTION_ON_SUBMISSION;
    $summary[] = $this->t(
      'Use current time : @use_current_time',
      ['@use_current_time' => $options[$item] ?? '- None -']
    );

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $current_times_setting = $this->getSetting('use_current_time');
    $options = $this->getSettingOptions();
    if ($current_times_setting == static::TIMESTAMP_OPTION_ON_CREATE && empty($items[$delta]->getValue())) {
      $now = \Drupal::time()->getRequestTime();
      $default_value = DrupalDateTime::createFromTimestamp($now);
    }
    else {
      $default_value = isset($items[$delta]->value) ? DrupalDateTime::createFromTimestamp($items[$delta]->value) : '';
    }

    $element['value'] = $element + [
      '#type' => 'datetime',
      '#default_value' => $default_value,
      '#date_year_range' => '1902:2037',
    ];

    $element['value']['#description'] = $element['#description'];

    if ($current_times_setting && $element['#description'] === '') {
      $element['value']['#description'] .= $this->t(
        'Use current time : @use_current_time',
        ['@use_current_time' => $options[$current_times_setting] ?? $options[static::TIMESTAMP_OPTION_ON_SUBMISSION]]
      );
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      // @todo The structure is different whether access is denied or not, to
      //   be fixed in https://www.drupal.org/node/2326533.
      if (isset($item['value']) && $item['value'] instanceof DrupalDateTime) {
        $date = $item['value'];
      }
      elseif (isset($item['value']['object']) && $item['value']['object'] instanceof DrupalDateTime) {
        $date = $item['value']['object'];
      }
      else {
        $date = new DrupalDateTime();
      }

      $item['value'] = $date->getTimestamp();
    }
    return $values;
  }

  /**
   * List of options for the element settings.
   *
   * @return array
   *   The list of options.
   */
  public function getSettingOptions(): array {
    // We don't have option as none because it creates a new date on form submission
    // @see TimestampDatetimeWidget::massageFormValues
    $options = [
      static::TIMESTAMP_OPTION_ON_SUBMISSION => $this->t('On form submission'),
      static::TIMESTAMP_OPTION_ON_CREATE => $this->t('On form create'),
    ];
    return $options;
  }

}
