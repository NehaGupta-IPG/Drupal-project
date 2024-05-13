<?php

namespace Drupal\datetime_range;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides friendly methods for datetime range.
 */
trait DateTimeRangeTrait {

  /**
   * {@inheritdoc}
   */
  protected function buildDate(DrupalDateTime $date, $all_day = FALSE) {
    $this->setTimeZone($date);

    $build = [
      '#markup' => $this->formatDate($date, $all_day),
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDateWithIsoAttribute(DrupalDateTime $date, $all_day = FALSE) {
    // Create the ISO date in Universal Time.
    $iso_date = $date->format("Y-m-d\TH:i:s") . 'Z';

    $this->setTimeZone($date);

    $build = [
      '#theme' => 'time',
      '#text' => $this->formatDate($date, $all_day),
      '#html' => FALSE,
      '#attributes' => [
        'datetime' => $iso_date,
      ],
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date, $all_day = FALSE) {
    if ($all_day) {
      $format_type = $this->getSetting('all_day_format_type');
      $timezone = (new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))->getName();
    }
    else {
      $format_type = $this->getSetting('format_type');
      $timezone = $this->getSetting('timezone_override') ?: $date->getTimezone()->getName();
    }

    return $this->dateFormatter->format($date->getTimestamp(), $format_type, '', $timezone != '' ? $timezone : NULL);
  }


  /**
   * Get the default settings for a date and time range display.
   *
   * @return array
   *   An array containing default settings.
   */
  protected static function dateTimeRangeDefaultSettings(): array {
    return [
      'from_to' => DateTimeRangeDisplayOptions::Both->value,
      'all_day_format_type' => 'short',
      'separator' => '-',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $separator = $this->getSetting('separator');

    foreach ($items as $delta => $item) {
      if (!empty($item->start_date) && !empty($item->end_date)) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
        $start_date = $item->start_date;
        /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
        $end_date = $item->end_date;
        /** @var \Drupal\Core\Datetime\DrupalDateTime $all_day */
        $all_day = $item->all_day;

        if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
          $elements[$delta] = $this->renderStartEndWithIsoAttribute($start_date, $separator, $end_date, $all_day);
        }
        else {
          $elements[$delta] = $this->buildDateWithIsoAttribute($start_date, $all_day);

          if (!empty($item->_attributes)) {
            $elements[$delta]['#attributes'] += $item->_attributes;
            // Unset field item attributes since they have been included in the
            // formatter output and should not be rendered in the field
            // template.
            unset($item->_attributes);
          }
        }
      }
    }

    return $elements;
  }

  /**
   * Configuration form for date time range.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   Modified form array.
   */
  protected function dateTimeRangeSettingsForm(array $form): array {
    $form['from_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#options' => $this->getFromToOptions(),
      '#default_value' => $this->getSetting('from_to'),
    ];

    $allow_all_day = $this->fieldDefinition->getSetting('allow_all_day');
    $form['all_day_format_type'] = [
      '#title' => $this->t('All day date format'),
      '#description' => $this->t("Choose a format for displaying the all day date. Be sure to set a format omitting time."),
      '#default_value' => $this->getSetting('all_day_format_type'),
      '#access' => (bool) $allow_all_day,
    ] + $form['format_type'];

    $field_name = $this->fieldDefinition->getName();
    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date separator'),
      '#description' => $this->t('The string to separate the start and end dates'),
      '#default_value' => $this->getSetting('separator'),
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][from_to]"]' => ['value' => DateTimeRangeDisplayOptions::Both->value],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Gets the date time range settings summary.
   *
   * @return array
   *   An array of summary messages.
   */
  protected function dateTimeRangeSettingsSummary(): array {
    $summary = [];
    if ($from_to = $this->getSetting('from_to')) {
      $from_to_options = $this->getFromToOptions();
      if (isset($from_to_options[$from_to])) {
        $summary[] = $from_to_options[$from_to];
      }
    }

    if ($this->fieldDefinition->getSetting('allow_all_day')) {
      $date = new DrupalDateTime();
      $summary[] = $this->t('All day format: @display', ['@display' => $this->formatDate($date, TRUE)]);
    }

    if (($separator = $this->getSetting('separator')) && $this->getSetting('from_to') === DateTimeRangeDisplayOptions::Both->value) {
      $summary[] = $this->t('Separator: %separator', ['%separator' => $separator]);
    }

    return $summary;
  }

  /**
   * Returns a list of possible values for the 'from_to' setting.
   *
   * @return array
   *   A list of 'from_to' options.
   */
  protected function getFromToOptions(): array {
    return [
      DateTimeRangeDisplayOptions::Both->value => $this->t('Display both start and end dates'),
      DateTimeRangeDisplayOptions::StartDate->value => $this->t('Display start date only'),
      DateTimeRangeDisplayOptions::EndDate->value => $this->t('Display end date only'),
    ];
  }

  /**
   * Gets whether the start date should be displayed.
   *
   * @return bool
   *   True if the start date should be displayed. False otherwise.
   */
  protected function startDateIsDisplayed(): bool {
    switch ($this->getSetting('from_to')) {
      case DateTimeRangeDisplayOptions::Both->value:
      case DateTimeRangeDisplayOptions::StartDate->value:
        return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets whether the end date should be displayed.
   *
   * @return bool
   *   True if the end date should be displayed. False otherwise.
   */
  protected function endDateIsDisplayed(): bool {
    switch ($this->getSetting('from_to')) {
      case DateTimeRangeDisplayOptions::Both->value:
      case DateTimeRangeDisplayOptions::EndDate->value:
        return TRUE;
    }

    return FALSE;
  }

  /**
   * Creates a render array given start/end dates.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date to be rendered.
   * @param string $separator
   *   The separator string.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The end date to be rendered.
   *
   * @return array
   *   A renderable array for a single date time range.
   */
  protected function renderStartEnd(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date): array {
    $element = [];
    if ($this->startDateIsDisplayed()) {
      $element[DateTimeRangeDisplayOptions::StartDate->value] = $this->buildDate($start_date);
    }
    if ($this->startDateIsDisplayed() && $this->endDateIsDisplayed()) {
      $element['separator'] = ['#plain_text' => ' ' . $separator . ' '];
    }
    if ($this->endDateIsDisplayed()) {
      $element[DateTimeRangeDisplayOptions::EndDate->value] = $this->buildDate($end_date);
    }
    return $element;
  }

  /**
   * Creates a render array with ISO attributes given start/end dates.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date to be rendered.
   * @param string $separator
   *   The separator string.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The end date to be rendered.
   *
   * @return array
   *   A renderable array for a single date time range.
   */
  protected function renderStartEndWithIsoAttribute(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date): array {
    $element = [];
    if ($this->startDateIsDisplayed()) {
      $element[DateTimeRangeDisplayOptions::StartDate->value] = $this->buildDateWithIsoAttribute($start_date, $all_day);
    }
    if ($this->startDateIsDisplayed() && $this->endDateIsDisplayed()) {
      $element['separator'] = ['#plain_text' => ' ' . $separator . ' '];
    }
    if ($this->endDateIsDisplayed()) {
      $element[DateTimeRangeDisplayOptions::EndDate->value] = $this->buildDateWithIsoAttribute($end_date, $all_day);
    }
    return $element;
  }

}
