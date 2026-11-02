<?php

namespace Drupal\datetime_range\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\datetime\DateTimeComputed;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Plugin implementation of the 'daterange' field type.
 */
#[FieldType(
  id: "daterange",
  label: new TranslatableMarkup("Datetime range"),
  description: [
    new TranslatableMarkup("A start and end, each using the Datetime format"),
    new TranslatableMarkup("Choose either date and time, date only, or all day"),
    new TranslatableMarkup("Example: 2001-01-15T07:28:08 to 2001-03-15T11:24:15"),
  ],
  category: "date_time",
  default_widget: "daterange_default",
  default_formatter: "daterange_default",
  list_class: DateRangeFieldItemList::class,
)]
class DateRangeItem extends DateTimeItem {

  /**
   * Value for the 'datetime_type' setting: store a date and time.
   */
  const DATETIME_TYPE_ALLDAY = 'allday';

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Start date value'))
      ->setRequired(TRUE);

    $properties['start_date'] = DataDefinition::create('any')
      ->setLabel(t('Computed start date'))
      ->setDescription(t('The computed start DateTime object.'))
      ->setComputed(TRUE)
      ->setClass(DateTimeComputed::class)
      ->setSetting('date source', 'value');

    $properties['end_value'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('End date value'))
      ->setRequired(TRUE);

    $properties['end_date'] = DataDefinition::create('any')
      ->setLabel(t('Computed end date'))
      ->setDescription(t('The computed end DateTime object.'))
      ->setComputed(TRUE)
      ->setClass(DateTimeComputed::class)
      ->setSetting('date source', 'end_value');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['value']['description'] = 'The start date value.';

    $schema['columns']['end_value'] = [
      'description' => 'The end date value.',
    ] + $schema['columns']['value'];

    $schema['indexes']['end_value'] = ['end_value'];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);
    $element['datetime_type']['#type'] = 'radios';
    $element['datetime_type']['#options'][static::DATETIME_TYPE_ALLDAY] = $this->t('All Day');

    $element['datetime_type'][static::DATETIME_TYPE_DATETIME]['#description'] = $this->t('Stored as UTC date and time values (Example: 2026-05-09T19:44:00 to 2026-05-09T20:44:00).');
    $element['datetime_type'][static::DATETIME_TYPE_DATE]['#description'] = $this->t('Stored as dates only, with no time values (Example: 2026-05-09 to 2026-05-10).');
    $element['datetime_type'][static::DATETIME_TYPE_ALLDAY]['#description'] = $this->t("Stored as a full-day range in UTC, from midnight to 11:59:59 PM in the current user's timezone (Example: 2026-05-09T04:00:00 to 2026-05-11T03:59:59).");

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $type = $field_definition->getSetting('datetime_type');

    // Just pick a date in the past year. No guidance is provided by this Field
    // type.
    $start = \Drupal::time()->getRequestTime() - mt_rand(0, 86400 * 365) - 86400;
    $end = $start + 86400;
    if ($type == static::DATETIME_TYPE_DATETIME) {
      $values['value'] = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start);
      $values['end_value'] = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end);
    }
    else {
      $values['value'] = gmdate(DateTimeItemInterface::DATE_STORAGE_FORMAT, $start);
      $values['end_value'] = gmdate(DateTimeItemInterface::DATE_STORAGE_FORMAT, $end);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $start_value = $this->get('value')->getValue();
    $end_value = $this->get('end_value')->getValue();
    return ($start_value === NULL || $start_value === '') && ($end_value === NULL || $end_value === '');
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the computed date is recalculated.
    if ($property_name == 'value') {
      $this->set('start_date', NULL);
    }
    elseif ($property_name == 'end_value') {
      $this->set('end_date', NULL);
    }
    parent::onChange($property_name, $notify);
  }

}
