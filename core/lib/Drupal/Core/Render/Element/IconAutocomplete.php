<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;

/**
 * Provides a form element to select an icon with autocomplete.
 *
 * This element provides a simple autocomplete textfield for selecting icons
 * from available icon packs. The value is stored as 'pack_id:icon_id'.
 *
 * Properties:
 * - #default_value: (string) Icon value as pack_id:icon_id.
 * - #allowed_icon_pack: (array) Icon pack IDs to limit the selection.
 *   If empty, all icon packs are available.
 * - #max_result: (int) Maximum number of autocomplete results. Default 50.
 *
 * Standard form element properties:
 * - #description: (string) Help or description text for the input element.
 * - #placeholder: (string) Placeholder text for the input.
 * - #required: (bool) Whether input is required on the element.
 * - #size: (int) Textfield size. Default 60.
 *
 * Usage example:
 * @code
 * $form['icon'] = [
 *   '#type' => 'icon_autocomplete',
 *   '#title' => $this->t('Select icon'),
 *   '#default_value' => 'my_pack:home',
 *   '#allowed_icon_pack' => ['my_pack', 'another_pack'],
 *   '#required' => TRUE,
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\FormElementBase
 * @see \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
 *
 * @internal
 *   This API is experimental.
 */
#[FormElement('icon_autocomplete')]
class IconAutocomplete extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => 255,
      '#autocomplete_route_name' => 'system.icon_autocomplete',
      '#element_validate' => [
        [$class, 'validateIconAutocomplete'],
      ],
      '#process' => [
        [$class, 'processIconAutocomplete'],
        [$class, 'processAutocomplete'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderIconAutocomplete'],
      ],
      '#theme' => 'input__textfield',
      '#theme_wrappers' => ['form_element'],
      '#allowed_icon_pack' => [],
      '#max_result' => 50,
      '#attached' => [
        'library' => [
          'system/icon_autocomplete',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // Normalize the input value.
      return is_string($input) ? trim($input) : '';
    }
    return NULL;
  }

  /**
   * Form element processing handler for the 'icon_autocomplete' element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processIconAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Set default placeholder if not specified.
    if (!isset($element['#placeholder'])) {
      $element['#placeholder'] = new TranslatableMarkup('Start typing icon name');
    }

    // Set default description if not specified.
    if (!isset($element['#description'])) {
      $element['#description'] = new TranslatableMarkup('Icon format: pack_id:icon_id');
    }

    // Add query parameters for autocomplete route.
    if (!empty($element['#allowed_icon_pack'])) {
      $element['#autocomplete_query_parameters']['allowed_icon_pack'] = implode('+', $element['#allowed_icon_pack']);
    }

    if (!empty($element['#max_result'])) {
      $element['#autocomplete_query_parameters']['max_result'] = $element['#max_result'];
    }

    // Add wrapper and preview for the input field.
    $element['#field_prefix'] = Markup::create('<div class="icon-wrapper"><span class="icon-preview"></span>');
    $element['#field_suffix'] = Markup::create('</div>');

    return $element;
  }

  /**
   * Prepares a #type 'icon_autocomplete' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderIconAutocomplete(array $element): array {
    $element['#attributes']['type'] = 'text';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-icon-autocomplete', 'form-text']);

    return $element;
  }

  /**
   * Form element validation handler for icon_autocomplete elements.
   *
   * Validates that the icon ID is in the correct format and exists.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateIconAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $value = $element['#value'];

    // Allow empty value if not required.
    if ($value === '' || $value === NULL) {
      if ($element['#required']) {
        $form_state->setError($element, new TranslatableMarkup('@name field is required.', [
          '@name' => $element['#title'],
        ]));
      }
      return;
    }

    // Validate icon ID format (pack_id:icon_id).
    if (!str_contains($value, IconDefinition::ICON_SEPARATOR)) {
      $form_state->setError($element, new TranslatableMarkup('The icon ID must be in the format pack_id:icon_id.'));
      return;
    }

    // Verify the icon exists.
    $icon_pack_manager = \Drupal::service('plugin.manager.icon_pack');
    $icon = $icon_pack_manager->getIcon($value);

    if (!$icon instanceof IconDefinitionInterface) {
      $form_state->setError($element, new TranslatableMarkup('The icon %icon does not exist. Please select a valid icon from the autocomplete suggestions.', [
        '%icon' => $value,
      ]));
      return;
    }

    // Validate against allowed icon packs if specified.
    if (!empty($element['#allowed_icon_pack'])) {
      $pack_id = $icon->getPackId();
      if (!in_array($pack_id, $element['#allowed_icon_pack'], TRUE)) {
        $form_state->setError($element, new TranslatableMarkup('The icon %icon is from pack %pack_id, which is not in the allowed list: @allowed.', [
          '%icon' => $value,
          '%pack_id' => $pack_id,
          '@allowed' => implode(', ', $element['#allowed_icon_pack']),
        ]));
      }
    }
  }

}
