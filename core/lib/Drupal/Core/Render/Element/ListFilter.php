<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Element;

/**
 * Provides a search element for filtering a list with JavaScript.
 *
 * - #list_container_selector: The CSS selector(s) of the container to search in.
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
 * - #library: (optional) Override library to attach.
 * - #announce: (optional) An array of strings to use for accessibility ARIA
 *   announcements when the number of visible items is changed. The keys are:
 *   - singular: Message to announce when only one item is visible.
 *   - plural: Message to announce when more than one items are visible. This
 *     must contain the '@count' placeholder.
 *   - all': Message to announce when all items are visible.
 * - #debug: (optional) Set to TRUE to add CSS styling to highlight the
 *   different elements.
 *
 * @RenderElement("list_filter")
 */
class ListFilter extends Search {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
      '#pre_render' => [
        [$class, 'preRenderListFilterSearch'],
      ],
      '#theme' => 'input__search',
      '#theme_wrappers' => ['form_element'],
      '#list_container_selector' => '.filter-container',
      '#list_item' => 'tbody tr',
      '#minimum_filter_length' => 2,
      '#list_text' => '',
      '#search_start_of_words' => FALSE,
      '#announce' => [
        'singular' => t('1 item is available in the modified list.'),
        'plural' => t('@count items are available in the modified list.'),
        'all' => t('All available items are listed.'),
      ],
      '#debug' => FALSE,
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
  public static function preRenderListFilterSearch($element) {
    $element['#attributes']['type'] = 'search';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-search']);
    $element['#attributes']['class'][] = 'table-filter-text';
    $element['#attributes']['data-table'] = $element['#list_container_selector'];
    $element['#attributes']['data-items'] = $element['#list_item'];
    if ($element['#list_text']) {
      $element['#attributes']['data-targets'] = $element['#list_text'];
    }
    $element['#attributes']['data-min-length'] = $element['#minimum_filter_length'];
    if ($element['#search_start_of_words']) {
      $element['#attributes']['data-search-start'] = 'true';
    }
    if ($element['#library']) {
      $element['#attached']['library'][] = $element['#library'];
    }
    $element['#attributes']['data-singular'] = $element['#announce']['singular'];
    $element['#attributes']['data-plural'] = $element['#announce']['plural'];
    $element['#attributes']['data-full'] = $element['#announce']['all'];
    if ($element['#debug']) {
      $element['#attributes']['data-debug'] = 'true';
    }

    return $element;
  }

}
