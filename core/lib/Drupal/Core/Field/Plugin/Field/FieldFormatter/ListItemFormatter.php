<?php

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'list_item' formatter.
 *
 * @FieldFormatter(
 *   id = "list_item",
 *   label = @Translation("List Item"),
 *   field_types = {
 *     "entity_reference",
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *   },
 *   multiple_values = TRUE
 * )
 */
class ListItemFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'list_type' => 'ol',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function getListTypeOptions() {
    return [
      'ol' => static::t('Ordered list'),
      'ul' => static::t('Unordered list'),
      'comma' => static::t('Comma separated'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $elements = parent::settingsForm($form, $form_state);
    $elements['list_type'] = [
      '#title' => $this->t('List type'),
      '#type' => 'select',
      '#options' => static::getListTypeOptions(),
      '#default_value' => $this->getSetting('list_type'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $list_type_options = static::getListTypeOptions();
    $summary[] = $this->t('List type: @list_type', ['@list_type' => $list_type_options[$this->getSetting('list_type')]]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $item_list = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];

    $list_type = $this->getSetting('list_type');

    if (in_array($list_type, ['ul', 'ol'])) {
      $item_list['#list_type'] = $list_type;
    }
    elseif ($list_type == 'comma') {
      $item_list['#context'] = ['list_style' => 'comma-list'];
    }
    return $item_list;
  }

}
