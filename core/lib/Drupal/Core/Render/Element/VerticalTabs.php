<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;

/**
 * Provides a render element for vertical tabs in a form.
 *
 * Formats all child and non-child details elements whose #group is assigned
 * this element's name as vertical tabs.
 *
 * Properties:
 * - #default_tab: The HTML ID of the rendered details element to be used as
 *   the default tab. View the source of the rendered page to determine the ID.
 *
 * Usage example:
 * @code
 * $form['information'] = [
 *   '#type' => 'vertical_tabs',
 *   '#default_tab' => 'edit-publication',
 * ];
 *
 * $form['author'] = [
 *   '#type' => 'details',
 *   '#title' => $this->t('Author'),
 *   '#group' => 'information',
 * ];
 *
 * $form['author']['name'] = [
 *   '#type' => 'textfield',
 *   '#title' => $this->t('Name'),
 * ];
 *
 * $form['publication'] = [
 *   '#type' => 'details',
 *   '#title' => $this->t('Publication'),
 *   '#group' => 'information',
 * ];
 *
 * $form['publication']['publisher'] = [
 *   '#type' => 'textfield',
 *   '#title' => $this->t('Publisher'),
 * ];
 * @endcode
 */
#[FormElement('vertical_tabs')]
class VerticalTabs extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#default_tab' => '',
      '#process' => [
        [static::class, 'processVerticalTabs'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderVerticalTabs'],
      ],
      '#theme_wrappers' => ['vertical_tabs', 'form_element'],
    ];
  }

  /**
   * Prepares a vertical_tabs element for rendering.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   vertical tabs element.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderVerticalTabs($element) {
    // Do not render the vertical tabs element if it is empty.
    $group = implode('][', $element['#parents']);
    $visible_children = Element::getVisibleChildren($element['group']['#groups'][$group]);
    if (!$visible_children) {
      $element['#printed'] = TRUE;
      return $element;
    }

    // Build the tab menu server side so the vertical tabs widget is complete
    // at first paint, before vertical-tabs.js adopts it. Only visible details
    // children become panes. Sort by weight, as rendering the group does, so
    // the menu order matches the pane order and the first menu item is the
    // pane vertical-tabs.js falls back to.
    $pane_keys = [];
    foreach (Element::children($element['group']['#groups'][$group], TRUE) as $key) {
      $child = $element['group']['#groups'][$group][$key];
      if (($child['#type'] ?? NULL) !== 'details' || !Element::isVisibleElement($child)) {
        continue;
      }
      // An optional details element with no visible children of its own is
      // not printed, so it never becomes a pane.
      // @see \Drupal\Core\Render\Element\Details::preRenderDetails()
      if (!empty($child['#optional']) && isset($child['#parents'])) {
        $child_group = implode('][', $child['#parents']);
        if (!Element::getVisibleChildren($element['group']['#groups'][$child_group] ?? [])) {
          continue;
        }
      }
      $pane_keys[] = $key;
    }
    if (!$pane_keys) {
      return $element;
    }

    // Determine the selected tab the same way vertical-tabs.js does: the
    // #default_tab pane (which processVerticalTabs() has set to the persisted
    // active tab when one was submitted), or the first pane. HTML IDs gain
    // "--" suffixes when they are regenerated during AJAX rebuilds, so when
    // no pane matches exactly, compare with the suffixes removed.
    $default_tab = (string) $element['#default_tab'];
    $strip_suffix = fn (string $id): string => preg_replace('/--.+$/', '', $id);
    $selected_key = NULL;
    foreach ($pane_keys as $key) {
      $id = $element['group']['#groups'][$group][$key]['#id'] ?? NULL;
      if ($id !== NULL && $id === $default_tab) {
        $selected_key = $key;
        break;
      }
    }
    if ($selected_key === NULL && $default_tab !== '') {
      foreach ($pane_keys as $key) {
        $id = $element['group']['#groups'][$group][$key]['#id'] ?? NULL;
        if ($id !== NULL && $strip_suffix($id) === $strip_suffix($default_tab)) {
          $selected_key = $key;
          break;
        }
      }
    }
    $selected_key ??= $pane_keys[0];

    // The selected pane is marked with an attribute instead of #open: the
    // pre-enhancement CSS uses it to show only the selected pane, while the
    // #open states the form defined are kept intact for rendering, so users
    // without JavaScript keep the expanded panes the form intended.
    $menu_items = [];
    foreach ($pane_keys as $key) {
      $child = &$element['group']['#groups'][$group][$key];
      if ($key === $selected_key) {
        $child['#attributes']['data-vertical-tabs-selected'] = 'true';
      }
      $menu_items[] = [
        'id' => $child['#id'] ?? '',
        'title' => $child['#title'] ?? '',
        'selected' => $key === $selected_key,
      ];
    }
    $element['#menu_items'] = $menu_items;
    return $element;
  }

  /**
   * Creates a group formatted as vertical tabs.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   details element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processVerticalTabs(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($element['#access']) && !$element['#access']) {
      return $element;
    }

    // Inject a new details as child, so that form_process_details() processes
    // this details element like any other details.
    $element['group'] = [
      '#type' => 'details',
      '#theme_wrappers' => [],
      '#parents' => $element['#parents'],
    ];

    // Add an invisible label for accessibility.
    if (!isset($element['#title'])) {
      $element['#title'] = t('Vertical Tabs');
      $element['#title_display'] = 'invisible';
    }

    $element['#attached']['library'][] = 'core/drupal.vertical-tabs';

    // The JavaScript stores the currently selected tab in this hidden
    // field so that the active tab can be restored the next time the
    // form is rendered, e.g. on preview pages or when form validation
    // fails.
    $name = implode('__', $element['#parents']);
    if ($form_state->hasValue($name . '__active_tab')) {
      $element['#default_tab'] = $form_state->getValue($name . '__active_tab');
    }
    $element[$name . '__active_tab'] = [
      '#type' => 'hidden',
      '#default_value' => $element['#default_tab'],
      '#attributes' => ['class' => ['vertical-tabs__active-tab']],
    ];
    // Clean up the active tab value so it's not accidentally stored in
    // settings forms.
    $form_state->addCleanValueKey($name . '__active_tab');

    return $element;
  }

}
