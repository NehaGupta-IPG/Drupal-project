<?php

namespace Drupal\field_ui\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Table;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a field_ui table element.
 */
#[RenderElement('field_ui_table')]
class FieldUiTable extends Table {


  /**
   * @var mixed Automatically converted property
   */
  public $header;

  /**
   * @var mixed Automatically converted property
   */
  public $rows;

  /**
   * @var mixed Automatically converted property
   */
  public $empty;

  /**
   * @var mixed Automatically converted property
   */
  public $tableselect;

  /**
   * @var mixed Automatically converted property
   */
  public $sticky;

  /**
   * @var mixed Automatically converted property
   */
  public $responsive;

  /**
   * @var mixed Automatically converted property
   */
  public $multiple;

  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $js_select;

  /**
   * @var mixed Automatically converted property
   */
  public $tabledrag;

  /**
   * @var mixed Automatically converted property
   */
  public $regions;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#regions'] = ['' => []];
    $info['#theme'] = 'field_ui_table';
    // Prepend FieldUiTable's prerender callbacks.
    array_unshift($info['#pre_render'], [$this, 'tablePreRender'], [$this, 'preRenderRegionRows']);
    return $info;
  }

  /**
   * Performs pre-render tasks on field_ui_table elements.
   *
   * @param array $elements
   *   A structured array containing two sub-levels of elements. Properties
   *   used:
   *   - #region_callback: A callback that provides the region of the table to
   *     place the row in.
   *   - #tabledrag: The value is a list of $options arrays that are passed to
   *     drupal_attach_tabledrag(). The HTML ID of the table is added to each
   *     $options array.
   *
   * @return array
   *   The $element with prepared variables ready for field-ui-table.html.twig.
   *
   * @see \Drupal\Core\Render\RendererInterface::render()
   * @see \Drupal\Core\Render\Element\Table::preRenderTable()
   */
  public static function tablePreRender($elements) {
    $js_settings = [];

    // For each region, build the tree structure from the weight and parenting
    // data contained in the flat form structure, to determine row order and
    // indentation.
    $regions = $elements['#regions'];
    $tree = ['' => ['name' => '', 'children' => []]];
    $trees = array_fill_keys(array_keys($regions), $tree);

    $parents = [];
    $children = Element::children($elements);
    $list = array_combine($children, $children);

    // Iterate on rows until we can build a known tree path for all of them.
    while ($list) {
      foreach ($list as $name) {
        $row = &$elements[$name];
        $parent = $row['parent_wrapper']['parent']['#value'];
        // Proceed if parent is known.
        if (empty($parent) || isset($parents[$parent])) {
          // Grab parent, and remove the row from the next iteration.
          $parents[$name] = $parent ? array_merge($parents[$parent], [$parent]) : [];
          unset($list[$name]);

          // Determine the region for the row.
          $region_name = call_user_func_array($row['#region_callback'], [&$row]);

          // Add the element in the tree.
          // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
          $target = &$trees[$region_name][''];
          foreach ($parents[$name] as $key) {
            $target = &$target['children'][$key];
          }
          $target['children'][$name] = ['name' => $name, 'weight' => $row['weight']['#value']];

          // Add tabledrag indentation to the first row cell.
          if ($depth = count($parents[$name])) {
            $children = Element::children($row);
            $cell = current($children);
            $indentation = [
              '#theme' => 'indentation',
              '#size' => $depth,
              '#suffix' => $row[$cell]['#prefix'] ?? '',
            ];
            $row[$cell]['#prefix'] = \Drupal::service('renderer')->render($indentation);
          }

          // Add row id and associate JS settings.
          $id = Html::getClass($name);
          $row['#attributes']['id'] = $id;
          if (isset($row['#js_settings'])) {
            $row['#js_settings'] += [
              'rowHandler' => $row['#row_type'],
              'name' => $name,
              'region' => $region_name,
            ];
            $js_settings[$id] = $row['#js_settings'];
          }
        }
      }
    }

    // Determine rendering order from the tree structure.
    foreach ($regions as $region_name => $region) {
      $elements['#regions'][$region_name]['rows_order'] = array_reduce($trees[$region_name], [static::class, 'reduceOrder']);
    }

    $elements['#attached']['drupalSettings']['fieldUIRowsData'] = $js_settings;

    // If the custom #tabledrag is set and there is an HTML ID, add the table's
    // HTML ID to the options and attach the behavior.
    // @see \Drupal\Core\Render\Element\Table::preRenderTable()
    if (!empty($elements['#tabledrag']) && isset($elements['#attributes']['id'])) {
      foreach ($elements['#tabledrag'] as $options) {
        $options['table_id'] = $elements['#attributes']['id'];
        drupal_attach_tabledrag($elements, $options);
      }
    }

    return $elements;
  }

  /**
   * Performs pre-render to move #regions to rows.
   *
   * @param array $elements
   *   A structured array containing two sub-levels of elements. Properties
   *   used:
   *   - #tabledrag: The value is a list of $options arrays that are passed to
   *     drupal_attach_tabledrag(). The HTML ID of the table is added to each
   *     $options array.
   *
   * @return array
   *   The $element with prepared variables ready for field-ui-table.html.twig.
   */
  public static function preRenderRegionRows($elements) {
    // Determine the colspan to use for region rows, by checking the number of
    // columns in the headers.
    $columns_count = 0;
    foreach ($elements['#header'] as $header) {
      $columns_count += (is_array($header) && isset($header['colspan']) ? $header['colspan'] : 1);
    }

    $rows = [];
    foreach (Element::children($elements) as $key) {
      $rows[$key] = $elements[$key];
      unset($elements[$key]);
    }

    // Render rows, region by region.
    foreach ($elements['#regions'] as $region_name => $region) {
      $region_name_class = Html::getClass($region_name);

      // Add region rows.
      if (isset($region['title']) && empty($region['invisible'])) {
        $elements['#rows'][] = [
          'class' => [
            'region-title',
            'region-' . $region_name_class . '-title',
          ],
          'no_striping' => TRUE,
          'data' => [
            ['data' => $region['title'], 'colspan' => $columns_count],
          ],
        ];
      }
      if (isset($region['message'])) {
        $class = (empty($region['rows_order']) ? 'region-empty' : 'region-populated');
        $elements['#rows'][] = [
          'class' => [
            'region-message',
            'region-' . $region_name_class . '-message', $class,
          ],
          'no_striping' => TRUE,
          'data' => [
            ['data' => $region['message'], 'colspan' => $columns_count],
          ],
        ];
      }

      // Add form rows, in the order determined at pre-render time.
      foreach ($region['rows_order'] as $name) {
        $element = $rows[$name];

        $row = ['data' => []];
        if (isset($element['#attributes'])) {
          $row += $element['#attributes'];
        }

        // Render children as table cells.
        foreach (Element::children($element) as $cell_key) {
          $child = $element[$cell_key];
          // Do not render a cell for children of #type 'value'.
          if (!(isset($child['#type']) && $child['#type'] == 'value')) {
            $cell = ['data' => $child];
            if (isset($child['#cell_attributes'])) {
              $cell += $child['#cell_attributes'];
            }
            $row['data'][] = $cell;
          }
        }
        $elements['#rows'][] = $row;
      }
    }

    return $elements;
  }

  /**
   * Determines the rendering order of an array representing a tree.
   *
   * Callback for array_reduce() within ::tablePreRender().
   *
   * @param mixed $array
   *   Holds the return value of the previous iteration; in the case of the
   *   first iteration it instead holds the value of the initial array.
   * @param mixed $a
   *   Holds the value of the current iteration.
   *
   * @return array
   *   Array where rendering order has been determined.
   */
  public static function reduceOrder($array, $a) {
    $array = $array ?: [];
    if (!empty($a['name'])) {
      $array[] = $a['name'];
    }
    if (!empty($a['children'])) {
      uasort($a['children'], ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
      $array = array_merge($array, array_reduce($a['children'], [static::class, 'reduceOrder']));
    }

    return $array;
  }

  /**
   * Create a field_ui_table element.
   *
   * @param mixed $header
   *   Automatically converted param.
   * @param mixed $rows
   *   Automatically converted param.
   * @param mixed $empty
   *   Automatically converted param.
   * @param mixed $input
   *   Automatically converted param.
   * @param mixed $tree
   *   Automatically converted param.
   * @param mixed $tableselect
   *   Automatically converted param.
   * @param mixed $sticky
   *   Automatically converted param.
   * @param mixed $responsive
   *   Automatically converted param.
   * @param mixed $multiple
   *   Automatically converted param.
   * @param mixed $js_select
   *   Automatically converted param.
   * @param mixed $process
   *   Automatically converted param.
   * @param mixed $element_validate
   *   Automatically converted param.
   * @param mixed $tabledrag
   *   Automatically converted param.
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $theme
   *   Automatically converted param.
   * @param mixed $regions
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
  public static function createInstance($header, $rows, $empty, $input, $tree, $tableselect, $sticky, $responsive, $multiple, $js_select, $process, $element_validate, $tabledrag, $pre_render, $theme, $regions, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('field_ui_table');
    $instance->header = $header;
    $instance->rows = $rows;
    $instance->empty = $empty;
    $instance->input = $input;
    $instance->tree = $tree;
    $instance->tableselect = $tableselect;
    $instance->sticky = $sticky;
    $instance->responsive = $responsive;
    $instance->multiple = $multiple;
    $instance->js_select = $js_select;
    $instance->process = $process;
    $instance->element_validate = $element_validate;
    $instance->tabledrag = $tabledrag;
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
    $instance->regions = $regions;
    $instance->value_callback = $value_callback;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#header' => $this->header,
      '#rows' => $this->rows,
      '#empty' => $this->empty,
      '#input' => $this->input,
      '#tree' => $this->tree,
      '#tableselect' => $this->tableselect,
      '#sticky' => $this->sticky,
      '#responsive' => $this->responsive,
      '#multiple' => $this->multiple,
      '#js_select' => $this->js_select,
      '#process' => $this->process,
      '#element_validate' => $this->element_validate,
      '#tabledrag' => $this->tabledrag,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#regions' => $this->regions,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
