<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for a table with radios or checkboxes in left column.
 *
 * Properties:
 * - #header: An array of table header labels.
 * - #options: An associative array where each key is the value returned when
 *   a user selects the radio button or checkbox, and each value is the row of
 *   table data.
 * - #empty: The message to display if table does not have any options.
 * - #multiple: Set to FALSE to render the table with radios instead checkboxes.
 * - #js_select: Set to FALSE if you don't want the select all checkbox added to
 *   the header.
 *
 * Other properties of the \Drupal\Core\Render\Element\Table element are also
 * available.
 *
 * Usage example:
 * @code
 * $header = [
 *   'color' => $this->t('Color'),
 *   'shape' => $this->t('Shape'),
 * ];
 *
 * $options = [
 *   1 => ['color' => 'Red', 'shape' => 'Triangle'],
 *   2 => ['color' => 'Green', 'shape' => 'Square'],
 *   // Prevent users from selecting a row by adding a '#disabled' property set
 *   // to TRUE.
 *   3 => ['color' => 'Blue', 'shape' => 'Hexagon', '#disabled' => TRUE],
 * ];
 *
 * $form['table'] = [
 *   '#type' => 'tableselect',
 *   '#header' => $header,
 *   '#options' => $options,
 *   '#empty' => $this->t('No shapes found'),
 * ];
 * @endcode
 *
 * See https://www.drupal.org/node/945102 for a full explanation.
 *
 * @see \Drupal\Core\Render\Element\Table
 */
#[FormElement('tableselect')]
class Tableselect extends Table {


  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $js_select;

  /**
   * @var mixed Automatically converted property
   */
  public $multiple;

  /**
   * @var mixed Automatically converted property
   */
  public $responsive;

  /**
   * @var mixed Automatically converted property
   */
  public $sticky;

  /**
   * @var mixed Automatically converted property
   */
  public $options;

  /**
   * @var mixed Automatically converted property
   */
  public $empty;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#js_select' => TRUE,
      '#multiple' => TRUE,
      '#responsive' => TRUE,
      '#sticky' => FALSE,
      '#pre_render' => [
        [static::class, 'preRenderTable'],
        [static::class, 'preRenderTableselect'],
      ],
      '#process' => [
        [static::class, 'processTableselect'],
      ],
      '#options' => [],
      '#empty' => '',
      '#theme' => 'table__tableselect',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // If $element['#multiple'] == FALSE, then radio buttons are displayed and
    // the default value handling is used.
    if (isset($element['#multiple']) && $element['#multiple']) {
      // Checkboxes are being displayed with the default value coming from the
      // keys of the #default_value property. This differs from the checkboxes
      // element which uses the array values.
      if ($input === FALSE) {
        $value = [];
        $element += ['#default_value' => []];
        foreach ($element['#default_value'] as $key => $flag) {
          if ($flag) {
            $value[$key] = $key;
          }
        }
        return $value;
      }
      else {
        return is_array($input) ? array_combine($input, $input) : [];
      }
    }
  }

  /**
   * Prepares a 'tableselect' #type element for rendering.
   *
   * Adds a column of radio buttons or checkboxes for each row of a table.
   *
   * @param array $element
   *   An associative array containing the properties and children of
   *   the tableselect element. Properties used: #header, #options, #empty,
   *   and #js_select. The #options property is an array of selection options;
   *   each array element of #options is an array of properties. These
   *   properties can include #attributes, which is added to the
   *   table row's HTML attributes; see table.html.twig. An example of per-row
   *   options:
   *   @code
   *     $options = [
   *       [
   *         'title' => $this->t('How to Learn Drupal'),
   *         'content_type' => $this->t('Article'),
   *         'status' => 'published',
   *         '#attributes' => ['class' => ['article-row']],
   *       ],
   *       [
   *         'title' => $this->t('Privacy Policy'),
   *         'content_type' => $this->t('Page'),
   *         'status' => 'published',
   *         '#attributes' => ['class' => ['page-row']],
   *       ],
   *     ];
   *     $header = [
   *       'title' => $this->t('Title'),
   *       'content_type' => $this->t('Content type'),
   *       'status' => $this->t('Status'),
   *     ];
   *     $form['table'] = [
   *       '#type' => 'tableselect',
   *       '#header' => $header,
   *       '#options' => $options,
   *       '#empty' => $this->t('No content available.'),
   *     ];
   *   @endcode
   *
   * @return array
   *   The processed element.
   */
  public static function preRenderTableselect($element) {
    $rows = [];
    $header = $element['#header'];
    if (!empty($element['#options'])) {
      // Generate a table row for each selectable item in #options.
      foreach (Element::children($element) as $key) {
        $row = [];

        $row['data'] = [];
        if (isset($element['#options'][$key]['#attributes'])) {
          $row += $element['#options'][$key]['#attributes'];
        }
        // Render the checkbox / radio element.
        $row['data'][] = \Drupal::service('renderer')->render($element[$key]);

        // As table.html.twig only maps header and row columns by order, create
        // the correct order by iterating over the header fields.
        foreach ($element['#header'] as $fieldname => $title) {
          // A row cell can span over multiple headers, which means less row
          // cells than headers could be present.
          if (isset($element['#options'][$key][$fieldname])) {
            // A header can span over multiple cells and in this case the cells
            // are passed in an array. The order of this array determines the
            // order in which they are added.
            if (is_array($element['#options'][$key][$fieldname]) && !isset($element['#options'][$key][$fieldname]['data'])) {
              foreach ($element['#options'][$key][$fieldname] as $cell) {
                $row['data'][] = $cell;
              }
            }
            else {
              $row['data'][] = $element['#options'][$key][$fieldname];
            }
          }
        }
        if (!empty($element['#options'][$key]['#disabled'])) {
          $row['class'][] = 'disabled';
        }
        $rows[] = $row;
      }
      // Add an empty header or a "Select all" checkbox to provide room for the
      // checkboxes/radios in the first table column.
      if ($element['#js_select']) {
        // Add a "Select all" checkbox.
        $element['#attached']['library'][] = 'core/drupal.tableselect';
        array_unshift($header, ['class' => ['select-all']]);
      }
      else {
        // Add an empty header when radio buttons are displayed or a "Select
        // all" checkbox is not desired.
        array_unshift($header, '');
      }
    }

    $element['#header'] = $header;
    $element['#rows'] = $rows;

    return $element;
  }

  /**
   * Creates checkbox or radio elements to populate a tableselect table.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   tableselect element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processTableselect(&$element, FormStateInterface $form_state, &$complete_form) {
    if ($element['#multiple']) {
      $value = is_array($element['#value']) ? $element['#value'] : [];
    }
    else {
      // Advanced selection behavior makes no sense for radios.
      $element['#js_select'] = FALSE;
    }

    $element['#tree'] = TRUE;

    if (count($element['#options']) > 0) {
      if (!isset($element['#default_value']) || $element['#default_value'] === 0) {
        $element['#default_value'] = [];
      }

      // Create a checkbox or radio for each item in #options in such a way that
      // the value of the tableselect element behaves as if it had been of type
      // checkboxes or radios.
      foreach ($element['#options'] as $key => $choice) {
        // Do not overwrite manually created children.
        if (!isset($element[$key])) {
          $disabled = !empty($element['#options'][$key]['#disabled']);
          if ($element['#multiple']) {
            $title = '';
            if (isset($element['#options'][$key]['title']) && is_array($element['#options'][$key]['title'])) {
              if (!empty($element['#options'][$key]['title']['data']['#title'])) {
                $title = new TranslatableMarkup('Update @title', [
                  '@title' => $element['#options'][$key]['title']['data']['#title'],
                ]);
              }
            }
            $element[$key] = [
              '#type' => 'checkbox',
              '#title' => $title,
              '#title_display' => 'invisible',
              '#return_value' => $key,
              '#default_value' => isset($value[$key]) ? $key : NULL,
              '#attributes' => $element['#attributes'],
              '#disabled' => $disabled,
              '#ajax' => $element['#ajax'] ?? NULL,
            ];
          }
          else {
            // Generate the parents as the autogenerator does, so we will have a
            // unique id for each radio button.
            $parents_for_id = array_merge($element['#parents'], [$key]);
            $element[$key] = [
              '#type' => 'radio',
              '#title' => '',
              '#return_value' => $key,
              '#default_value' => ($element['#default_value'] == $key) ? $key : NULL,
              '#attributes' => $element['#attributes'],
              '#parents' => $element['#parents'],
              '#id' => HtmlUtility::getUniqueId('edit-' . implode('-', $parents_for_id)),
              '#disabled' => $disabled,
              '#ajax' => $element['#ajax'] ?? NULL,
            ];
          }
          if (isset($element['#options'][$key]['#weight'])) {
            $element[$key]['#weight'] = $element['#options'][$key]['#weight'];
          }
        }
      }
    }
    else {
      $element['#value'] = [];
    }
    return $element;
  }

  /**
   * Create a tableselect element.
   *
   * @param mixed $input
   *   Automatically converted param.
   * @param mixed $js_select
   *   Automatically converted param.
   * @param mixed $multiple
   *   Automatically converted param.
   * @param mixed $responsive
   *   Automatically converted param.
   * @param mixed $sticky
   *   Automatically converted param.
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $process
   *   Automatically converted param.
   * @param mixed $options
   *   Automatically converted param.
   * @param mixed $empty
   *   Automatically converted param.
   * @param mixed $theme
   *   Automatically converted param.
   * @param mixed $value_callback
   *   Automatically converted param.
   * @param mixed $type
   *   Automatically converted param.
   * @param mixed $defaults_loaded
   *   Automatically converted param.
   * @param ?\Drupal\Core\Render\ElementInfoManager $elementInfoManager
   *   The element info manager.
   *
   * @return static
   */
  public static function createInstance($input, $js_select, $multiple, $responsive, $sticky, $pre_render, $process, $options, $empty, $theme, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('tableselect');
    $instance->input = $input;
    $instance->js_select = $js_select;
    $instance->multiple = $multiple;
    $instance->responsive = $responsive;
    $instance->sticky = $sticky;
    $instance->pre_render = $pre_render;
    $instance->process = $process;
    $instance->options = $options;
    $instance->empty = $empty;
    $instance->theme = $theme;
    $instance->value_callback = $value_callback;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#input' => $this->input,
      '#js_select' => $this->js_select,
      '#multiple' => $this->multiple,
      '#responsive' => $this->responsive,
      '#sticky' => $this->sticky,
      '#pre_render' => $this->pre_render,
      '#process' => $this->process,
      '#options' => $this->options,
      '#empty' => $this->empty,
      '#theme' => $this->theme,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
