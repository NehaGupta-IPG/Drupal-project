<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a search element for filtering a list with JavaScript.
 *
 * - #size: (optional) The size of the input field. Defaults to 60.
 * - #maxlength: (optional) The maximum length of the input field. Defaults to
 *   128.
 * - #theme_wrappers: ['form_element'].
 * - #list_container_selector: The CSS selector(s) of the container to search
 *   in. To use multiple selectors, separate them with commas, like
 *   '.selector-a, .selector-b'.
 * - #list_item: (optional) The CSS selector, relative to the container, for the
 *   individual items that will be hidden or shown during filtering.
 *   This selector identifies the DOM elements to toggle visibility on.
 *   Defaults to 'tbody tr' (table rows within a tbody). Examples: '.item' for
 *   list items, '.js-layout-builder-block-link' for block links, or
 *   '.package-listing table tbody tr' for nested table rows.
 * - #list_text: (optional) The CSS selector, relative to a list item, of
 *   elements containing text to search for. This may produce multiple elements
 *   for each list item. Defaults to an empty string, which indicates that the
 *   whole of the list item should be considered searchable text.
 * - #minimum_filter_length: (optional) The minimum length of the typed string
 *   before a filter is triggered. By default, is triggered after 2 chars.
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

    $list_filter_attrs = [];
    $list_filter_attrs['containerSelector'] = $element['#list_container_selector'];
    $list_filter_attrs['itemsSelector'] = $element['#list_item'];
    if ($element['#list_text']) {
      $list_filter_attrs['targetsSelector'] = $element['#list_text'];
    }
    $list_filter_attrs['minLength'] = $element['#minimum_filter_length'];
    $list_filter_attrs['searchStart'] = (bool) $element['#search_start_of_words'];
    $list_filter_attrs['announceSingular'] = $element['#announce']['singular'];
    $list_filter_attrs['announcePlural'] = $element['#announce']['plural'];
    $list_filter_attrs['announceFull'] = $element['#announce']['all'];

    $element['#attributes']['data-list-filter'] = json_encode($list_filter_attrs);
    $element['#attached']['library'][] = 'core/drupal.list-filter';
    return $element;
  }

}
