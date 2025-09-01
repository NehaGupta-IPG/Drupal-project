<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a search element for filtering a list with JavaScript.
 *
 * - #input: TRUE
 * - #size: (optional) The size of the input field. Defaults to 60.
 * - #maxlength: (optional) The maximum length of the input field. Defaults to
 *   128.
 * - theme_wrappers: ['form_element'].
 * - #list_container_selector: The CSS selector(s) of the container to search
 *   in. To use multiple selectors, separate them with commas, like
 *   '.selector-a, .selector-b'.
 * - #list_item: (optional) The CSS selector, relative to the container, for the
 *   items to search for. If omitted, defaults to 'tbody tr'.
 * - #list_text: (optional) The CSS selector, relative to a list item, of
 *   elements containing text to search for. This may produce multiple elements.
 *   Defaults to an empty string, which indicates that the whole of the list
 *   item should be considered searchable text.
 * - #minimum_filter_length: (optional) The minimum length of the typed string
 *   before a filter is triggered. Defaults to 2.
 * - #search_start_of_words: (optional) Whether the entered filter text will
 *   search anywhere within words, or only from the start of words. For example,
 *   if TRUE, typing 'ke' will not filter the word 'cake'. Defaults to FALSE.
 * - #announce: (optional) An array of strings to use for accessibility ARIA
 *   announcements when the number of visible items is changed. The keys are:
 *   - singular: Message to announce when only one item is visible.
 *   - plural: Message to announce when more than one items are visible. This
 *     must contain the '@count' placeholder.
 *   - all: Message to announce when all items are visible.
 */
#[RenderElement('list_filter')]
class ListFilter extends Search {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
      '#pre_render' => [
        [static::class, 'preRenderListFilterSearch'],
      ],
      '#theme' => 'input__search',
      '#theme_wrappers' => ['form_element'],
      '#list_container_selector' => '.filter-container',
      '#list_item' => 'tbody tr',
      '#list_text' => '',
      '#minimum_filter_length' => 2,
      '#search_start_of_words' => FALSE,
      '#announce' => [
        'singular' => $this->t('1 item is available in the modified list.'),
        'plural' => $this->t('@count items are available in the modified list.'),
        'all' => $this->t('All available items are listed.'),
      ],
      '#library' => 'core/drupal.list-filter',
    ];
  }

  /**
   * Prepares a #type 'list_filter' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderListFilterSearch(array $element): array {
    $element['#attributes']['type'] = 'search';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-search']);
    $element['#attributes']['class'][] = 'table-filter-text';

    $listFilterAttrs = [];
    $listFilterAttrs['containerSelector'] = $element['#list_container_selector'];
    $listFilterAttrs['itemsSelector'] = $element['#list_item'];
    if ($element['#list_text']) {
      $listFilterAttrs['targetsSelector'] = $element['#list_text'];
    }
    $listFilterAttrs['minLength'] = $element['#minimum_filter_length'];
    $listFilterAttrs['searchStart'] = (bool) $element['#search_start_of_words'];
    $listFilterAttrs['announceSingular'] = $element['#announce']['singular'] ?? t('1 item is available in the modified list.');
    $listFilterAttrs['announcePlural'] = $element['#announce']['plural'] ?? t('@count items are available in the modified list.');
    $listFilterAttrs['announceFull'] = $element['#announce']['all'] ?? t('All available items are listed.');

    $element['#attributes']['data-list-filter'] = json_encode($listFilterAttrs);
    $element['#attached']['library'][] = 'core/drupal.list-filter';
    return $element;
  }

}
