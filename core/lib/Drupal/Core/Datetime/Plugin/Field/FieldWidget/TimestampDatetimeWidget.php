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
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'use_current_time' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['use_current_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use current time on empty'),
      '#description' => $this->t('Set the value to the current time upon submission when the form element is left empty.'),
      '#default_value' => $this->getSetting('use_current_time'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t(
      'Use current time on empty: @use_current_time',
      ['@use_current_time' => $this->getSetting('use_current_time') ? 'Yes' : 'No']
    );

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $default_value = isset($items[$delta]->value) ? DrupalDateTime::createFromTimestamp($items[$delta]->value) : '';
    $element['value'] = $element + [
      '#type' => 'datetime',
      '#default_value' => $default_value,
      '#date_year_range' => '1902:2037',
    ];

    $element['value']['#description'] = $element['#description'];

    if ($this->getSetting('use_current_time') && $element['#description'] === '') {
      $element['value']['#description'] .= $this->t('Leave blank to use the time of form submission.');
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      // Delta=0 will be shown even if value is empty.
      // else don't save the form value.
      if ($item['_original_delta'] > 0 && $item['value'] == NULL) {
        unset($values[$item['_original_delta']]);
      }
      // @todo The structure is different whether access is denied or not, to
      //   be fixed in https://www.drupal.org/node/2326533.
      if (isset($item['value']) && $item['value'] instanceof DrupalDateTime) {
        $date = $item['value'];
      }
      elseif (isset($item['value']['object']) && $item['value']['object'] instanceof DrupalDateTime) {
        $date = $item['value']['object'];
      }
      elseif ($this->getSetting('use_current_time')) {
        $date = new DrupalDateTime();
      }
      if (isset($date)) {
        $item['value'] = $date->getTimestamp();
      }
    }
    return $values;
  }

}
