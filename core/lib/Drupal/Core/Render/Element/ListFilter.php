<?php

namespace Drupal\Core\Render\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;

/**
 * Provides a search element for filtering a list with JavaScript.
 *
 * - #list_container_id: (optional) The CSS ID of the container to search in. If
 *   omitted, defaults to 'filter-container'.
 * - #list_item: (optional) The CSS selector, relative to the container, for the
 *   items to search for. If omitted, defaults to '.filter-item'.
 * - #list_text: (optional) The CSS selector, relative to a list item, of
 *   elements containing text to search for. This may produce multiple elements.
 *   Defaults to an empty string, which indicates that the whole of the list
 *   item should be considered searchable text.
 * - #minimum_filter_length: (optional) The minimum length of the typed string
 *   before a filter is triggered. Defaults to 1.
 * - #search_start_of_words: (optional) Whether the entered filter text will
 *   search anywhere within words, or only from the start of words. For example,
 *   if TRUE, typing 'ke' will not filter the word 'cake'. Defaults to FALSE.
 * - #list_group: (optional) The CSS selector, relative to the container, for
 *   the groups of items. If omitted, the list is not considered to have
 *   grouping.
 * - #library: (optional) The name of the front-end library to use for the
 *   filtering behaviour. Core includes the following libraries:
 *   - core/drupal.list-filter: (default) Supports both no grouping, and groups
 *     which contain their items.
 *   - core/drupal.list-filter.details: Additional behaviors for HTML <details>
 *     elements.
 *   - core/drupal.list-filter.sibling-groups: Item elements are siblings of
 *     group elements, rather than child elements. The group of an item is its
 *     first prior sibling element that is a group.
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
      '#list_container_id' => 'filter-container',
      '#list_item' => '.filter-item',
      '#minimum_filter_length' => 1,
      '#list_text' => '',
      '#list_group' => '',
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

    $list_filter_id = $element['#list_container_id'];

    // Ensure this element has a unique HTML ID.
    $search_field_id = Html::getUniqueId($element['#attributes']['id'] ?? $list_filter_id . '-search');
    $element['#attributes']['id'] = $search_field_id;

    $settings = [
      'search_field_id' => $search_field_id,
    ];

    foreach ([
      '#list_container_id',
      '#list_item',
      '#minimum_filter_length',
      '#search_start_of_words',
      '#list_text',
      '#list_group',
      '#announce',
      '#debug',
    ] as $key) {
      $settings[substr($key, 1)] = $element[$key];
    }

    $element['#attached']['drupalSettings']['listFilter'][$list_filter_id] = $settings;

    $element['#attached']['library'][] = $element['#library'];

    return $element;
  }

}
